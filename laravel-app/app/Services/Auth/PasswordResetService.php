<?php

namespace App\Services\Auth;

use App\Auth\StaffUser;
use App\Mail\PasswordResetCode;
use App\Services\Firebase\FirebaseAdmin;
use App\Services\Firebase\FirestoreClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * "Forgot password" for staff: a 6-digit code emailed to the account's address,
 * then a short-lived token that allows one new password.
 *
 * State lives in Firestore because Vercel instances share nothing: the cache is
 * per-instance /tmp and sessions are cookies a client could replay to reset a
 * counter. Documents (ids are sha256 hashes, so no emails or IPs are stored):
 *
 *   passwordResets/{sha256(email)}      uid, codeHash, codeExpiresAt, attempts,
 *                                       lastSentAt, windowStart, sendCount,
 *                                       tokenHash, tokenExpiresAt
 *   passwordResetIps/{sha256(ip)}       windowStart, count
 *
 * Codes and tokens are stored as HMACs keyed with APP_KEY, never in plain text.
 * Nothing here tells the caller whether an email has an account: requestCode()
 * behaves the same either way and verifyCode() fails the same way.
 */
class PasswordResetService
{
    public const CODE_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 60;

    public const MAX_SENDS_PER_HOUR = 5;

    public const MAX_IP_REQUESTS_PER_HOUR = 20;

    public const TOKEN_MINUTES = 10;

    public function __construct(private FirebaseAdmin $admin, private FirestoreClient $db)
    {
    }

    /**
     * Email a code if $email belongs to an active staff account.
     *
     * @return bool false only when this IP has asked too often (the one limit
     *              that is safe to show, since it doesn't depend on the email)
     */
    public function requestCode(string $email, string $ip): bool
    {
        $email = self::normalize($email);

        if (! $this->countIpRequest($ip)) {
            $this->audit('ip_limited', $email);

            return false;
        }

        // Anything that goes wrong past this point is logged, not shown:
        // a different response would reveal that the account exists.
        try {
            $this->sendIfStaff($email);
        } catch (Throwable $e) {
            report($e);
            $this->audit('error', $email);
        }

        return true;
    }

    /** Check a code; on success returns a one-time token for resetPassword(). */
    public function verifyCode(string $email, string $code): ?string
    {
        $email = self::normalize($email);
        $path = self::path($email);
        $doc = $this->db->get($path);
        $now = CarbonImmutable::now();

        if (! $doc || empty($doc['codeHash']) || ! $doc['codeExpiresAt'] || $now->greaterThan($doc['codeExpiresAt'])) {
            $this->audit('code_invalid', $email);

            return null;
        }

        if (! hash_equals($doc['codeHash'], $this->hmac('code', $email, $code))) {
            $attempts = (int) ($doc['attempts'] ?? 0) + 1;

            // Out of tries: drop the code so even the right one no longer works.
            $this->db->commit([$this->db->mergeWrite($path, $attempts >= self::MAX_ATTEMPTS
                ? ['codeHash' => null, 'codeExpiresAt' => null, 'attempts' => $attempts]
                : ['attempts' => $attempts])]);
            $this->audit($attempts >= self::MAX_ATTEMPTS ? 'code_locked' : 'code_wrong', $email);

            return null;
        }

        $token = Str::random(48);
        $this->db->commit([$this->db->mergeWrite($path, [
            'codeHash' => null,
            'codeExpiresAt' => null,
            'attempts' => 0,
            'tokenHash' => $this->hmac('token', $email, $token),
            'tokenExpiresAt' => $now->addMinutes(self::TOKEN_MINUTES),
        ])]);
        $this->audit('code_verified', $email);

        return $token;
    }

