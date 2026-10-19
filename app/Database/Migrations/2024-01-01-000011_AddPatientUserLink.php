<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPatientUserLink extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('patients', [
            'user_id' => ['type' => 'INT', 'null' => true, 'unique' => true, 'after' => 'id'],
        ]);

        $this->db->query("UPDATE patients p JOIN users u ON u.name = p.name AND u.role = 'patient' SET p.user_id = u.id WHERE p.user_id IS NULL");
    }

    public function down(): void
    {
        $this->forge->dropColumn('patients', 'user_id');
    }
}