<?php

namespace App\Services\Firebase;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * OAuth access token for the Firebase service account.
 *
 * The key comes from FIREBASE_CREDENTIALS_JSON (the JSON itself, raw or
 * base64; for hosts without a disk such as Vercel) or else from the file at
 * FIREBASE_CREDENTIALS.
 *
 * One token covers Firestore, Firebase Auth admin calls and FCM. It is kept
 * in memory for the request and in the cache until two minutes before Google
 * says it expires, so most requests never call Google's token endpoint.
 * FirebaseTransport calls forget() when Google rejects a token (401).
 */
class GoogleAccessToken
{
    private const CACHE_KEY = 'firebase.access_token.v2';

    /** Renew this long before Google's expiry, so a token never dies mid-request. */
    private const EXPIRY_MARGIN_SECONDS = 120;

    private const SCOPES = [
        'https://www.googleapis.com/auth/cloud-platform',
        'https://www.googleapis.com/auth/firebase.messaging',
    ];

    private ?string $token = null;

    private int $expiresAt = 0;

    public function get(): string
    {
        if ($this->token !== null && time() < $this->expiresAt) {
            return $this->token;
        }

        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached) && isset($cached['token'], $cached['expires_at']) && time() < $cached['expires_at']) {
            return $this->remember($cached['token'], $cached['expires_at']);
        }

        [$token, $expiresAt] = $this->fetch();
        Cache::put(self::CACHE_KEY, ['token' => $token, 'expires_at' => $expiresAt], $expiresAt - time());

        return $this->remember($token, $expiresAt);
    }

    /** Drop the token everywhere, so the next get() fetches a new one. */
    public function forget(): void
    {
        $this->token = null;
        $this->expiresAt = 0;
        Cache::forget(self::CACHE_KEY);
    }

    public static function isConfigured(): bool
    {
        return self::serviceAccountKey() !== null;
    }

    /**
     * Non-secret facts about the configured key, for `php artisan rehab:diagnose`.
     *
     * @return array{configured: bool, source: ?string, project_id: ?string, client_email_domain: ?string}
     */
    public static function describeKey(): array
    {
        $key = self::serviceAccountKey();
        if ($key === null) {
            return ['configured' => false, 'source' => null, 'project_id' => null, 'client_email_domain' => null];
        }

        $source = is_array($key) ? 'FIREBASE_CREDENTIALS_JSON' : 'FIREBASE_CREDENTIALS file';
        $data = is_array($key) ? $key : json_decode((string) @file_get_contents($key), true);
        $email = is_array($data) ? (string) ($data['client_email'] ?? '') : '';

        return [
            'configured' => true,
            'source' => $source,
            'project_id' => is_array($data) ? ($data['project_id'] ?? null) : null,
            'client_email_domain' => $email !== '' ? substr($email, strpos($email, '@') + 1) : null,
        ];
    }

    /** @return array{0: string, 1: int} the token and when to stop using it (unix time) */
    private function fetch(): array
    {
        $key = self::serviceAccountKey();
        if ($key === null) {
            Log::error('firebase.token not configured');

            throw new FirebaseException('Firebase is not configured. Set FIREBASE_CREDENTIALS (path to the service-account JSON) or FIREBASE_CREDENTIALS_JSON.');
        }

        $started = hrtime(true);

        try {
            $result = (new ServiceAccountCredentials(self::SCOPES, $key))->fetchAuthToken();
        } catch (Throwable $e) {
            Log::error('firebase.token fetch failed', ['error' => get_class($e).': '.mb_substr($e->getMessage(), 0, 200)]);

            throw new FirebaseException('Could not get a Firebase access token (network error or invalid service account).', 0, $e);
        }

        if (empty($result['access_token'])) {
            Log::error('firebase.token fetch returned no token');

            throw new FirebaseException('Could not get a Firebase access token from the service account.');
        }

        $lifetime = (int) ($result['expires_in'] ?? 3600);
        Log::info('firebase.token fetched', [
            'ms' => round((hrtime(true) - $started) / 1e6, 1),
            'expires_in' => $lifetime,
        ]);

        return [$result['access_token'], time() + max(60, $lifetime - self::EXPIRY_MARGIN_SECONDS)];
    }

    private function remember(string $token, int $expiresAt): string
    {
        $this->token = $token;
        $this->expiresAt = $expiresAt;

        return $token;
    }

    /**
     * The decoded key from FIREBASE_CREDENTIALS_JSON, else the readable file
     * path from FIREBASE_CREDENTIALS, else null.
     */
    private static function serviceAccountKey(): array|string|null
    {
        $json = trim((string) config('services.firebase.credentials_json'));
        if ($json !== '') {
            $key = json_decode($json, true);
            if (! is_array($key)) {
                $key = json_decode((string) base64_decode($json, true), true);
            }

            return is_array($key) && isset($key['private_key'], $key['client_email']) ? $key : null;
        }

        $path = config('services.firebase.credentials');

        return $path && is_readable($path) ? $path : null;
    }
}