    /** Set the new password if the token from verifyCode() is still valid. */
    public function resetPassword(string $email, string $token, string $password): bool
    {
        $email = self::normalize($email);
        $path = self::path($email);
        $doc = $this->db->get($path);

        if (! $doc || empty($doc['tokenHash']) || ! $doc['tokenExpiresAt']
            || CarbonImmutable::now()->greaterThan($doc['tokenExpiresAt'])
            || ! hash_equals($doc['tokenHash'], $this->hmac('token', $email, $token))) {
            $this->audit('token_invalid', $email);

            return false;
        }

        $this->admin->updateUser($doc['uid'], ['password' => $password]);
        $this->db->delete($path);
        $this->audit('password_changed', $email, $doc['uid']);

        return true;
    }

    public static function normalize(string $email): string
    {
        return strtolower(trim($email));
    }

    private function sendIfStaff(string $email): void
    {
        $uid = $this->admin->uidForEmail($email);
        $user = $uid ? StaffUser::fromDocument($this->db->get("users/{$uid}")) : null;
        if (! $user) {
            $this->audit('not_staff', $email);

            return;
        }

        $path = self::path($email);
        $doc = $this->db->get($path) ?? [];
        $now = CarbonImmutable::now();

        if (isset($doc['lastSentAt']) && $doc['lastSentAt']->addSeconds(self::RESEND_SECONDS)->greaterThan($now)) {
            $this->audit('cooldown', $email, $uid);

            return;
        }

        $windowOpen = isset($doc['windowStart']) && $doc['windowStart']->addHour()->greaterThan($now);
        $sendCount = $windowOpen ? (int) ($doc['sendCount'] ?? 0) : 0;
        if ($sendCount >= self::MAX_SENDS_PER_HOUR) {
            $this->audit('send_limited', $email, $uid);

            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // A new code replaces the old one and any unused token.
        $this->db->commit([$this->db->mergeWrite($path, [
            'uid' => $uid,
            'codeHash' => $this->hmac('code', $email, $code),
            'codeExpiresAt' => $now->addMinutes(self::CODE_MINUTES),
            'attempts' => 0,
            'lastSentAt' => $now,
            'windowStart' => $windowOpen ? $doc['windowStart'] : $now,
            'sendCount' => $sendCount + 1,
            'tokenHash' => null,
            'tokenExpiresAt' => null,
        ])]);

        Mail::to($email)->send(new PasswordResetCode($code, $user->name, self::CODE_MINUTES));
        $this->audit('code_sent', $email, $uid);
    }

    /** Count one code request from $ip; false once it is over the hourly limit. */
    private function countIpRequest(string $ip): bool
    {
        $path = 'passwordResetIps/'.hash('sha256', $ip);
        $doc = $this->db->get($path);
        $now = CarbonImmutable::now();

        $windowOpen = isset($doc['windowStart']) && $doc['windowStart']->addHour()->greaterThan($now);
        $count = $windowOpen ? (int) ($doc['count'] ?? 0) : 0;
        if ($count >= self::MAX_IP_REQUESTS_PER_HOUR) {
            return false;
        }

        $this->db->commit([$this->db->mergeWrite($path, [
            'windowStart' => $windowOpen ? $doc['windowStart'] : $now,
            'count' => $count + 1,
        ])]);

        return true;
    }

    private static function path(string $email): string
    {
        return 'passwordResets/'.hash('sha256', $email);
    }

    /** Keyed hash bound to the email, so a stored value is useless without APP_KEY. */
    private function hmac(string $purpose, string $email, string $value): string
    {
        return hash_hmac('sha256', "{$purpose}|{$email}|{$value}", (string) config('app.key'));
    }

    /** Same shape as the auth.login lines: hashed email, never the address itself. */
    private function audit(string $outcome, string $email, ?string $uid = null): void
    {
        Log::info('auth.password_reset', array_filter([
            'outcome' => $outcome,
            'email_hash' => substr(hash('sha256', $email), 0, 12),
            'uid' => $uid,
            'ip' => request()->ip(),
        ]));
    }
}
