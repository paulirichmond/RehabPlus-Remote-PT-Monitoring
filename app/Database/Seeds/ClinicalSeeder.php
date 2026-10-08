<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ClinicalSeeder extends Seeder
{
    public function run(): void
    {
        $patients = [
            ['name' => 'Juan dela Cruz',   'condition' => 'Post-ACL Reconstruction',  'created_at' => date('Y-m-d H:i:s')],
            ['name' => 'Maria Santos',     'condition' => 'Rotator Cuff Repair',       'created_at' => date('Y-m-d H:i:s')],
            ['name' => 'Pedro Reyes',      'condition' => 'Lumbar Disc Herniation',    'created_at' => date('Y-m-d H:i:s')],
        ];
        $this->db->table('patients')->insertBatch($patients);

        $records = [
            ['patient_id' => 1, 'exercise_name' => 'Quad Sets',          'sets_prescribed' => 3, 'sets_completed' => 3, 'pain_level' => 2, 'recorded_at' => date('Y-m-d H:i:s', strtotime('-1 day')), 'created_at' => date('Y-m-d H:i:s')],
            ['patient_id' => 1, 'exercise_name' => 'Straight Leg Raise', 'sets_prescribed' => 3, 'sets_completed' => 2, 'pain_level' => 4, 'recorded_at' => date('Y-m-d H:i:s'),                      'created_at' => date('Y-m-d H:i:s')],
            ['patient_id' => 2, 'exercise_name' => 'Pendulum Exercise',  'sets_prescribed' => 4, 'sets_completed' => 4, 'pain_level' => 1, 'recorded_at' => date('Y-m-d H:i:s', strtotime('-2 days')), 'created_at' => date('Y-m-d H:i:s')],
            ['patient_id' => 2, 'exercise_name' => 'External Rotation',  'sets_prescribed' => 3, 'sets_completed' => 1, 'pain_level' => 6, 'recorded_at' => date('Y-m-d H:i:s'),                      'created_at' => date('Y-m-d H:i:s')],
            ['patient_id' => 3, 'exercise_name' => 'Cat-Cow Stretch',    'sets_prescribed' => 3, 'sets_completed' => 3, 'pain_level' => 3, 'recorded_at' => date('Y-m-d H:i:s', strtotime('-3 days')), 'created_at' => date('Y-m-d H:i:s')],
            ['patient_id' => 3, 'exercise_name' => 'Bird Dog',           'sets_prescribed' => 3, 'sets_completed' => 2, 'pain_level' => 5, 'recorded_at' => date('Y-m-d H:i:s'),                      'created_at' => date('Y-m-d H:i:s')],
        ];
        $this->db->table('exercise_records')->insertBatch($records);
    }
}
