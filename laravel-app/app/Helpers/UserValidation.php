<?php

namespace App\Helpers;

use App\Auth\StaffUser;
use App\Services\Firebase\FirebaseAdmin;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserValidation
{
    /**
     * Roles that can be picked in the Create Account / Edit User form.
     * Patient accounts are created from the Patients page and can't be chosen here.
     */
    public const STAFF_ROLES = StaffUser::ROLES;

    public static function newAccount(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email|max:150',
            'password' => 'required|string|min:'.FirebaseAdmin::MIN_PASSWORD.'|max:100',
            'role' => ['nullable', Rule::in(self::STAFF_ROLES)],
        ]);
        $data['role'] ??= StaffUser::STAFF;

        return $data;
    }

    /**
     * Name, role and optional new password. Therapist accounts keep their role
     * (their patients are linked to them), and admins can't change their own
     * role, so the clinic can't lose its last admin by accident.
     */
    public static function update(Request $request, array $user, StaffUser $by): array
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:100',
            'role' => ['nullable', Rule::in(self::STAFF_ROLES)],
            'password' => 'nullable|string|min:'.FirebaseAdmin::MIN_PASSWORD.'|max:100',
        ]);

        if ($user['role'] === StaffUser::THERAPIST || $user['uid'] === $by->id || empty($data['role'])) {
            $data['role'] = $user['role'];
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }
}
