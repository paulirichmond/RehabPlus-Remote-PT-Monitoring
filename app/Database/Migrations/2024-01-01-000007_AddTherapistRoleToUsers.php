<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTherapistRoleToUsers extends Migration
{
    public function up(): void
    {
        $this->db->query("ALTER TABLE users MODIFY role ENUM('superadmin', 'manager', 'staff', 'therapist') NOT NULL DEFAULT 'staff'");
    }

    public function down(): void
    {
        $this->db->query("ALTER TABLE users MODIFY role ENUM('superadmin', 'manager', 'staff') NOT NULL DEFAULT 'staff'");
    }
}
