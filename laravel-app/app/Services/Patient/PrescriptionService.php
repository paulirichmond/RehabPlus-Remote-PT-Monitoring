<?php

namespace App\Services\Patient;

use App\Auth\StaffUser;
use App\Services\Firebase\FirestoreClient;
use App\Services\Firebase\PatientNotifier;

/**
 * Assigned exercises: patients/{uid}/prescriptions/{id}. The app loads the
 * active ones into the patient's plan. Prescriptions are never deleted (old
 * sessions reference their id); stopping one sets active: false.
 */
class PrescriptionService
{
    public function __construct(private FirestoreClient $db, private PatientNotifier $notifier)
    {
    }

    /** Active first, then newest. */
    public function forPatient(string $patientUid): array
    {
        $list = array_map(fn ($d) => $this->present($d), $this->db->query("patients/{$patientUid}", 'prescriptions'));
        usort($list, fn ($a, $b) => [$b['active'], $b['createdAt']?->getTimestamp() ?? 0]
            <=> [$a['active'], $a['createdAt']?->getTimestamp() ?? 0]);

        return $list;
    }

    public function find(string $patientUid, string $id): ?array
    {
        if (! FirestoreClient::isValidId($id)) {
            return null;
        }
        $doc = $this->db->get("patients/{$patientUid}/prescriptions/{$id}");

        return $doc ? $this->present($doc) : null;
    }

    /**
     * Assign an exercise, log an "exercise_assigned" entry on the app's
     * Notifications page in the same commit, then push to the patient's phones.
     */
    public function create(StaffUser $by, array $patient, array $data): string
    {
        $id = FirestoreClient::newId();
        $fields = $this->fields($data);
        $body = $this->summary($fields);

        $this->db->commit([
            $this->db->createWrite("patients/{$patient['uid']}/prescriptions/{$id}", $fields + [
                'active' => true,
                'assignedBy' => $by->id,
            ], serverTimestamps: ['createdAt']),
            $this->notifier->notificationWrite($patient['uid'], 'exercise_assigned', 'New Exercise Assigned', $body),
        ]);

        $this->notifier->push($patient['uid'], 'New Exercise Assigned', $body, ['type' => 'exercise_assigned']);

        return $id;
    }

    public function update(array $patient, array $prescription, array $data): void
    {
        $this->db->commit([
            $this->db->mergeWrite(
                "patients/{$patient['uid']}/prescriptions/{$prescription['id']}",
                $this->fields($data),
                serverTimestamps: ['updatedAt'],
            ),
        ]);
    }

    public function setActive(array $patient, array $prescription, bool $active): void
    {
        $this->db->commit([
            $this->db->mergeWrite(
                "patients/{$patient['uid']}/prescriptions/{$prescription['id']}",
                ['active' => $active],
                serverTimestamps: ['updatedAt'],
            ),
        ]);
    }

    /** Validated form data -> Firestore fields (the shape the app parses). */
    private function fields(array $data): array
    {
        $days = array_values(array_unique(array_map('intval', $data['days'] ?? [])));
        sort($days);
        $hasHours = isset($data['start_hour'], $data['end_hour']);

        return [
            'exerciseType' => $data['exercise_type'],
            'targetReps' => (int) $data['target_reps'],
            'holdSeconds' => ExerciseCatalog::isHold($data['exercise_type']) && ! empty($data['hold_seconds'])
                ? (int) $data['hold_seconds'] : null,
            'frequencyPerWeek' => (int) $data['frequency_per_week'],
            'schedule' => ($days || $hasHours) ? [
                'days' => $days,
                'startHour' => $hasHours ? (int) $data['start_hour'] : null,
                'endHour' => $hasHours ? (int) $data['end_hour'] : null,
            ] : null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ];
    }

    private function summary(array $f): string
    {
        $what = ExerciseCatalog::isHold($f['exerciseType'])
            ? "{$f['targetReps']} holds"
            : "{$f['targetReps']} reps";

        return ExerciseCatalog::name($f['exerciseType']).": {$what}, {$f['frequencyPerWeek']}x per week.";
    }

    private function present(array $doc): array
    {
        $schedule = is_array($doc['schedule'] ?? null) ? $doc['schedule'] : [];

        return [
            'id' => $doc['_id'],
            'exerciseType' => $doc['exerciseType'] ?? '',
            'exerciseName' => ExerciseCatalog::name($doc['exerciseType'] ?? null),
            'isHold' => ExerciseCatalog::isHold($doc['exerciseType'] ?? null),
            'targetReps' => (int) ($doc['targetReps'] ?? 0),
            'holdSeconds' => isset($doc['holdSeconds']) ? (int) $doc['holdSeconds'] : null,
            'frequencyPerWeek' => (int) ($doc['frequencyPerWeek'] ?? 3),
            'days' => array_map('intval', $schedule['days'] ?? []),
            'startHour' => isset($schedule['startHour']) ? (int) $schedule['startHour'] : null,
            'endHour' => isset($schedule['endHour']) ? (int) $schedule['endHour'] : null,
            'notes' => (string) ($doc['notes'] ?? ''),
            'active' => (bool) ($doc['active'] ?? false),
            'createdAt' => $doc['createdAt'] ?? null,
        ];
    }
}
