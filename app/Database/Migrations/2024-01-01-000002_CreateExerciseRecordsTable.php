<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateExerciseRecordsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'auto_increment' => true],
            'patient_id'      => ['type' => 'INT'],
            'exercise_name'   => ['type' => 'VARCHAR', 'constraint' => 150],
            'sets_prescribed' => ['type' => 'INT'],
            'sets_completed'  => ['type' => 'INT'],
            'pain_level'      => ['type' => 'TINYINT', 'constraint' => 2, 'default' => 0],
            'notes'           => ['type' => 'TEXT', 'null' => true],
            'recorded_at'     => ['type' => 'DATETIME'],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('patient_id', 'patients', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('exercise_records');
    }

    public function down(): void
    {
        $this->forge->dropTable('exercise_records');
    }
}
