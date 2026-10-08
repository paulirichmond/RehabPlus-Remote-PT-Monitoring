<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPatientSummaryPlan extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('patients', [
            'medical_summary' => ['type' => 'TEXT', 'null' => true, 'after' => 'condition'],
            'therapy_plan' => ['type' => 'TEXT', 'null' => true, 'after' => 'medical_summary'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('patients', ['medical_summary', 'therapy_plan']);
    }
}