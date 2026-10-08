<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_log_in(): void
    {
        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@rehabplus.com',
            'password' => bcrypt('admin123'),
            'role' => 'superadmin',
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@rehabplus.com',
            'password' => 'admin123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }
}
