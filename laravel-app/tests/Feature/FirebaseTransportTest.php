<?php

namespace Tests\Feature;

use App\Services\Firebase\FirebaseException;
use App\Services\Firebase\FirebaseMetrics;
use App\Services\Firebase\FirebaseTransport;
use App\Services\Firebase\FirestoreClient;
use App\Services\Firebase\GoogleAccessToken;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Support\FakeFirebase;
use Tests\TestCase;

/**
 * FirebaseTransport: parallel batches, retries, token refresh and the
 * diagnostic logging, plus the request-level diagnostics and headers.
 */
class FirebaseTransportTest extends TestCase
{
    use FakeFirebase;

    /** @var array<int, MessageLogged> */
    private array $logs = [];

    protected function setUp(): void
    {
        parent::setUp();

        Event::listen(MessageLogged::class, fn (MessageLogged $log) => $this->logs[] = $log);
    }

    private function db(): FirestoreClient
    {
        return app(FirestoreClient::class);
    }

    /** Firestore document paths requested so far, in the order they were sent. */
    private function requestedPaths(): array
    {
        return Http::recorded()
            ->map(fn ($pair) => str_contains($pair[0]->url(), '/documents/') ? urldecode(explode('/documents/', $pair[0]->url())[1]) : null)
            ->filter()
            ->values()
            ->all();
    }

    private function logsNamed(string $message): array
    {
        return array_values(array_filter($this->logs, fn (MessageLogged $log) => $log->message === $message));
    }

    public function test_tasks_run_concurrently_and_share_batches(): void
    {
        foreach (['a1', 'a2', 'b1', 'b2'] as $id) {
            $this->docs["users/{$id}"] = ['name' => $id];
        }

        $results = $this->db()->concurrently([
            'a' => fn () => [$this->db()->get('users/a1')['name'], $this->db()->get('users/a2')['name']],
            'b' => fn () => [$this->db()->get('users/b1')['name'], $this->db()->get('users/b2')['name']],
        ]);

        $this->assertSame(['a' => ['a1', 'a2'], 'b' => ['b1', 'b2']], $results);
        // Interleaved: each task's first read went out together, then each second read.
        $this->assertSame(['users/a1', 'users/b1', 'users/a2', 'users/b2'], $this->requestedPaths());

        $metrics = app(FirebaseMetrics::class);
        $this->assertSame(4, $metrics->calls);
        $this->assertSame(2, $metrics->batches);
        $this->assertSame(2, $metrics->largestBatch);
    }

    public function test_results_keep_task_order_even_when_tasks_finish_out_of_order(): void
    {
        $this->docs['users/x'] = ['name' => 'X'];

        $results = $this->db()->concurrently([
            'slow' => fn () => $this->db()->get('users/x')['name'],
            'instant' => fn () => 'no firebase needed',
        ]);

        $this->assertSame(['slow', 'instant'], array_keys($results));
        $this->assertSame(['slow' => 'X', 'instant' => 'no firebase needed'], $results);
    }

    public function test_a_failing_task_does_not_stop_the_others_and_is_rethrown(): void
    {
        $this->docs['users/ok'] = ['name' => 'Fine'];
        $finished = false;

        try {
            $this->db()->concurrently([
                fn () => throw new RuntimeException('task one broke'),
                function () use (&$finished) {
                    $this->db()->get('users/ok');
                    $finished = true;
                },
            ]);
            $this->fail('Expected the first task\'s exception.');
        } catch (RuntimeException $e) {
            $this->assertSame('task one broke', $e->getMessage());
        }

        $this->assertTrue($finished, 'The healthy task should still run to the end.');
    }

    public function test_reads_are_retried_on_transient_errors(): void
    {
        $this->docs['patients/p1'] = ['name' => 'Pat'];
        $this->injectedFailures['/patients/p1'] = [503, 'connection'];

        $this->assertSame('Pat', $this->db()->get('patients/p1')['name']);

        $this->assertSame(3, app(FirebaseMetrics::class)->calls);
        $this->assertSame(2, app(FirebaseMetrics::class)->retries);
        $this->assertCount(2, $this->logsNamed('firebase.call retrying'));
    }

    public function test_reads_give_up_after_max_attempts_with_a_friendly_error(): void
    {
        $this->injectedFailures['/patients/p1'] = ['connection', 'connection', 'connection'];

        try {
            $this->db()->get('patients/p1');
            $this->fail('Expected a FirebaseException.');
        } catch (FirebaseException $e) {
            $this->assertStringContainsString('Could not reach Firebase', $e->getMessage());
        }

        $this->assertSame(3, app(FirebaseMetrics::class)->calls);
        $this->assertSame(1, app(FirebaseMetrics::class)->failures);
        $failed = $this->logsNamed('firebase.call failed');
        $this->assertCount(1, $failed);
        $this->assertSame('error', $failed[0]->level);
    }

    public function test_commits_are_never_retried(): void
    {
        $this->injectedFailures[':commit'] = [503];

        try {
            $this->db()->commit([$this->db()->mergeWrite('patients/p1', ['name' => 'X'], increments: ['visits' => 1])]);
            $this->fail('Expected a FirebaseException.');
        } catch (FirebaseException $e) {
            $this->assertStringNotContainsString('injected', $e->getMessage(), 'Raw Firebase errors stay in the logs, not on screen.');
        }

        $this->assertSame([], $this->commits, 'An increment must not be applied twice.');
        $this->assertSame(1, app(FirebaseMetrics::class)->calls);
    }

