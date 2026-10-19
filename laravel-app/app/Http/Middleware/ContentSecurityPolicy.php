<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Content-Security-Policy: the browser only runs scripts this server put in
 * the page, so injected markup (XSS) can't execute.
 *
 * Scripts: a fresh random nonce per request ('nonce-...') plus 'self'. Every
 * <script> in the views carries nonce="{{ Vite::cspNonce() }}"; the @vite
 * tags get it automatically. There is deliberately no CDN host allowlist for
 * scripts (anyone can publish a package on jsDelivr); the one CDN script,
 * Chart.js, carries the nonce and a Subresource Integrity hash instead.
 * Inline event handlers (onclick=...) are not allowed; views use data-
 * attributes handled in resources/js/rehabplus-dashboard.js.
 *
 * Styles: 'unsafe-inline' is allowed because the views use style="" for
 * layout. CSS can't run code, so this costs little. (A nonce in style-src
 * would switch 'unsafe-inline' off, so styles get none.)
 *
 * CSP_MODE=enforce (default) | report-only | off. Violations are POSTed to
 * /csp-report and logged as "csp.violation" (see docs/DIAGNOSTICS.md).
 */
class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $mode = config('security.csp_mode', 'enforce');
        if ($mode === 'off') {
            return $next($request);
        }

        // Creates the nonce before the views render, so they can print it.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        if (! $this->isHtml($response)) {
            return $response;
        }

        $header = $mode === 'report-only' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
        $response->headers->set($header, $this->policy($request, $nonce));

        return $response;
    }

    private function policy(Request $request, string $nonce): string
    {
        $devServer = $this->viteDevServer();

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", ...$devServer],
            'style-src' => ["'self'", "'unsafe-inline'", 'https://cdn.jsdelivr.net', 'https://fonts.googleapis.com', ...$devServer],
            'font-src' => ["'self'", 'https://cdn.jsdelivr.net', 'https://fonts.gstatic.com', 'data:'],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'connect-src' => ["'self'", ...$devServer, ...array_map(fn ($url) => preg_replace('/^http/', 'ws', $url), $devServer)],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
            'report-uri' => [route('csp.report', absolute: false)],
        ];

        $policy = implode('; ', array_map(
            fn (string $directive, array $sources) => $directive.' '.implode(' ', $sources),
            array_keys($directives),
            $directives,
        ));

        // Only over HTTPS: on plain-http local dev it would break asset loading.
        return $request->isSecure() ? $policy.'; upgrade-insecure-requests' : $policy;
    }

    /** The `npm run dev` server's origin while it is running, so hot reload keeps working. */
    private function viteDevServer(): array
    {
        if (! Vite::isRunningHot()) {
            return [];
        }

        $url = trim((string) @file_get_contents(public_path('hot')));

        return $url !== '' ? [rtrim($url, '/')] : [];
    }

    private function isHtml(Response $response): bool
    {
        $type = (string) $response->headers->get('Content-Type');

        return $type === '' || str_contains($type, 'text/html');
    }
}
