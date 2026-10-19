<?php

use App\Http\Middleware\ContentSecurityPolicy;
use App\Http\Middleware\EnsureAuthenticated;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\RequestDiagnostics;
use App\Http\Middleware\SecurityHeaders;
use App\Services\Firebase\FirebaseException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // First in, last out: the request id exists before anything logs, and
        // the summary line sees the final status.
        $middleware->prepend(RequestDiagnostics::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->append(ContentSecurityPolicy::class);

        // Browsers send CSP reports without a CSRF token.
        $middleware->validateCsrfTokens(except: ['csp-report']);

        // On Vercel every request arrives through Vercel's proxy, which sets
        // X-Forwarded-For to the real client. Without trusting it, the login
        // throttle (per IP) would be shared by all users and logs would show
        // the proxy's address. api/index.php (Vercel only) defaults
        // TRUSTED_PROXIES to '*'; elsewhere set it to your proxy's address.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES') ?: null);

        $middleware->alias([
            'auth.rehab' => EnsureAuthenticated::class,
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Firebase is the data store, so an outage or a rejected write (e.g. an
        // email that already has an account) shouldn't be a 500. Forms go back
        // with the message; pages show a "try again" screen. Still reported.
        $exceptions->render(function (FirebaseException $e, Request $request) {
            if (! $request->isMethod('GET') && ! $request->expectsJson()) {
                return back()
                    ->withInput($request->except(['password', 'password_confirmation']))
                    ->with('error', $e->getMessage());
            }

            return response()->view('errors.firebase', [
                'message' => $e->getMessage(),
                'requestId' => $request->attributes->get('request_id'),
            ], 503);
        });
    })->create();
