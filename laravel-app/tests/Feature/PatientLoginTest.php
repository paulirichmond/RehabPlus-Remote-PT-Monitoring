<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_log_in(): void
    {
        User::create([
            'name' => 'Emily Johnson',
            'email' => 'patient@rehabplus.com',
            'password' => bcrypt('admin123'),
            'role' => 'patient',
            'is_active' => true,
        ]);

        $response = $this->post('/patient-login', [
            'email' => 'patient@rehabplus.com',
            'password' => 'admin123',
        ]);

        $response->assertRedirect('/patient-portal');
        $this->assertAuthenticated();
    }
}