    public function test_a_rejected_token_is_refreshed_once(): void
    {
        $token = new class extends GoogleAccessToken
        {
            public int $forgotten = 0;

            public function get(): string
            {
                return 'token-'.$this->forgotten;
            }

            public function forget(): void
            {
                $this->forgotten++;
            }
        };
        $this->instance(GoogleAccessToken::class, $token);
        $this->docs['users/u1'] = ['name' => 'U'];
        $this->injectedFailures['/users/u1'] = [401];

        $this->assertSame('U', $this->db()->get('users/u1')['name']);
        $this->assertSame(1, $token->forgotten);
        $this->assertSame('Bearer token-1', Http::recorded()->last()[0]->header('Authorization')[0]);
    }

    public function test_call_logs_are_structured_and_hold_no_secrets(): void
    {
        $this->staff('admin', 'adm', 'Ada', 'ada@clinic.com');

        $this->post('/login', ['email' => 'ada@clinic.com', 'password' => 'secret-pass'])->assertRedirect('/dashboard');

        $calls = $this->logsNamed('firebase.call');
        $this->assertNotEmpty($calls);
        foreach ($calls as $log) {
            $this->assertSame('debug', $log->level);
            $this->assertArrayHasKey('op', $log->context);
            $this->assertArrayHasKey('target', $log->context);
            $this->assertArrayHasKey('status', $log->context);
        }

        $everything = json_encode(array_map(fn ($l) => [$l->message, $l->context], $this->logs));
        $this->assertStringNotContainsString('secret-pass', $everything);
        $this->assertStringNotContainsString('ada@clinic.com', $everything);
        $this->assertStringNotContainsString('test-token', $everything);
        $this->assertStringNotContainsString('test-api-key', $everything);

        $login = $this->logsNamed('auth.login')[0];
        $this->assertSame('success', $login->context['outcome']);
        $this->assertSame('adm', $login->context['uid']);
        $this->assertSame(12, strlen($login->context['email_hash']));
    }

    public function test_failed_login_is_audited_without_the_email(): void
    {
        $this->post('/login', ['email' => 'nobody@clinic.com', 'password' => 'wrong-password']);

        $login = $this->logsNamed('auth.login')[0];
        $this->assertSame('warning', $login->level);
        $this->assertSame('invalid_credentials', $login->context['outcome']);
        $this->assertStringNotContainsString('nobody', json_encode($login->context));
    }

    public function test_every_request_gets_an_id_a_summary_line_and_security_headers(): void
    {
        $this->patientDoc('pat1');

        $response = $this->actingAs($this->staff('admin', 'adm'))
            ->withHeader('X-Request-Id', 'trace-from-upstream-123')
            ->get('/patients/pat1')
            ->assertOk()
            ->assertHeader('X-Request-Id', 'trace-from-upstream-123')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $summary = $this->logsNamed('http.request')[0];
        $this->assertSame('info', $summary->level);
        $this->assertSame('patients.show', $summary->context['route']);
        $this->assertSame(200, $summary->context['status']);
        $this->assertSame('adm', $summary->context['user_uid']);
        $this->assertGreaterThan(0, $summary->context['firebase']['calls']);
        // The profile's five clinical reads went out as one batch.
        $this->assertGreaterThanOrEqual(5, $summary->context['firebase']['largest_batch']);
    }

    public function test_unsafe_upstream_request_ids_are_replaced(): void
    {
        $response = $this->withHeader('X-Request-Id', "bad\nid injected")->get('/login');

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $response->headers->get('X-Request-Id'));
    }

    public function test_firebase_outage_page_shows_the_request_id(): void
    {
        $this->injectedFailures[':runQuery'] = array_fill(0, 30, 503);

        $this->actingAs($this->staff('admin'))
            ->withHeader('X-Request-Id', 'outage-ref-12345')
            ->get('/patients')
            ->assertStatus(503)
            ->assertSee('outage-ref-12345');
    }

    public function test_diagnose_command_reports_json_and_passes_against_the_fake(): void
    {
        $exitCode = Artisan::call('rehab:diagnose', ['--json' => true]);
        $report = json_decode(Artisan::output(), true);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($report['ok']);
        $checks = array_column($report['checks'], null, 'check');
        $this->assertSame('3 reads sent as 1 parallel batch', $checks['firestore.parallel']['detail']);
        $this->assertSame('ok', $checks['auth.admin']['status']);
        $this->assertStringNotContainsString('test-token', Artisan::output());
    }

    public function test_diagnose_command_fails_without_credentials(): void
    {
        config(['services.firebase.credentials' => null, 'services.firebase.credentials_json' => null]);

        $this->artisan('rehab:diagnose')->assertFailed();
    }

    public function test_diagnose_flags_settings_that_need_the_removed_sql_database(): void
    {
        config(['session.driver' => 'database']);
        Artisan::call('rehab:diagnose', ['--json' => true]);
        $checks = array_column(json_decode(Artisan::output(), true)['checks'], null, 'check');
        $this->assertSame('fail', $checks['no_sql']['status']);
        $this->assertStringContainsString('SESSION_DRIVER', $checks['no_sql']['detail']);

        config(['session.driver' => 'file', 'queue.default' => 'database']);
        Artisan::call('rehab:diagnose', ['--json' => true]);
        $checks = array_column(json_decode(Artisan::output(), true)['checks'], null, 'check');
        $this->assertSame('warn', $checks['no_sql']['status']);

        config(['queue.default' => 'sync']);
        Artisan::call('rehab:diagnose', ['--json' => true]);
        $checks = array_column(json_decode(Artisan::output(), true)['checks'], null, 'check');
        $this->assertSame('ok', $checks['no_sql']['status']);
    }

    public function test_transport_is_shared_within_a_request(): void
    {
        $this->assertSame(app(FirebaseTransport::class), app(FirebaseTransport::class));
    }
}
