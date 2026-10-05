<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientStatisticsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_patient_statistics_page(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => 'superadmin',
            'is_active' => true,
        ]));

        Patient::query()->insert([
            'name' => 'Jane Doe',
            'condition' => 'ACL',
            'assigned_to' => 'Dr. Smith',
            'user_id' => null,
            'created_at' => now()->subMonth(),
            'updated_at' => now(),
        ]);

        $response = $this->get('/patient-statistics');

        $response->assertOk();
        $response->assertSee('Patient Statistics');
    }

    public function test_non_super_admin_cannot_view_patient_statistics_page(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
        ]));

        $response = $this->get('/patient-statistics');

        $response->assertStatus(403);
    }
}
