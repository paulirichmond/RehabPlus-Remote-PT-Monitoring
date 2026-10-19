<?php

namespace App\Services\Patient;

use App\Auth\StaffUser;
use App\Services\Firebase\FirestoreClient;
use Carbon\CarbonImmutable;

/**
 * Clinic-only records under each patient (the app can't read them; Firestore
 * rules deny anything not listed):
 *
 *   patients/{uid}/assessments/{id}  {type, score 0-100, assessedAt, notes, therapistId, therapistName, createdAt}
 *   patients/{uid}/goals/{id}        {title, details, targetDate, status, progress 0-100, therapistId, therapistName, createdAt, updatedAt, achievedAt}
 *   patients/{uid}/notes/{id}        {topic, text, noteDate, therapistId, therapistName, createdAt}
 */
class ClinicalRecordService
{
    public const ASSESSMENTS = 'assessments';

    public const GOALS = 'goals';

    public const NOTES = 'notes';

    public const GOAL_STATUSES = ['active' => 'In progress', 'achieved' => 'Achieved', 'discontinued' => 'Discontinued'];

    /** The field each kind is sorted by (newest first). */
    private const DATE_FIELD = [self::ASSESSMENTS => 'assessedAt', self::GOALS => 'createdAt', self::NOTES => 'noteDate'];

    /**
     * Up to this many patients, each one's subcollection is read separately
     * (in parallel), so only their records leave Firestore. Above it, one
     * collection-group read of the whole clinic is cheaper.
     */
    public const PER_PATIENT_READ_LIMIT = 15;

    public function __construct(private FirestoreClient $db)
    {
    }

    /**
     * Records of one kind for the given patients (the ones the user may see),
     * newest first.
     *
     * @param  array<int, array>  $patients  presented patients (uid, name)
     */
    public function list(string $kind, array $patients): array
    {
        $names = array_column($patients, 'name', 'uid');

        if ($names === []) {
            return [];
        }

        $docs = count($names) <= self::PER_PATIENT_READ_LIMIT
            ? $this->readEachPatient($kind, array_keys($names))
            : $this->db->query('', $kind, allDescendants: true);

        $rows = [];
        foreach ($docs as $doc) {
            $patientUid = explode('/', $doc['_path'])[1] ?? null;
            // Collection-group reads also match a "notes"/"goals" collection elsewhere; keep only patients' ones.
            if (! str_starts_with($doc['_path'], 'patients/') || ! isset($names[$patientUid])) {
                continue;
            }
            $rows[] = $this->present($kind, $doc) + ['patientUid' => $patientUid, 'patientName' => $names[$patientUid]];
        }

        $field = self::DATE_FIELD[$kind];
        usort($rows, fn ($a, $b) => ($b[$field]?->getTimestamp() ?? 0) <=> ($a[$field]?->getTimestamp() ?? 0));

        return $rows;
    }

    /** One subcollection read per patient, all sent in parallel. */
    private function readEachPatient(string $kind, array $patientUids): array
    {
        $tasks = [];
        foreach ($patientUids as $uid) {
            $tasks[] = fn () => $this->db->query("patients/{$uid}", $kind);
        }

        return array_merge(...$this->db->concurrently($tasks));
    }

    public function find(string $kind, string $patientUid, string $id): ?array
    {
        if (! FirestoreClient::isValidId($id)) {
            return null;
        }
        $doc = $this->db->get("patients/{$patientUid}/{$kind}/{$id}");

        return $doc ? $this->present($kind, $doc) : null;
    }

    public function createAssessment(StaffUser $by, array $patient, array $data): void
    {
        $this->create($by, $patient, self::ASSESSMENTS, [
            'type' => $data['type'],
            'score' => (int) $data['score'],
            'assessedAt' => $this->date($data['date']),
            'notes' => trim((string) ($data['notes'] ?? '')),
        ]);
    }

