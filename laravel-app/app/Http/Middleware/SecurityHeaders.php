<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser hardening headers for every response.
 *
 * No Content-Security-Policy yet: the views use inline scripts and CDN
 * assets, so a strict CSP needs nonces first. HSTS is sent by Vercel.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // Signed-in pages show patient data: keep them out of shared caches
        // and the browser's back/forward cache after logout.
        if (Auth::hasUser()) {
            $headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
