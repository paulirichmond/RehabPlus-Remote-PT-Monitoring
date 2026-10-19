<?php

/*
 * Vercel entry point (vercel-php runtime, see vercel.json). Every request
 * that isn't a static file in public/ lands here.
 *
 * Vercel is serverless: only /tmp is writable, nothing on disk is shared
 * between requests, and the site is always served over HTTPS. So Laravel's
 * writable paths move to /tmp and sessions live in the (encrypted) cookie.
 * Anything set in the Vercel project's Environment Variables wins over these.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$defaults = [
    'APP_CONFIG_CACHE' => '/tmp/laravel/config.php',
    'APP_EVENTS_CACHE' => '/tmp/laravel/events.php',
    'APP_PACKAGES_CACHE' => '/tmp/laravel/packages.php',
    'APP_ROUTES_CACHE' => '/tmp/laravel/routes.php',
    'APP_SERVICES_CACHE' => '/tmp/laravel/services.php',
    'LOG_CHANNEL' => 'stderr',
    'SESSION_DRIVER' => 'cookie',
    'CACHE_STORE' => 'file',
    // Vercel's edge sets X-Forwarded-For to the real client (see bootstrap/app.php).
    'TRUSTED_PROXIES' => '*',
];

foreach ($defaults as $name => $value) {
    if (getenv($name) === false && ! isset($_ENV[$name]) && ! isset($_SERVER[$name])) {
        putenv("{$name}={$value}");
        $_ENV[$name] = $_SERVER[$name] = $value;
    }
}

$storage = '/tmp/storage';
foreach (['framework/cache/data', 'framework/views', 'framework/sessions', 'logs'] as $dir) {
    if (! is_dir("{$storage}/{$dir}")) {
        mkdir("{$storage}/{$dir}", 0755, true);
    }
}
if (! is_dir('/tmp/laravel')) {
    mkdir('/tmp/laravel', 0755, true);
}

// Vercel terminates TLS in front of PHP; make URLs and secure cookies https.
$_SERVER['HTTPS'] = 'on';

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->useStoragePath($storage);

$app->handleRequest(Request::capture());
