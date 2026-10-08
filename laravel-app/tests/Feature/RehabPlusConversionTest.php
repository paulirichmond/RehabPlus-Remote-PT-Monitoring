<?php

namespace Tests\Feature;

use Tests\TestCase;

class RehabPlusConversionTest extends TestCase
{
    public function test_login_page_loads(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Login');
    }
}
