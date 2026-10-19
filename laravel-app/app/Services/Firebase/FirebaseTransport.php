<?php

namespace App\Services\Firebase;

use Fiber;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LogicException;
use Throwable;
use WeakMap;

/**
 * Every HTTP call to Firebase (Firestore, Firebase Auth, FCM) goes through
 * here. It adds:
 *
 *   - Parallelism. concurrently() runs independent pieces of page logic as
 *     PHP Fibers. When one of them needs Firebase it pauses, and the requests
 *     of all paused tasks are sent together with Http::pool (curl multi), so
 *     a page that needs five reads waits for one round trip instead of five.
 *     Code inside the tasks stays ordinary sequential code.
 *   - Retries. Transient failures (network errors, 429, 5xx) are retried with
 *     exponential backoff and jitter, but only for idempotent requests. A 401
 *     (token revoked or expired early) gets a fresh token and one more try.
 *   - Diagnostics. One structured log line per call (operation, target,
 *     status, duration, attempt; never bodies, tokens or emails), counters in
 *     FirebaseMetrics for the per-request summary, and a warning for slow calls.
 *
 * PHP runs one request per process and Vercel has no threads, so this is
 * I/O concurrency rather than multithreading. All the waiting in this app is
 * on Firebase, so that is where the time goes.
 */
class FirebaseTransport
{
    private const TRANSIENT_STATUSES = [429, 500, 502, 503, 504];

    /** Fibers started by concurrently(); only these may pause to share a batch. */
    private WeakMap $taskFibers;

    public function __construct(
        private HttpFactory $http,
        private GoogleAccessToken $token,
        private FirebaseMetrics $metrics,
    ) {
        $this->taskFibers = new WeakMap;
    }

    /**
     * Run independent tasks at the same time and return their results, keyed
     * like $tasks and in the same order.
     *
     *   [$sessions, $notes] = $transport->concurrently([
     *       fn () => $progress->sessions($uid),
     *       fn () => $records->list('notes', [$patient]),
     *   ]);
     *
     * Every task runs to completion even if another one fails; then the first
     * failure (in task order) is rethrown, so nothing is left half-finished.
     * Nested calls work but their inner tasks block the outer batch.
     *
     * @param  array<array-key, callable(): mixed>  $tasks
     * @return array<array-key, mixed>
     */
    public function concurrently(array $tasks): array
    {
        if (count($tasks) <= 1) {
            return array_map(fn (callable $task) => $task(), $tasks);
        }

        $fibers = [];
        $waiting = [];   // task key => FirebaseRequest it is paused on
        $results = [];
        $errors = [];

        foreach ($tasks as $key => $task) {
            $fiber = new Fiber($task);
            $this->taskFibers[$fiber] = true;
            $fibers[$key] = $fiber;
            $this->step($key, $fiber, fn () => $fiber->start(), $waiting, $results, $errors);
        }

        while ($waiting !== []) {
            $outcomes = $this->sendMany($waiting);
            $resumed = $waiting;
            $waiting = [];

            foreach (array_keys($resumed) as $key) {
                $fiber = $fibers[$key];
                $this->step($key, $fiber, fn () => $fiber->resume($outcomes[$key]), $waiting, $results, $errors);
            }
        }

        foreach (array_keys($tasks) as $key) {
            if (isset($errors[$key])) {
                throw $errors[$key];
            }
        }

        return array_replace(array_fill_keys(array_keys($tasks), null), $results);
    }

    /**
     * Send one request. Inside a concurrently() task it waits for the shared
     * batch; otherwise it is sent straight away.
     *
     * HTTP error statuses come back as the Response (callers decide whether a
     * 404 is fine). Network failures throw FirebaseException.
     */
    public function send(FirebaseRequest $request): Response
    {
        $current = Fiber::getCurrent();

        $outcome = $current !== null && isset($this->taskFibers[$current])
            ? Fiber::suspend($request)
            : $this->sendMany(['request' => $request])['request'];

        if ($outcome instanceof Throwable) {
            throw $outcome;
        }

        return $outcome;
    }

