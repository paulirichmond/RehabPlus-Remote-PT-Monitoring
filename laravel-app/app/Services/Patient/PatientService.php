<?php

namespace App\Services\Patient;

use App\Auth\StaffUser;
use App\Services\Firebase\FirebaseAdmin;
use App\Services\Firebase\FirebaseException;
use App\Services\Firebase\FirestoreClient;
use App\Services\User\UserService;
use Illuminate\Support\Facades\Log;

/**
 * Patients live in Firestore patients/{uid}, keyed by their Firebase Auth uid
 * (they sign in to the mobile app with it). See API_CONTRACT.md in the app repo.
 *
 * The service account bypasses Firestore rules, so access is checked here:
 *   - admin and front-desk staff see every patient
 *   - therapists see only patients whose therapistId is their uid
 *   - only admins and the patient's own therapist change anything
 */
class PatientService
{
    public function __construct(
        private FirestoreClient $db,
        private FirebaseAdmin $admin,
        private UserService $users,
    ) {
    }

    /** Patients this user may see, sorted by name. */
    public function visible(StaffUser $user, bool $includeArchived = false): array
    {
        $docs = $user->isTherapist()
            ? $this->db->query('', 'patients', [['therapistId', 'EQUAL', $user->id]])
            : $this->db->query('', 'patients');

        $patients = array_map(fn ($d) => $this->present($d), $docs);
        if (! $includeArchived) {
            $patients = array_filter($patients, fn ($p) => ! $p['archived']);
        }
        usort($patients, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return array_values($patients);
    }

    /** Patients list page data: search by name/condition/email, filter by condition. */
    public function search(StaffUser $user, string $search, string $condition, bool $archived): array
    {
        $all = $this->visible($user, includeArchived: true);
        $needle = mb_strtolower($search);

        $data = array_filter($all, fn ($p) => $p['archived'] === $archived
            && ($condition === '' || $p['condition'] === $condition)
            && ($needle === '' || str_contains(mb_strtolower($p['name'].' '.$p['condition'].' '.$p['email']), $needle)));

        $conditions = array_unique(array_filter(array_column($all, 'condition')));
        sort($conditions, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'data' => array_values($data),
            'conditions' => $conditions,
            'search' => $search,
            'selectedCondition' => $condition,
            'showArchived' => $archived,
        ];
    }

    /** The patient if this user may see them, else null (callers 404). */
    public function find(StaffUser $user, string $uid): ?array
    {
        if (! FirestoreClient::isValidId($uid)) {
            return null;
        }

        $doc = $this->db->get("patients/{$uid}");
        $patient = $doc ? $this->present($doc) : null;

        if (! $patient || ! $this->canView($user, $patient)) {
            return null;
        }

        // Patients created by older seed scripts have no email on their profile.
        if ($patient['email'] === '') {
            $patient['email'] = (string) ($this->db->get("users/{$uid}")['email'] ?? '');
        }

        return $patient;
    }

    public function canView(StaffUser $user, array $patient): bool
    {
        return ! $user->isTherapist() || $patient['therapistId'] === $user->id;
    }

    /** Edit profile, prescriptions, assessments, goals and notes. */
    public function canManage(StaffUser $user, array $patient): bool
    {
        return $user->isAdmin() || ($user->isTherapist() && $patient['therapistId'] === $user->id);
    }

    /**
     * Create the patient's app login (Firebase Auth, temporary password) plus
     * users/{uid} and patients/{uid}. A therapist's new patient is assigned to
     * them; an admin picks the therapist (or none).
     *
     * @return string the new patient's uid
     */
    public function create(StaffUser $by, array $data): string
    {
        [$therapistId, $therapistName] = $this->therapistFor($by, $data['therapist_id'] ?? null);

        $uid = $this->admin->createUser($data['email'], $data['password'], $data['name']);

        $this->users->commitOrRollBack($uid, [
            $this->db->createWrite("users/{$uid}", [
                'role' => 'patient',
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'active' => true,
            ], serverTimestamps: ['createdAt']),
            $this->db->createWrite("patients/{$uid}", $this->profileFields($data) + [
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'therapistId' => $therapistId,
                'therapistName' => $therapistName,
                'archived' => false,
                'createdBy' => $by->id,
            ], serverTimestamps: ['createdAt']),
        ]);

        return $uid;
    }

    public function update(StaffUser $by, array $patient, array $data): void
    {
        $fields = $this->profileFields($data) + ['name' => $data['name']];

        if ($by->isAdmin() && array_key_exists('therapist_id', $data)) {
            [$fields['therapistId'], $fields['therapistName']] = $this->therapistFor($by, $data['therapist_id']);
        }

        $this->db->commit([
            $this->db->mergeWrite("patients/{$patient['uid']}", $fields, serverTimestamps: ['updatedAt']),
            $this->db->mergeWrite("users/{$patient['uid']}", ['name' => $data['name']]),
        ]);

        // The login's display name is cosmetic (the app shows users/{uid}.name), so
        // a failure here mustn't report the already-saved edit as failed.
        if ($data['name'] !== $patient['name']) {
            try {
                $this->admin->updateUser($patient['uid'], ['displayName' => $data['name']]);
            } catch (FirebaseException $e) {
                Log::warning('Could not update patient display name: '.$e->getMessage(), ['patient' => $patient['uid']]);
            }
        }
    }

    /**
     * Archive (disable the app login and hide from lists) or restore. Nothing
     * is deleted: sessions and records stay for the clinic's history.
     */
    public function setArchived(array $patient, bool $archived): void
    {
        $this->admin->updateUser($patient['uid'], ['disabled' => $archived]);
        $this->db->commit([
            $this->db->mergeWrite("patients/{$patient['uid']}", ['archived' => $archived], serverTimestamps: ['updatedAt']),
            $this->db->mergeWrite("users/{$patient['uid']}", ['active' => ! $archived]),
        ]);
    }

    /** Set a new temporary app password (the patient can change it via "Forgot password"). */
    public function resetPassword(array $patient, string $password): void
    {
        $this->admin->updateUser($patient['uid'], ['password' => $password]);
    }

    public function present(array $doc): array
    {
        return [
            'uid' => $doc['_id'],
            'name' => (string) ($doc['name'] ?? 'Unnamed patient'),
            'email' => (string) ($doc['email'] ?? ''),
            'condition' => (string) ($doc['condition'] ?? ''),
            'therapistId' => $doc['therapistId'] ?? null,
            'therapistName' => (string) ($doc['therapistName'] ?? ''),
            'medicalSummary' => (string) ($doc['medicalSummary'] ?? ''),
            'therapyPlan' => (string) ($doc['therapyPlan'] ?? ''),
            'phone' => (string) ($doc['phone'] ?? ''),
            'dob' => (string) ($doc['dob'] ?? ''),
            'createdAt' => $doc['createdAt'] ?? null,
            'archived' => (bool) ($doc['archived'] ?? false),
        ];
    }

    /** Form fields that map 1:1 onto the patients doc. */
    private function profileFields(array $data): array
    {
        return [
            'condition' => $data['condition'],
            'medicalSummary' => (string) ($data['medical_summary'] ?? ''),
            'therapyPlan' => (string) ($data['therapy_plan'] ?? ''),
            'phone' => (string) ($data['phone'] ?? ''),
            'dob' => (string) ($data['dob'] ?? ''),
        ];
    }

    /** @return array{0: ?string, 1: string} therapistId and therapistName */
    private function therapistFor(StaffUser $by, ?string $therapistId): array
    {
        if ($by->isTherapist()) {
            return [$by->id, $by->name];
        }
        if (! $therapistId) {
            return [null, ''];
        }

        $therapist = $this->users->findStaff($therapistId);
        if (! $therapist || $therapist['role'] !== StaffUser::THERAPIST) {
            throw new FirebaseException('The selected therapist no longer exists.');
        }

        return [$therapist['uid'], $therapist['name']];
    }
}
