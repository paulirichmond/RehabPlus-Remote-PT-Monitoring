<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_page_loads(): void
    {
        $this->actingAs(
            \App\Models\User::factory()->create([
                'role' => 'superadmin',
                'is_active' => true,
            ])
        );

        $response = $this->get('/analytics');

        $response->assertOk();
        $response->assertSee('Recovery Trends');
    }
}
