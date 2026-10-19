<?php

namespace App\Services\Firebase;

/**
 * One HTTP call to a Google/Firebase API, described before it is sent so the
 * transport can retry it, log it and send it in parallel with others.
 *
 * $operation and $target exist for the logs only. They must never contain
 * secrets: no tokens, passwords, API keys, device tokens or email addresses.
 * Good targets are Firestore paths ("patients/abc/sessions") or "uid:abc".
 */
final class FirebaseRequest
{
    public const GET = 'GET';

    public const POST = 'POST';

    public const DELETE = 'DELETE';

    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly ?array $body,
        /** What kind of call this is, e.g. "firestore.runQuery" or "auth.lookup". */
        public readonly string $operation,
        /** What it touches, e.g. "patients/abc/sessions". */
        public readonly string $target,
        /**
         * Safe to send twice. Reads, deletes and "set to this value" updates
         * are; commits with increments or create-preconditions and pushes are
         * not, so those are never retried after a server error.
         */
        public readonly bool $idempotent,
        /** Send the service-account OAuth token (false for API-key calls). */
        public readonly bool $authenticated = true,
    ) {
    }
}
