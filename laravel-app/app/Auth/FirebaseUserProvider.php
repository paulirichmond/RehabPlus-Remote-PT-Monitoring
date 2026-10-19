<?php

namespace App\Auth;

use App\Services\Firebase\FirebaseAdmin;
use App\Services\Firebase\FirestoreClient;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Cache;

/**
 * Laravel user provider backed by Firebase: the session stores the Firebase
 * uid, and each request loads users/{uid} from Firestore (cached briefly so
 * every page doesn't cost a Firestore read). Disabling an account or changing
 * its role takes effect within CACHE_SECONDS, or at once via forget().
 */
class FirebaseUserProvider implements UserProvider
{
    public const CACHE_SECONDS = 120;

    public function __construct(private FirestoreClient $db, private FirebaseAdmin $admin)
    {
    }

    public static function forget(string $uid): void
    {
        Cache::forget(self::cacheKey($uid));
    }

    public function retrieveById($identifier): ?Authenticatable
    {
        $uid = (string) $identifier;
        if (! preg_match('/^[A-Za-z0-9_-]{1,128}$/', $uid)) {
            return null;
        }

        $doc = Cache::remember(self::cacheKey($uid), self::CACHE_SECONDS, fn () => $this->db->get("users/{$uid}") ?? false);

        return StaffUser::fromDocument($doc ?: null);
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return null; // no "remember me"
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $uid = isset($credentials['email']) ? $this->admin->uidForEmail($credentials['email']) : null;

        return $uid ? $this->retrieveById($uid) : null;
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $result = $this->admin->verifyPassword($credentials['email'] ?? '', $credentials['password'] ?? '');

        return $result['uid'] !== null && $result['uid'] === $user->getAuthIdentifier();
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
    }

    private static function cacheKey(string $uid): string
    {
        return 'staff-user.'.$uid;
    }
}
