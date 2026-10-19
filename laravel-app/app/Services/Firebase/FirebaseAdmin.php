<?php

namespace App\Services\Firebase;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Firebase Auth admin calls and FCM pushes over REST, using the service account
 * (no Admin SDK / grpc needed). Password checks at login use the public Web
 * API key instead, the same way the app signs in.
 *
 * Calls go through FirebaseTransport (retries, parallel batches, logging).
 * Log targets name uids at most; emails, passwords and device tokens are
 * never logged.
 */
class FirebaseAdmin
{
    /** Firebase Auth's own minimum is 6; the dashboard asks for a bit more. */
    public const MIN_PASSWORD = 8;

    /** Firebase Auth's limit for uids in one accounts:lookup. */
    private const LOOKUP_LIMIT = 100;

    public function __construct(private FirebaseTransport $transport, private FirestoreClient $firestore)
    {
    }

    /**
     * Firebase Auth uid for an email, or null if there is no such account.
     * Cached for 10 minutes; emails rarely change.
     */
    public function uidForEmail(string $email): ?string
    {
        $email = strtolower(trim($email));

        return Cache::remember(self::uidCacheKey($email), now()->addMinutes(10), function () use ($email) {
            $response = $this->call(':lookup', ['email' => [$email]], 'auth.lookup', 'by email', idempotent: true);

            if ($response->failed()) {
                throw new FirebaseException('Firebase Auth lookup failed ('.$response->status().').');
            }

            return $response->json('users.0.localId');
        });
    }

    /** Cache key used by uidForEmail(), so callers can forget an entry. */
    public static function uidCacheKey(string $email): string
    {
        return 'firebase.uid.'.sha1(strtolower(trim($email)));
    }

    /**
     * Create an email/password account and return its uid. Not retried: a
     * retry after a lost response would fail with "email already exists".
     *
     * @throws FirebaseException with a message safe to show staff (e.g. email taken)
     */
    public function createUser(string $email, string $password, string $displayName): string
    {
        $response = $this->call('', [
            'email' => strtolower(trim($email)),
            'password' => $password,
            'displayName' => $displayName,
            'emailVerified' => false,
        ], 'auth.create', 'new account', idempotent: false);

        $this->authOk($response, 'create the account');

        return $response->json('localId');
    }

    /**
     * Change an account. Supported keys: password, displayName, disabled.
     * Setting values is safe to repeat, so this is retried on transient errors.
     */
    public function updateUser(string $uid, array $changes): void
    {
        $body = ['localId' => $uid];
        foreach (['password', 'displayName'] as $key) {
            if (isset($changes[$key])) {
                $body[$key] = $changes[$key];
            }
        }
        if (array_key_exists('disabled', $changes)) {
            $body['disableUser'] = (bool) $changes['disabled'];
        }

        $fields = implode(',', array_keys(array_diff_key($body, ['localId' => 1])));
        $this->authOk($this->call(':update', $body, 'auth.update', "uid:{$uid} ({$fields})", idempotent: true), 'update the account');
    }

    /** Delete a login. An account that is already gone counts as deleted. */
    public function deleteUser(string $uid): void
    {
        $response = $this->call(':delete', ['localId' => $uid], 'auth.delete', "uid:{$uid}", idempotent: true);
        if (str_starts_with((string) $response->json('error.message'), 'USER_NOT_FOUND')) {
            return;
        }

        $this->authOk($response, 'delete the account');
    }

    /**
     * Which of these uids still have a Firebase Auth login (e.g. to spot a
     * users/{uid} doc whose login was deleted in the Firebase console).
     * Lookups of more than 100 uids are split and sent in parallel.
     *
     * @param  string[]  $uids
     * @return string[]
     */
    public function existingUids(array $uids): array
    {
        $chunks = array_chunk(array_values($uids), self::LOOKUP_LIMIT);

        $responses = $this->transport->concurrently(array_map(
            fn (array $chunk) => fn () => $this->call(':lookup', ['localId' => $chunk], 'auth.lookup', count($chunk).' uids', idempotent: true),
            $chunks,
        ));

        $found = [];
        foreach ($responses as $response) {
            if ($response->failed()) {
                throw new FirebaseException('Firebase Auth lookup failed ('.$response->status().').');
            }
            array_push($found, ...array_column($response->json('users') ?? [], 'localId'));
        }

        return $found;
    }