    /**
     * Send requests in parallel (at most `max_parallel` at a time), retrying
     * transient failures. Never throws for a single failed request: each key
     * maps to its Response or to the FirebaseException that ended it.
     *
     * @param  array<array-key, FirebaseRequest>  $requests
     * @return array<array-key, Response|FirebaseException>
     */
    public function sendMany(array $requests): array
    {
        $maxAttempts = max(1, (int) config('services.firebase.max_attempts', 3));
        $outcomes = [];
        $pending = $requests;
        $tokenRefreshed = false;

        for ($attempt = 1; $pending !== []; $attempt++) {
            $started = hrtime(true);
            $raw = $this->sendBatch($pending);
            $elapsedMs = (hrtime(true) - $started) / 1e6;

            $this->metrics->batches++;
            $this->metrics->calls += count($pending);
            $this->metrics->largestBatch = max($this->metrics->largestBatch, count($pending));
            $this->metrics->waitMs += $elapsedMs;

            $retry = [];
            $refreshToken = false;
            foreach ($pending as $key => $request) {
                $result = $raw[$key] ?? new FirebaseException('No response from Firebase.');
                $decision = $this->decide($request, $result, $attempt, $maxAttempts, $tokenRefreshed);

                $this->logCall($request, $result, $attempt, $elapsedMs, count($pending), $decision);

                if ($decision === 'retry' || $decision === 'refresh') {
                    $retry[$key] = $request;
                    $refreshToken = $refreshToken || $decision === 'refresh';
                    $this->metrics->retries++;

                    continue;
                }

                $outcomes[$key] = $this->finalOutcome($request, $result);
            }

            if ($retry === []) {
                break;
            }

            if ($refreshToken) {
                $this->token->forget();
                $tokenRefreshed = true;
            } else {
                usleep($this->backoffMicroseconds($attempt, $raw, array_keys($retry)));
            }

            $pending = $retry;
        }

        // Keep the caller's key order.
        return array_replace(array_intersect_key($requests, $outcomes), $outcomes);
    }

    // ---- internals --------------------------------------------------------

    /**
     * Start or resume a task fiber and record where it ended up: paused on a
     * request, finished with a result, or failed.
     */
    private function step(int|string $key, Fiber $fiber, callable $advance, array &$waiting, array &$results, array &$errors): void
    {
        try {
            $paused = $advance();
        } catch (Throwable $e) {
            $errors[$key] = $e;

            return;
        }

        if ($fiber->isTerminated()) {
            $results[$key] = $fiber->getReturn();

            return;
        }

        if (! $paused instanceof FirebaseRequest) {
            $errors[$key] = new LogicException('A concurrently() task paused without a Firebase request.');

            return;
        }

        $waiting[$key] = $paused;
    }

    /**
     * Send one parallel batch. Connection failures come back as exceptions
     * in the result array instead of being thrown.
     *
     * @return array<array-key, Response|Throwable>
     */
    private function sendBatch(array $requests): array
    {
        try {
            $token = $this->needsToken($requests) ? $this->token->get() : null;
        } catch (FirebaseException $e) {
            return array_map(fn () => $e, $requests);
        }

        $concurrency = max(1, (int) config('services.firebase.max_parallel', 10));

        try {
            return $this->http->pool(function (Pool $pool) use ($requests, $token) {
                foreach ($requests as $key => $request) {
                    $this->dispatch($this->configure($pool->as((string) $key), $request, $token), $request);
                }
            }, $concurrency);
        } catch (Throwable $e) {
            // e.g. a malformed URL; report it per request like any other failure.
            return array_map(fn () => $e, $requests);
        }
    }

    private function configure(PendingRequest $pending, FirebaseRequest $request, ?string $token): PendingRequest
    {
        $pending = $pending
            ->acceptJson()
            ->connectTimeout((int) config('services.firebase.connect_timeout', 5))
            ->timeout((int) config('services.firebase.timeout', 15));

        return $request->authenticated && $token !== null ? $pending->withToken($token) : $pending;
    }

    private function dispatch(PendingRequest $pending, FirebaseRequest $request): mixed
    {
        return match ($request->method) {
            FirebaseRequest::GET => $pending->get($request->url),
            FirebaseRequest::DELETE => $pending->delete($request->url),
            default => $pending->post($request->url, $request->body ?? []),
        };
    }

