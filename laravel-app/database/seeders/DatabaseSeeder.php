<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\ExerciseRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::query()->truncate();
        Patient::query()->truncate();
        ExerciseRecord::query()->truncate();
        Appointment::query()->truncate();

        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@rehabplus.com',
            'password' => bcrypt('admin123'),
            'role' => 'superadmin',
            'is_active' => true,
        ]);

        $therapist = User::create([
            'name' => 'Dr. Sarah Lee',
            'email' => 'therapist@rehabplus.com',
            'password' => bcrypt('admin123'),
            'role' => 'therapist',
            'is_active' => true,
        ]);

        $patientUser = User::create([
            'name' => 'Emily Johnson',
            'email' => 'patient@rehabplus.com',
            'password' => bcrypt('admin123'),
            'role' => 'patient',
            'is_active' => true,
        ]);

        $patientA = Patient::create([
            'user_id' => $patientUser->id,
            'name' => 'Emily Johnson',
            'condition' => 'Knee Rehabilitation',
            'medical_summary' => 'Post-surgical rehab with mild swelling.',
            'therapy_plan' => 'Mobility and strength progression over 4 weeks.',
        ]);

        $patientB = Patient::create([
            'user_id' => null,
            'name' => 'Marcus Smith',
            'condition' => 'Back Recovery',
            'medical_summary' => 'Lower back discomfort during movement.',
            'therapy_plan' => 'Core activation and posture correction.',
        ]);

        ExerciseRecord::create([
            'patient_id' => $patientA->id,
            'exercise_name' => 'Straight Leg Raise',
            'sets_prescribed' => 3,
            'sets_completed' => 3,
            'pain_level' => 3,
            'notes' => 'Improved range with less discomfort.',
            'recorded_at' => now()->subDay(),
        ]);

        ExerciseRecord::create([
            'patient_id' => $patientB->id,
            'exercise_name' => 'Bird Dog',
            'sets_prescribed' => 4,
            'sets_completed' => 3,
            'pain_level' => 4,
            'notes' => 'Good balance and control.',
            'recorded_at' => now()->subHours(10),
        ]);

        Appointment::create([
            'patient' => $patientA->name,
            'therapist' => $therapist->name,
            'patient_condition' => $patientA->condition,
            'contact' => '555-1201',
            'date' => now()->addDay()->toDateString(),
            'time' => '09:30:00',
            'session' => 'Mobility Session',
            'notes' => 'Focus on knee flexion and gait improvement.',
            'status' => 'Upcoming',
        ]);

        Appointment::create([
            'patient' => $patientB->name,
            'therapist' => $therapist->name,
            'patient_condition' => $patientB->condition,
            'contact' => '555-9840',
            'date' => now()->addDays(2)->toDateString(),
            'time' => '14:00:00',
            'session' => 'Strength Review',
            'notes' => 'Review posture and recovery trend.',
            'status' => 'Upcoming',
        ]);
    }
}
