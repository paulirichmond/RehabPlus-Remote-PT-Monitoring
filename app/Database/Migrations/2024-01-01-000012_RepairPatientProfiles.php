<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RepairPatientProfiles extends Migration
{
    public function up(): void
    {
        $this->db->query("INSERT INTO patients (user_id, name, `condition`) SELECT u.id, u.name, 'Not specified' FROM users u LEFT JOIN patients p ON p.user_id = u.id WHERE u.role = 'patient' AND p.id IS NULL");
    }

    public function down(): void
    {
        $this->db->query("DELETE p FROM patients p INNER JOIN users u ON u.id = p.user_id WHERE u.role = 'patient' AND p.`condition` = 'Not specified'");
    }
}