    public function createGoal(StaffUser $by, array $patient, array $data): void
    {
        $this->create($by, $patient, self::GOALS, [
            'title' => $data['title'],
            'details' => trim((string) ($data['details'] ?? '')),
            'targetDate' => empty($data['target_date']) ? null : $this->date($data['target_date']),
            'status' => 'active',
            'progress' => (int) ($data['progress'] ?? 0),
            'achievedAt' => null,
        ], ['updatedAt']);
    }

    /** Update a goal's progress/status; reaching "achieved" stamps achievedAt and 100%. */
    public function updateGoal(array $patient, array $goal, array $data): void
    {
        $achieved = $data['status'] === 'achieved';
        $fields = [
            'status' => $data['status'],
            'progress' => $achieved ? 100 : (int) $data['progress'],
        ];
        if ($achieved !== ($goal['status'] === 'achieved')) {
            $fields['achievedAt'] = $achieved ? CarbonImmutable::now() : null;
        }

        $this->db->commit([
            $this->db->mergeWrite("patients/{$patient['uid']}/goals/{$goal['id']}", $fields, serverTimestamps: ['updatedAt']),
        ]);
    }

    public function createNote(StaffUser $by, array $patient, array $data): void
    {
        $this->create($by, $patient, self::NOTES, [
            'topic' => $data['topic'],
            'text' => $data['text'],
            'noteDate' => $this->date($data['date']),
        ]);
    }

    public function delete(string $kind, array $patient, string $id): void
    {
        $this->db->delete("patients/{$patient['uid']}/{$kind}/{$id}");
    }

    /** % of goals achieved (discontinued ones don't count), or null with no goals. */
    public static function goalAchievement(array $goals): ?int
    {
        $counted = array_filter($goals, fn ($g) => $g['status'] !== 'discontinued');

        return $counted
            ? (int) round(count(array_filter($counted, fn ($g) => $g['status'] === 'achieved')) / count($counted) * 100)
            : null;
    }

    private function create(StaffUser $by, array $patient, string $kind, array $fields, array $extraTimestamps = []): void
    {
        $this->db->commit([
            $this->db->createWrite("patients/{$patient['uid']}/{$kind}/".FirestoreClient::newId(), $fields + [
                'therapistId' => $by->id,
                'therapistName' => $by->name,
            ], serverTimestamps: array_merge(['createdAt'], $extraTimestamps)),
        ]);
    }

    /** A Y-m-d form date as a timestamp at the start of that day, clinic time. */
    private function date(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, config('app.timezone'))->startOfDay();
    }

    private function present(string $kind, array $doc): array
    {
        $common = [
            'id' => $doc['_id'],
            'therapistId' => $doc['therapistId'] ?? null,
            'therapistName' => (string) ($doc['therapistName'] ?? ''),
            'createdAt' => $doc['createdAt'] ?? null,
        ];

        return $common + match ($kind) {
            self::ASSESSMENTS => [
                'type' => (string) ($doc['type'] ?? ''),
                'score' => (int) ($doc['score'] ?? 0),
                'assessedAt' => $doc['assessedAt'] ?? $doc['createdAt'] ?? null,
                'notes' => (string) ($doc['notes'] ?? ''),
            ],
            self::GOALS => [
                'title' => (string) ($doc['title'] ?? ''),
                'details' => (string) ($doc['details'] ?? ''),
                'targetDate' => $doc['targetDate'] ?? null,
                'status' => array_key_exists($doc['status'] ?? '', self::GOAL_STATUSES) ? $doc['status'] : 'active',
                'progress' => max(0, min(100, (int) ($doc['progress'] ?? 0))),
                'achievedAt' => $doc['achievedAt'] ?? null,
            ],
            self::NOTES => [
                'topic' => (string) ($doc['topic'] ?? ''),
                'text' => (string) ($doc['text'] ?? ''),
                'noteDate' => $doc['noteDate'] ?? $doc['createdAt'] ?? null,
            ],
        };
    }
}
