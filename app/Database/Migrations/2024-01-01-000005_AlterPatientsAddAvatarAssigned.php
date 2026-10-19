<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterPatientsAddAvatarAssigned extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('patients', [
            'avatar'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'condition'],
            'assigned_to' => ['type' => 'INT', 'null' => true, 'after' => 'avatar'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('patients', ['avatar', 'assigned_to']);
    }
}