    /**
     * Check an email/password with Firebase Auth.
     *
     * @return array{uid: ?string, error: ?string} the uid on success; otherwise
     *                                             error is 'invalid', 'disabled' or 'throttled'
     */
    public function verifyPassword(string $email, string $password): array
    {
        $key = config('services.firebase.api_key');
        if (! $key) {
            throw new FirebaseException('Login is not set up on this server yet: FIREBASE_API_KEY is missing from .env.');
        }

        // The API key is in the URL, which is never logged (only op/target are).
        $response = $this->transport->send(new FirebaseRequest(
            FirebaseRequest::POST,
            'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key='.urlencode($key),
            ['email' => strtolower(trim($email)), 'password' => $password, 'returnSecureToken' => false],
            'auth.signInWithPassword',
            'staff login',
            idempotent: true,
            authenticated: false,
        ));

        if ($response->successful()) {
            return ['uid' => $response->json('localId'), 'error' => null];
        }

        $code = (string) $response->json('error.message');

        return ['uid' => null, 'error' => match (true) {
            str_starts_with($code, 'USER_DISABLED') => 'disabled',
            str_starts_with($code, 'TOO_MANY_ATTEMPTS') => 'throttled',
            in_array(strtok($code, ' :'), ['INVALID_LOGIN_CREDENTIALS', 'EMAIL_NOT_FOUND', 'INVALID_PASSWORD', 'INVALID_EMAIL', 'MISSING_PASSWORD'], true) => 'invalid',
            default => throw new FirebaseException('Firebase login failed ('.$response->status().').'),
        }];
    }

    /**
     * Send one FCM message. Not retried (a retry could buzz the phone twice).
     *
     * @return string 'sent', 'dead' (token no longer valid, delete it) or 'failed'
     */
    public function sendPush(string $deviceToken, string $title, string $body, array $data): string
    {
        try {
            $response = $this->transport->send(new FirebaseRequest(
                FirebaseRequest::POST,
                "https://fcm.googleapis.com/v1/projects/{$this->firestore->projectId()}/messages:send",
                ['message' => [
                    'token' => $deviceToken,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => array_map('strval', $data),
                    'android' => ['priority' => 'high', 'notification' => ['channel_id' => 'chat_messages']],
                ]],
                'fcm.send',
                'device '.substr(hash('sha256', $deviceToken), 0, 10).' ('.($data['type'] ?? 'push').')',
                idempotent: false,
            ));
        } catch (FirebaseException) {
            return 'failed'; // network error; already logged by the transport
        }

        if ($response->successful()) {
            return 'sent';
        }

        $code = $response->json('error.details.0.errorCode') ?? $response->json('error.status');

        return in_array($code, ['UNREGISTERED', 'INVALID_ARGUMENT'], true) ? 'dead' : 'failed';
    }

    /** POST to the project's Firebase Auth accounts endpoint with the service-account token. */
    private function call(string $suffix, array $body, string $operation, string $target, bool $idempotent): Response
    {
        return $this->transport->send(new FirebaseRequest(
            FirebaseRequest::POST,
            "https://identitytoolkit.googleapis.com/v1/projects/{$this->firestore->projectId()}/accounts{$suffix}",
            $body,
            $operation,
            $target,
            $idempotent,
        ));
    }

    /** Turn Firebase Auth error codes into messages staff can act on. */
    private function authOk(Response $response, string $action): void
    {
        if ($response->successful()) {
            return;
        }

        $code = strtok((string) $response->json('error.message'), ' :');

        throw new FirebaseException(match ($code) {
            'EMAIL_EXISTS', 'DUPLICATE_EMAIL' => 'That email address already has an account.',
            'INVALID_EMAIL' => 'That email address is not valid.',
            'WEAK_PASSWORD' => 'The password is too weak (use at least '.self::MIN_PASSWORD.' characters).',
            'USER_NOT_FOUND' => 'That account no longer exists in Firebase.',
            default => "Could not {$action} in Firebase (".$response->status().').',
        });
    }
}
