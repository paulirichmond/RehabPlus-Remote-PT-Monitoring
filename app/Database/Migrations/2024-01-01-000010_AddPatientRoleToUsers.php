<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPatientRoleToUsers extends Migration
{
    public function up(): void
    {
        $this->db->query("ALTER TABLE users MODIFY role ENUM('superadmin', 'manager', 'staff', 'therapist', 'patient') NOT NULL DEFAULT 'staff'");
        $this->db->query("UPDATE users SET role = 'patient' WHERE role = ''");
    }

    public function down(): void
    {
        $this->db->query("DELETE FROM users WHERE role = 'patient'");
        $this->db->query("ALTER TABLE users MODIFY role ENUM('superadmin', 'manager', 'staff', 'therapist') NOT NULL DEFAULT 'staff'");
    }
}
