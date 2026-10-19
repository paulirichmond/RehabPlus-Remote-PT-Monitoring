<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAppointmentsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'patient' => ['type' => 'VARCHAR', 'constraint' => 100],
            'therapist' => ['type' => 'VARCHAR', 'constraint' => 100],
            'patient_condition' => ['type' => 'VARCHAR', 'constraint' => 150],
            'contact' => ['type' => 'VARCHAR', 'constraint' => 50],
            'date' => ['type' => 'DATE'],
            'time' => ['type' => 'TIME'],
            'session' => ['type' => 'VARCHAR', 'constraint' => 100],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Upcoming'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('appointments', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('appointments');
    }
}
