<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = [
        'name',
        'email',
        'password',
        'role',
        'avatar',
        'is_active'
    ];

    protected $useTimestamps = false;

    public static function getStaffRoles(): array
    {
        return ['staff', 'therapist', 'manager', 'superadmin'];
    }

    public static function normalizeRoleForStaffAccount(?string $role): string
    {
        $normalized = strtolower(trim((string) ($role ?? '')));

        if ($normalized === '' || $normalized === 'patient') {
            return 'staff';
        }

        if (in_array($normalized, self::getStaffRoles(), true)) {
            return $normalized;
        }

        return 'staff';
    }

    public function findByEmail(string $email): ?array
    {
        return $this->where('email', $email)->first();
    }
}