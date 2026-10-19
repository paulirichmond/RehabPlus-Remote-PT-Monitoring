<?php

namespace App\Console\Commands;

use App\Services\Firebase\FirebaseAdmin;
use App\Services\Firebase\FirebaseMetrics;
use App\Services\Firebase\FirestoreClient;
use App\Services\Firebase\GoogleAccessToken;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Read-only health check of the dashboard's configuration and its Firebase
 * connection. Safe to run against production: it reads a handful of
 * documents and never writes, sends or prints secrets.
 *
 *   php artisan rehab:diagnose          human-readable table
 *   php artisan rehab:diagnose --json   one JSON object, for scripts and AI agents
 *
 * Exit code 0 when every check passes (warnings allowed), 1 otherwise.
 */
class Diagnose extends Command
{
    protected $signature = 'rehab:diagnose {--json : Print one JSON object instead of a table}';

    protected $description = 'Check configuration and the Firebase connection (read-only)';

    /** @var array<int, array{check: string, status: string, ms: ?float, detail: string}> */
    private array $results = [];

    public function handle(GoogleAccessToken $token, FirestoreClient $db, FirebaseAdmin $admin, FirebaseMetrics $metrics): int
    {
        $this->checkAppConfig();
        $key = $this->checkCredentials();

        if ($key['configured']) {
            $this->probe('firebase.token', function () use ($token) {
                $token->forget(); // prove a fresh token can be fetched
                $token->get();

                return 'service-account token fetched';
            });

            $this->probe('firestore.read', function () use ($db) {
                $docs = $db->query('', 'users', limit: 1);

                return 'users collection readable ('.count($docs).' doc sampled)';
            });

            $this->probe('firestore.parallel', function () use ($db, $metrics) {
                $batchesBefore = $metrics->batches;
                $db->concurrently([
                    fn () => $db->query('', 'users', limit: 1),
                    fn () => $db->query('', 'patients', limit: 1),
                    fn () => $db->query('', 'appointments', limit: 1),
                ]);
                $batches = $metrics->batches - $batchesBefore;

                if ($batches !== 1) {
                    return ['warn', "3 reads took {$batches} batches (expected 1)"];
                }

                return '3 reads sent as 1 parallel batch';
            });

            $this->probe('auth.admin', function () use ($admin) {
                $found = $admin->existingUids(['rehab-diagnose-'.Str::lower(Str::random(8))]);

                return $found === []
                    ? 'Firebase Auth admin lookup works'
                    : ['fail', 'lookup of a random uid unexpectedly found an account'];
            });
        }

        $this->probe('cache.roundtrip', function () {
            $key = 'rehab-diagnose-'.Str::random(8);
            Cache::put($key, 'ok', 30);
            $value = Cache::get($key);
            Cache::forget($key);

            return $value === 'ok'
                ? 'cache store "'.config('cache.default').'" works'
                : ['fail', 'value written to the cache could not be read back'];
        });

        return $this->report($metrics);
    }

    private function checkAppConfig(): void
    {
        $production = app()->environment('production');

        $this->record('app.key', config('app.key') ? 'ok' : 'fail', config('app.key') ? 'set' : 'APP_KEY is missing: every session page will 500');
        $this->record('app.env', 'ok', (string) config('app.env'));
        $this->record(
            'app.debug',
            $production && config('app.debug') ? 'fail' : 'ok',
            config('app.debug') ? ($production ? 'APP_DEBUG=true in production leaks stack traces' : 'on (not production)') : 'off',
        );
        $this->record('session.driver', 'ok', (string) config('session.driver'));
        $this->checkNoSqlDrivers();
        $this->record('log', 'ok', 'channel '.config('logging.default').', level '.$this->logLevel());
        $this->record(
            'firebase.api_key',
            config('services.firebase.api_key') ? 'ok' : 'fail',
            config('services.firebase.api_key') ? 'set (staff login works)' : 'FIREBASE_API_KEY missing: staff cannot sign in',
        );
        $this->record('firebase.transport', 'ok', sprintf(
            'timeout %ss, attempts %d, parallel %d, slow %dms',
            config('services.firebase.timeout'),
            config('services.firebase.max_attempts'),
            config('services.firebase.max_parallel'),
            config('services.firebase.slow_ms'),
        ));
    }

    /**
     * The app has no SQL database any more. A session or cache store set to
     * "database" breaks every login; a "database" queue is unused but would
     * fail the moment something is queued.
     */
    private function checkNoSqlDrivers(): void
    {
        $broken = [];
        if (config('session.driver') === 'database') {
            $broken[] = 'SESSION_DRIVER';
        }
        if (config('cache.default') === 'database') {
            $broken[] = 'CACHE_STORE';
        }

        if ($broken !== []) {
            $this->record('no_sql', 'fail', implode(', ', $broken).' set to "database", but there is no SQL database: use file (or cookie for sessions)');
        } elseif (config('queue.default') === 'database') {
            $this->record('no_sql', 'warn', 'QUEUE_CONNECTION=database has no database behind it; set it to sync');
        } else {
            $this->record('no_sql', 'ok', 'sessions, cache and queue need no SQL database');
        }
    }

    /** Level of the default log channel (for a stack: its first channel). */
    private function logLevel(): string
    {
        $channel = config('logging.channels.'.config('logging.default'));
        if (($channel['driver'] ?? null) === 'stack') {
            $first = $channel['channels'][0] ?? null;
            $channel = $first ? config("logging.channels.{$first}") : null;
        }

        return (string) ($channel['level'] ?? 'debug');
    }

    private function checkCredentials(): array
    {
        $key = GoogleAccessToken::describeKey();
        $project = config('services.firebase.project_id');

        if (! $key['configured']) {
            $this->record('firebase.credentials', 'fail', 'no service account: set FIREBASE_CREDENTIALS_JSON (or FIREBASE_CREDENTIALS)');

            return $key;
        }

        $matches = $key['project_id'] === null || $key['project_id'] === $project;
        $this->record(
            'firebase.credentials',
            $matches ? 'ok' : 'fail',
            "from {$key['source']}, key project {$key['project_id']}, configured project {$project}".($matches ? '' : ' (MISMATCH)'),
        );

        return $key;
    }

    /** Time a check; it returns a detail string, or [status, detail]. */
    private function probe(string $check, callable $test): void
    {
        $started = hrtime(true);

        try {
            $outcome = $test();
            [$status, $detail] = is_array($outcome) ? $outcome : ['ok', $outcome];
        } catch (Throwable $e) {
            [$status, $detail] = ['fail', get_class($e).': '.Str::limit($e->getMessage(), 200)];
        }

        $this->record($check, $status, $detail, round((hrtime(true) - $started) / 1e6, 1));
    }

    private function record(string $check, string $status, string $detail, ?float $ms = null): void
    {
        $this->results[] = ['check' => $check, 'status' => $status, 'ms' => $ms, 'detail' => $detail];
    }

    private function report(FirebaseMetrics $metrics): int
    {
        $failed = count(array_filter($this->results, fn ($r) => $r['status'] === 'fail'));

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => $failed === 0,
                'failed' => $failed,
                'checks' => $this->results,
                'firebase' => $metrics->toArray(),
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Check', 'Status', 'ms', 'Detail'], array_map(
                fn ($r) => [$r['check'], strtoupper($r['status']), $r['ms'] ?? '', $r['detail']],
                $this->results,
            ));
            $failed === 0 ? $this->info('All checks passed.') : $this->error("{$failed} check(s) failed.");
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
