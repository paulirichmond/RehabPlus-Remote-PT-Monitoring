# Diagnostics and troubleshooting

How to tell what the dashboard is doing, written for people and AI agents
alike. Everything here is read-only.

## 1. Health check

    php artisan rehab:diagnose          # table
    php artisan rehab:diagnose --json   # one JSON object; exit code 0 = healthy

It checks APP_KEY / APP_DEBUG, the service-account key (and that its project
matches FIREBASE_PROJECT_ID), FIREBASE_API_KEY (staff login), a fresh OAuth
token, a Firestore read, that three reads go out as one parallel batch, a
Firebase Auth admin lookup, the cache, and that no setting still points at the
removed SQL database (`no_sql`). It never writes, sends pushes or
prints secrets. Each check has `status` (ok / warn / fail), `ms` and `detail`.

## 2. Tracing one request

Every response has an `X-Request-Id` header, and the "can't reach its data"
error page shows the same id as "Reference for support". Every log line
written during that request carries `request_id` in its context (and
`user_uid` / `user_role` once the user is signed in). Filter the logs by it.

On Vercel the logs are the function logs (stderr). Locally they are in
storage/logs/laravel.log. `LOG_LEVEL=debug` includes one line per Firebase
call; `info` keeps the per-request summaries, logins and pushes.

## 3. Log events

| Message | Level | Context |
|---|---|---|
| `http.request` | info; warning if slow (>3 s) or 429; error on 5xx | method, path, route, status, ms, user_uid, user_role, firebase {calls, batches, largest_batch, retries, failures, wait_ms} |
| `firebase.call` | debug | op, target, method, status, ms, attempt, batch_size, batch_ms, outcome (ok / not_found) |
| `firebase.call slow` | warning | as above, when one call takes longer than FIREBASE_SLOW_MS |
| `firebase.call retrying` | warning | as above plus firebase_status / firebase_message or error; outcome retry / refresh (401, new token) |
| `firebase.call failed` | error | as above; the final failure after retries |
| `firebase.token fetched` / `fetch failed` / `not configured` | info / error | ms, expires_in |
| `auth.login` | info on success, else warning | outcome (success, invalid_credentials, disabled, throttled_by_firebase, patient_account, not_staff_or_inactive, no_profile, firebase_error), email_hash (sha256, 12 chars), uid, role, ip |
| `auth.logout` | info | uid |
| `push.sent` | info | patient, type, devices, sent, dead, failed |
| `push.skipped ...` / `push.preferences unreadable` / `push.dead_device not deleted` | info / warning | patient, type, error |
| `csp.violation` | warning | directive, blocked (URL without query, or "inline"), page, source, line, disposition (enforce / report) |

Never logged: request or response bodies, passwords, OAuth tokens, the API
key, FCM device tokens (only a 10-character hash), email addresses (only a
hash), message text or clinical data.

## 4. Reading the numbers

- `firebase.largest_batch` > 1 means calls on that page ran in parallel.
  `wait_ms` is wall-clock time spent waiting on Firebase; compare it with the
  request's `ms` to see whether Firebase or PHP is the slow part.
- `retries` > 0 with `failures` = 0: Firebase hiccuped and recovered.
- `failures` > 0: look for the `firebase.call failed` line with the same
  request_id; `firebase_status` is Google's code (PERMISSION_DENIED,
  FAILED_PRECONDITION = missing index, RESOURCE_EXHAUSTED = quota, ...).
- `firebase.token fetched` on many requests means the cache isn't keeping the
  token (each new Vercel instance fetches one once; that's expected).

## 5. Content-Security-Policy

Every HTML response carries a Content-Security-Policy
(app/Http/Middleware/ContentSecurityPolicy.php). Scripts run only if they
carry the request's nonce: views print `nonce="{{ Vite::cspNonce() }}"` on
each `<script>`, and `@vite` adds it by itself. Inline handlers
(`onclick=`, `onsubmit=`) are blocked; use the data- attributes handled in
resources/js/rehabplus-dashboard.js (`data-confirm`, `data-autosubmit`,
`data-print`, `data-show` / `data-hide`). CDN files are pinned with SRI
`integrity` hashes; changing a CDN version means recomputing its hash:

    curl -sL <url> | openssl dgst -sha384 -binary | openssl base64 -A

A `csp.violation` line means the browser blocked something: either an
attack, or a new view that forgot the nonce or used an inline handler. If a
page breaks in production, set `CSP_MODE=report-only` in Vercel and
redeploy; nothing is blocked and violations are still logged.

## 6. How the parallelism works

PHP has no threads on Vercel. `FirebaseTransport::concurrently()` runs each
independent piece of page logic as a PHP Fiber; when one needs Firebase it
pauses, and all paused requests go out together through `Http::pool` (curl
multi). Reads are retried on network errors, 429 and 5xx with exponential
backoff; commits, account creation and pushes are sent exactly once because
repeating them could double-apply an increment or buzz a phone twice.
