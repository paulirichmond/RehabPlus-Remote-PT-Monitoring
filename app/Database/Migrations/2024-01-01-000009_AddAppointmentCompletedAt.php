<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAppointmentCompletedAt extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('appointments', [
            'completed_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'status'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('appointments', 'completed_at');
    }
}