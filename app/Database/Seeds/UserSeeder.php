<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name'       => 'Super Admin',
                'email'      => 'superadmin@rehabplus.com',
                'password'   => password_hash('admin123', PASSWORD_DEFAULT),
                'role'       => 'superadmin',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name'       => 'Jane Manager',
                'email'      => 'manager@rehabplus.com',
                'password'   => password_hash('manager123', PASSWORD_DEFAULT),
                'role'       => 'manager',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name'       => 'John Staff',
                'email'      => 'staff@rehabplus.com',
                'password'   => password_hash('staff123', PASSWORD_DEFAULT),
                'role'       => 'staff',
                'created_at' => date('Y-m-d H:i:s'),
            ],
        ];

        foreach ($users as $user) {
            $this->db->table('users')->ignore(true)->insert($user);
        }
    }
}
