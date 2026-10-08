<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_therapist_role_cannot_be_changed_during_update(): void
    {
        $this->actingAs(
            User::factory()->create([
                'role' => 'superadmin',
                'is_active' => true,
            ])
        );

        $therapist = User::factory()->create([
            'name' => 'Jane Therapist',
            'email' => 'jane.therapist@example.com',
            'role' => 'therapist',
            'is_active' => true,
        ]);

        $this->from('/users/' . $therapist->id . '/edit')
            ->put('/users/' . $therapist->id, [
                'name' => 'Jane Therapist',
                'email' => 'jane.therapist.updated@example.com',
                'role' => 'staff',
            ]);

        $therapist->refresh();

        $this->assertSame('therapist', $therapist->role);
    }
}
