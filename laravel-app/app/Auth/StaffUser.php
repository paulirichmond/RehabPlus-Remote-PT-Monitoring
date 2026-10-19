<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A signed-in dashboard user. The account lives in Firebase Auth and its
 * profile in Firestore users/{uid}; nothing is stored in SQL.
 *
 * Roles (users/{uid}.role):
 *   admin     - everything, including staff accounts and all patients
 *   therapist - their own patients (patients.therapistId == uid)
 *   staff     - front desk: patient list/contact details and the clinic inbox
 * Patients ('patient') use the mobile app and can't sign in here.
 */
class StaffUser implements Authenticatable
{
    public const ADMIN = 'admin';

    public const THERAPIST = 'therapist';

    public const STAFF = 'staff';

    public const ROLES = [self::ADMIN, self::THERAPIST, self::STAFF];

    public const ROLE_LABELS = [
        self::ADMIN => 'Administrator',
        self::THERAPIST => 'Physical Therapist',
        self::STAFF => 'Front Desk Staff',
    ];

    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $role,
    ) {
    }

    /** Build from a users/{uid} document, or null if it isn't an active staff account. */
    public static function fromDocument(?array $doc): ?self
    {
        if (! $doc || ! in_array($doc['role'] ?? null, self::ROLES, true) || ($doc['active'] ?? true) === false) {
            return null;
        }

        return new self($doc['_id'], (string) ($doc['name'] ?? ''), (string) ($doc['email'] ?? ''), $doc['role']);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ADMIN;
    }

    public function isTherapist(): bool
    {
        return $this->role === self::THERAPIST;
    }

    /** Clinical records (sessions, prescriptions, assessments, goals, notes). */
    public function isClinician(): bool
    {
        return $this->isAdmin() || $this->isTherapist();
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? ucfirst($this->role);
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): string
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    /** Passwords are checked by Firebase Auth, never stored here. */
    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
    }

    public function getRememberTokenName(): string
    {
        return '';
    }
}