    /**
     * What to do with a result: 'ok', 'not_found', 'retry' (transient, try
     * again after a pause), 'refresh' (401: new token, try again) or 'fail'.
     */
    private function decide(FirebaseRequest $request, Response|Throwable|null $result, int $attempt, int $maxAttempts, bool $tokenRefreshed): string
    {
        $canRetry = $attempt < $maxAttempts;

        if ($result instanceof Throwable || $result === null) {
            return $canRetry && $request->idempotent ? 'retry' : 'fail';
        }

        $status = $result->status();

        if ($status === 401 && $request->authenticated && ! $tokenRefreshed && $canRetry) {
            return 'refresh'; // rejected before it was processed, so safe for any request
        }
        if (in_array($status, self::TRANSIENT_STATUSES, true)) {
            return $canRetry && $request->idempotent ? 'retry' : 'fail';
        }
        if ($status === 404) {
            return 'not_found';
        }

        return $result->successful() ? 'ok' : 'fail';
    }

    private function finalOutcome(FirebaseRequest $request, Response|Throwable $result): Response|FirebaseException
    {
        if ($result instanceof Response) {
            if ($result->failed() && $result->status() !== 404) {
                $this->metrics->failures++;
            }

            return $result;
        }

        $this->metrics->failures++;

        return $result instanceof FirebaseException
            ? $result
            : new FirebaseException('Could not reach Firebase (network error).', 0, $result);
    }

    /** Exponential backoff with jitter; honours a short Retry-After from Google. */
    private function backoffMicroseconds(int $attempt, array $raw, array $retryKeys): int
    {
        $baseMs = (int) config('services.firebase.retry_backoff_ms', 150);
        if ($baseMs <= 0) {
            return 0;
        }

        $retryAfterMs = 0;
        foreach ($retryKeys as $key) {
            $header = ($raw[$key] ?? null) instanceof Response ? $raw[$key]->header('Retry-After') : '';
            if (is_numeric($header)) {
                $retryAfterMs = max($retryAfterMs, (int) $header * 1000);
            }
        }

        $delayMs = max($baseMs * (2 ** ($attempt - 1)) + random_int(0, $baseMs), min($retryAfterMs, 2000));

        return $delayMs * 1000;
    }

    private function needsToken(array $requests): bool
    {
        foreach ($requests as $request) {
            if ($request->authenticated) {
                return true;
            }
        }

        return false;
    }

    /**
     * One structured line per call. Successful calls log at debug, retries
     * and slow calls at warning, final failures at error. Firebase's own error
     * code/message is included for failures; request bodies never are.
     */
    private function logCall(FirebaseRequest $request, Response|Throwable $result, int $attempt, float $batchMs, int $batchSize, string $decision): void
    {
        $context = [
            'op' => $request->operation,
            'target' => $request->target,
            'method' => $request->method,
            'outcome' => $decision,
            'attempt' => $attempt,
            'batch_size' => $batchSize,
            'batch_ms' => round($batchMs, 1),
        ];

        if ($result instanceof Response) {
            $context['status'] = $result->status();
            $context['ms'] = $this->transferMs($result) ?? round($batchMs, 1);

            if ($result->failed() && $result->status() !== 404) {
                $context['firebase_status'] = $result->json('error.status') ?? $result->json('0.error.status');
                $context['firebase_message'] = Str::limit((string) ($result->json('error.message') ?? $result->json('0.error.message') ?? ''), 200);
            }
        } else {
            $context['status'] = null;
            $context['error'] = Str::limit(get_class($result).': '.$result->getMessage(), 200);
        }

        $slow = ($context['ms'] ?? 0) >= (int) config('services.firebase.slow_ms', 1500);

        match (true) {
            $decision === 'fail' => Log::error('firebase.call failed', $context),
            $decision === 'retry' || $decision === 'refresh' => Log::warning('firebase.call retrying', $context),
            $slow => Log::warning('firebase.call slow', $context),
            default => Log::debug('firebase.call', $context),
        };
    }

    /** Per-call time from curl's transfer stats, when available. */
    private function transferMs(Response $response): ?float
    {
        $seconds = $response->transferStats?->getTransferTime();

        return $seconds === null ? null : round($seconds * 1000, 1);
    }
}
