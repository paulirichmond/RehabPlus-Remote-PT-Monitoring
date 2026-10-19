<?php

namespace Tests\Unit;

use App\Services\Firebase\GoogleAccessToken;
use Tests\TestCase;

class GoogleAccessTokenTest extends TestCase
{
    private const FAKE_KEY = [
        'type' => 'service_account',
        'client_email' => 'dashboard@example.iam.gserviceaccount.com',
        'private_key' => "-----BEGIN PRIVATE KEY-----\nnot-a-real-key\n-----END PRIVATE KEY-----\n",
    ];

    public function test_not_configured_without_any_credentials(): void
    {
        config(['services.firebase.credentials' => null, 'services.firebase.credentials_json' => null]);

        $this->assertFalse(GoogleAccessToken::isConfigured());
    }

    public function test_raw_json_credentials_count_as_configured(): void
    {
        config(['services.firebase.credentials' => null, 'services.firebase.credentials_json' => json_encode(self::FAKE_KEY)]);

        $this->assertTrue(GoogleAccessToken::isConfigured());
    }

    public function test_base64_json_credentials_count_as_configured(): void
    {
        config(['services.firebase.credentials' => null, 'services.firebase.credentials_json' => base64_encode(json_encode(self::FAKE_KEY))]);

        $this->assertTrue(GoogleAccessToken::isConfigured());
    }

    public function test_json_that_is_not_a_service_account_is_rejected(): void
    {
        config(['services.firebase.credentials' => null, 'services.firebase.credentials_json' => '{"hello":"world"}']);

        $this->assertFalse(GoogleAccessToken::isConfigured());
    }

    public function test_file_path_still_works(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sa');
        file_put_contents($path, json_encode(self::FAKE_KEY));
        config(['services.firebase.credentials' => $path, 'services.firebase.credentials_json' => null]);

        try {
            $this->assertTrue(GoogleAccessToken::isConfigured());
        } finally {
            unlink($path);
        }
    }
}
