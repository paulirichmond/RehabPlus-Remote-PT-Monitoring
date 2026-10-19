<?php

namespace App\Services\User;

use App\Auth\FirebaseUserProvider;
use App\Auth\StaffUser;
use App\Services\Firebase\FirebaseAdmin;
use App\Services\Firebase\FirebaseException;
use App\Services\Firebase\FirestoreClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Staff accounts: a Firebase Auth login plus Firestore users/{uid}
 * {role, name, email, active}. Patients also have users/{uid} (role
 * 'patient') but are managed from the Patients pages.
 */
class UserService
{
    public function __construct(private FirestoreClient $db, private FirebaseAdmin $admin)
    {
    }

    /** Every account, sorted by name. */
    public function all(): array
    {
        $users = array_map(fn ($d) => $this->present($d), $this->db->query('', 'users'));
        usort($users, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $users;
    }

    /** Staff accounts (admin, therapist, staff), active or not. */
    public function staff(): array
    {
        return array_values(array_filter($this->all(), fn ($u) => in_array($u['role'], StaffUser::ROLES, true)));
    }

    /** Active therapists, for "assigned therapist" pickers. */
    public function therapists(): array
    {
        return array_values(array_filter(
            $this->staff(),
            fn ($u) => $u['role'] === StaffUser::THERAPIST && $u['active'],
        ));
    }

    public function find(string $uid): ?array
    {
        if (! FirestoreClient::isValidId($uid)) {
            return null;
        }
        $doc = $this->db->get("users/{$uid}");

        return $doc ? $this->present($doc) : null;
    }

    /** A staff account (not a patient), or null. */
    public function findStaff(string $uid): ?array
    {
        $user = $this->find($uid);

        return $user && in_array($user['role'], StaffUser::ROLES, true) ? $user : null;
    }

    /**
     * Create the Firebase login and users/{uid}. If the Firestore write fails the
     * new login is deleted again, so there is never a login without a role.
     */
    public function create(array $data): string
    {
        $uid = $this->admin->createUser($data['email'], $data['password'], $data['name']);

        $this->commitOrRollBack($uid, [
            $this->db->createWrite("users/{$uid}", [
                'role' => $data['role'],
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'active' => true,
            ], serverTimestamps: ['createdAt']),
        ]);

        return $uid;
    }

    /**
     * Update name/role (and optionally the password). A therapist's new name is
     * copied to their patients' therapistName, which the app shows.
     */
    public function update(array $user, array $data): void
    {
        $writes = [$this->db->mergeWrite("users/{$user['uid']}", ['name' => $data['name'], 'role' => $data['role']])];

        if ($user['role'] === StaffUser::THERAPIST && $data['name'] !== $user['name']) {
            foreach ($this->db->query('', 'patients', [['therapistId', 'EQUAL', $user['uid']]]) as $patient) {
                $writes[] = $this->db->mergeWrite($patient['_path'], ['therapistName' => $data['name']]);
            }
        }

        $this->db->commit($writes);
        FirebaseUserProvider::forget($user['uid']);

        $this->admin->updateUser($user['uid'], array_filter([
            'displayName' => $data['name'],
            'password' => $data['password'] ?? null,
        ]));
    }

    /** Enable or disable a login (Firebase Auth + users.active). */
    public function setActive(array $user, bool $active): void
    {
        $this->admin->updateUser($user['uid'], ['disabled' => ! $active]);
        $this->db->commit([$this->db->mergeWrite("users/{$user['uid']}", ['active' => $active])]);
        FirebaseUserProvider::forget($user['uid']);
    }

    /**
     * Delete the Firebase login and users/{uid}. Refused while patients are
     * still assigned to the account (reassign them first, or disable instead).
     * Records keep the staff member's name (therapistName), so nothing else
     * needs to change.
     *
     * @return ?string why it can't be deleted, or null once deleted
     */
    public function delete(array $user): ?string
    {
        $assigned = count($this->db->query('', 'patients', [['therapistId', 'EQUAL', $user['uid']]]));
        if ($assigned > 0) {
            return "{$user['name']} still has {$assigned} assigned ".($assigned === 1 ? 'patient' : 'patients').'. Reassign them first, or disable the account instead.';
        }

        $this->admin->deleteUser($user['uid']);
        $this->db->delete("users/{$user['uid']}");
        FirebaseUserProvider::forget($user['uid']);
        Cache::forget(FirebaseAdmin::uidCacheKey($user['email']));

        return null;
    }

    /**
     * Uids (of the given accounts) that no longer have a Firebase login, or []
     * if Firebase Auth can't be checked right now.
     */
    public function withoutLogin(array $users): array
    {
        if (! $users) {
            return [];
        }

        try {
            return array_values(array_diff(array_column($users, 'uid'), $this->admin->existingUids(array_column($users, 'uid'))));
        } catch (FirebaseException $e) {
            Log::warning('Could not check staff logins', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /** Commit, or delete the just-created Firebase login and rethrow. */
    public function commitOrRollBack(string $uid, array $writes): void
    {
        try {
            $this->db->commit($writes);
        } catch (FirebaseException $e) {
            try {
                $this->admin->deleteUser($uid);
            } catch (FirebaseException $cleanup) {
                Log::error('Could not delete Firebase login after a failed write', ['uid' => $uid]);
            }

            throw $e;
        }
    }

    private function present(array $doc): array
    {
        return [
            'uid' => $doc['_id'],
            'name' => (string) ($doc['name'] ?? ''),
            'email' => (string) ($doc['email'] ?? ''),
            'role' => (string) ($doc['role'] ?? ''),
            'active' => ($doc['active'] ?? true) !== false,
            'createdAt' => $doc['createdAt'] ?? null,
        ];
    }
}
