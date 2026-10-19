<?php

namespace App\Services\User;

use App\Models\PatientModel;
use App\Models\UserModel;

class UserService
{
    private UserModel $users;

    public function __construct()
    {
        $this->users = new UserModel();
    }

    public function createStaff(array $input): void
    {
        $this->users->insert([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => password_hash($input['password'], PASSWORD_DEFAULT),
            'role' => UserModel::normalizeRoleForStaffAccount($input['role']),
            'is_active' => 1,
        ]);
    }

    /**
     * Create a patient login plus its patient profile. Returns false if the account could not be created.
     */
    public function createPatient(array $input): bool
    {
        $data = [
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => password_hash($input['password'], PASSWORD_DEFAULT),
            'role' => 'patient',
            'is_active' => 1,
        ];

        if ($this->users->insert($data) === false) {
            return false;
        }

        (new PatientModel())->insert([
            'user_id' => $this->users->getInsertID(),
            'name' => $data['name'],
            'condition' => 'Not specified',
        ]);

        return true;
    }

    public function update(array $user, array $input): void
    {
        $data = [
            'name' => $input['name'],
            'email' => $input['email'],
            'role' => $user['role'] !== 'patient'
                ? UserModel::normalizeRoleForStaffAccount($input['role'])
                : 'patient',
        ];

        if (! empty($input['password'])) {
            $data['password'] = password_hash($input['password'], PASSWORD_DEFAULT);
        }

        $this->users->update($user['id'], $data);
    }
}
