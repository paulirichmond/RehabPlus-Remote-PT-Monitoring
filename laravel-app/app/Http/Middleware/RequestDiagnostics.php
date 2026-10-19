<?php

namespace App\Http\Middleware;

use App\Services\Firebase\FirebaseMetrics;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Makes every request traceable in the logs:
 *
 *   - A request id (the caller's X-Request-Id if it looks safe, else Vercel's
 *     x-vercel-id, else a new UUID) is put in Laravel's log Context, so EVERY
 *     log line written during the request carries it, and it is returned in
 *     the X-Request-Id response header and shown on error pages.
 *   - One summary line per request ("http.request"): method, route, status,
 *     duration, user uid/role, and the Firebase totals (calls, parallel
 *     batches, retries, failures, wait time).
 *
 * To troubleshoot: take the request id from the error page or response
 * header and filter the logs by it.
 */
class RequestDiagnostics
{
    /** Requests slower than this log the summary as a warning. */
    private const SLOW_MS = 3000;

    public function __construct(private FirebaseMetrics $firebase)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $started = hrtime(true);
        $requestId = $this->requestId($request);

        Context::add('request_id', $requestId);
        $request->attributes->set('request_id', $requestId);

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            // Unhandled exceptions are rendered by the handler outside this
            // middleware; still write the summary so the request is visible.
            $this->summarise($request, null, $started, $e);

            throw $e;
        }

        $response->headers->set('X-Request-Id', $requestId);
        $this->summarise($request, $response, $started);

        return $response;
    }

    private function summarise(Request $request, ?Response $response, int $started, ?Throwable $error = null): void
    {
        $status = $response?->getStatusCode() ?? 500;
        $ms = round((hrtime(true) - $started) / 1e6, 1);
        $user = Auth::hasUser() ? Auth::user() : null; // never trigger a lookup just to log

        $context = [
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'route' => $request->route()?->getName(),
            'status' => $status,
            'ms' => $ms,
            'user_uid' => $user?->getAuthIdentifier(),
            'user_role' => $user->role ?? null,
            'firebase' => $this->firebase->toArray(),
        ];
        if ($error !== null) {
            $context['exception'] = get_class($error);
        }

        match (true) {
            $status >= 500 => Log::error('http.request', $context),
            $ms >= self::SLOW_MS || $status === 429 => Log::warning('http.request', $context),
            default => Log::info('http.request', $context),
        };
    }

    /** Reuse an upstream id only if it can't smuggle anything into the logs. */
    private function requestId(Request $request): string
    {
        foreach ([$request->headers->get('X-Request-Id'), $request->headers->get('x-vercel-id')] as $candidate) {
            if (is_string($candidate) && preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $candidate)) {
                return $candidate;
            }
        }

        return (string) Str::uuid();
    }
}
