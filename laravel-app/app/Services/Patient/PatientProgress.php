<?php

namespace App\Services\Patient;

use App\Services\Firebase\FirestoreClient;
use Carbon\CarbonImmutable;

/**
 * Exercise sessions the app uploads (patients/{uid}/sessions, read-only for
 * the dashboard) and the progress metrics from API_CONTRACT.md:
 * adherence, movement accuracy, pain, incomplete and unscheduled sessions.
 */
class PatientProgress
{
    public function __construct(private FirestoreClient $db)
    {
    }

    /** One patient's most recent sessions, newest first. */
    public function sessions(string $patientUid, int $limit = 100): array
    {
        $docs = $this->db->query("patients/{$patientUid}", 'sessions', orderBy: ['startTime', 'DESCENDING'], limit: $limit);

        return array_map(fn ($d) => self::presentSession($d), $docs);
    }

    /**
     * Sessions grouped by patient uid (newest first).
     *
     * Without $patientUids: every patient's sessions in one collection-group
     * read; callers drop patients the user can't see. With $patientUids: one
     * read per patient, in parallel, so only those patients' sessions are
     * fetched (used for therapists).
     *
     * @param  string[]|null  $patientUids
     * @return array<string, array>
     */
    public function sessionsByPatient(?array $patientUids = null): array
    {
        if ($patientUids === null) {
            $docs = $this->db->query('', 'sessions', allDescendants: true);
        } else {
            $tasks = [];
            foreach ($patientUids as $uid) {
                $tasks[] = fn () => $this->db->query("patients/{$uid}", 'sessions');
            }
            $docs = $tasks === [] ? [] : array_merge(...$this->db->concurrently($tasks));
        }

        $grouped = [];
        foreach ($docs as $doc) {
            $session = self::presentSession($doc);
            $grouped[$session['patientUid']][] = $session;
        }
        foreach ($grouped as &$sessions) {
            usort($sessions, fn ($a, $b) => ($b['startTime']?->getTimestamp() ?? 0) <=> ($a['startTime']?->getTimestamp() ?? 0));
        }

        return $grouped;
    }

    public static function presentSession(array $doc): array
    {
        $compliance = $doc['overallCompliance'] ?? null;

        return [
            'id' => $doc['_id'],
            'patientUid' => explode('/', $doc['_path'])[1] ?? null,
            'exerciseId' => $doc['exerciseId'] ?? null,
            'exerciseName' => $doc['exerciseName'] ?? ExerciseCatalog::name($doc['exerciseId'] ?? null),
            'prescriptionId' => $doc['prescriptionId'] ?? null,
            'startTime' => $doc['startTime'] ?? $doc['createdAt'] ?? null,
            'completedReps' => (int) ($doc['completedReps'] ?? 0),
            'rejectedReps' => (int) ($doc['rejectedReps'] ?? 0),
            'targetReps' => (int) ($doc['targetReps'] ?? 0),
            'accuracy' => $compliance === null ? null : round((float) $compliance * 100, 1),
            'painLevel' => isset($doc['painLevel']) ? (int) $doc['painLevel'] : null,
            'painNote' => $doc['painNote'] ?? null,
            'endedEarly' => (bool) ($doc['endedEarly'] ?? false),
            'outsideSchedule' => (bool) ($doc['outsideSchedule'] ?? false),
        ];
    }

    /**
     * Headline numbers for a set of sessions.
     *
     * @return array{total: int, completed: int, accuracy: float, avgPain: float, endedEarly: int, outsideSchedule: int, lastSessionAt: ?CarbonImmutable}
     */
    public static function stats(array $sessions): array
    {
        $accuracies = array_filter(array_column($sessions, 'accuracy'), fn ($v) => $v !== null);
        $pains = array_filter(array_column($sessions, 'painLevel'), fn ($v) => $v !== null);

        return [
            'total' => count($sessions),
            'completed' => count(array_filter($sessions, fn ($s) => ! $s['endedEarly'])),
            'accuracy' => $accuracies ? round(array_sum($accuracies) / count($accuracies), 1) : 0.0,
            'avgPain' => $pains ? round(array_sum($pains) / count($pains), 1) : 0.0,
            'endedEarly' => count(array_filter($sessions, fn ($s) => $s['endedEarly'])),
            'outsideSchedule' => count(array_filter($sessions, fn ($s) => $s['outsideSchedule'])),
            'lastSessionAt' => $sessions[0]['startTime'] ?? null,
        ];
    }

    /**
     * This week's adherence per active prescription: completed sessions in the
     * last 7 days / frequencyPerWeek (capped at 100%).
     *
     * @return array{perPrescription: array<string, array{done: int, target: int, percent: int}>, overall: ?int}
     */
    public static function adherence(array $sessions, array $prescriptions, ?CarbonImmutable $now = null): array
    {
        $since = ($now ?? CarbonImmutable::now())->subDays(7);
        $per = [];

        foreach ($prescriptions as $rx) {
            if (! $rx['active']) {
                continue;
            }
            $done = count(array_filter($sessions, fn ($s) => $s['prescriptionId'] === $rx['id']
                && ! $s['endedEarly'] && $s['startTime'] && $s['startTime']->greaterThanOrEqualTo($since)));
            $target = max(1, $rx['frequencyPerWeek']);
            $per[$rx['id']] = ['done' => $done, 'target' => $target, 'percent' => (int) min(100, round($done / $target * 100))];
        }

        return [
            'perPrescription' => $per,
            'overall' => $per ? (int) round(array_sum(array_column($per, 'percent')) / count($per)) : null,
        ];
    }

    /**
     * Average pain per day for the last $days days (null when no session that day).
     *
     * @return array<int, array{date: CarbonImmutable, pain: ?float}>
     */
    public static function painTrend(array $sessions, int $days = 14, ?CarbonImmutable $now = null): array
    {
        $today = ($now ?? CarbonImmutable::now())->setTimezone(config('app.timezone'))->startOfDay();
        $byDay = [];
        foreach ($sessions as $s) {
            if ($s['startTime'] && $s['painLevel'] !== null) {
                $byDay[$s['startTime']->setTimezone(config('app.timezone'))->format('Y-m-d')][] = $s['painLevel'];
            }
        }

        $trend = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = $today->subDays($i);
            $values = $byDay[$day->format('Y-m-d')] ?? [];
            $trend[] = ['date' => $day, 'pain' => $values ? round(array_sum($values) / count($values), 1) : null];
        }

        return $trend;
    }
}
