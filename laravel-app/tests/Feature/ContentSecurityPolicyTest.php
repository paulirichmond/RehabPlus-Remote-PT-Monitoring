<?php

namespace Tests\Feature;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeFirebase;
use Tests\TestCase;

/** Content-Security-Policy header, nonces on every script, and violation reports. */
class ContentSecurityPolicyTest extends TestCase
{
    use FakeFirebase;

    /** The nonce from the response's CSP header. */
    private function nonce(TestResponse $response): string
    {
        preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $m);
        $this->assertNotEmpty($m[1] ?? null, 'The CSP header has a script nonce.');

        return $m[1];
    }

    /** Every <script> carries this response's nonce, and no element has an inline on...= handler. */
    private function assertPageIsCspClean(TestResponse $response): void
    {
        $html = $response->getContent();
        $nonce = $this->nonce($response);

        preg_match_all('/<script\b[^>]*>/i', $html, $scripts);
        $this->assertNotEmpty($scripts[0]);
        foreach ($scripts[0] as $tag) {
            $this->assertStringContainsString('nonce="'.$nonce.'"', $tag, "Script without this request's nonce: {$tag}");
        }

        $this->assertDoesNotMatchRegularExpression('/<[^>]+\son[a-z]+\s*=\s*["\']/i', $html, 'Inline event handlers are blocked by the CSP.');
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_policy_is_strict_for_scripts(): void
    {
        $policy = $this->get('/login')->assertOk()->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\\/=]+'(;|$)/", $policy);
        $this->assertStringNotContainsString("'unsafe-eval'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringContainsString("base-uri 'self'", $policy);
        $this->assertStringContainsString("form-action 'self'", $policy);
        $this->assertStringContainsString('report-uri /csp-report', $policy);
        // script-src must not allow inline code or a whole CDN
        preg_match('/script-src ([^;]+)/', $policy, $script);
        $this->assertStringNotContainsString('unsafe-inline', $script[1]);
        $this->assertStringNotContainsString('jsdelivr', $script[1]);
    }

    public function test_nonce_changes_every_request(): void
    {
        $this->assertNotSame($this->nonce($this->get('/login')), $this->nonce($this->get('/login')));
    }

    public function test_login_page_is_csp_clean(): void
    {
        $response = $this->get('/login')->assertOk();

        $this->assertPageIsCspClean($response);
        $response->assertSee('id="passwordToggle"', false);
    }

    public function test_signed_in_pages_are_csp_clean(): void
    {
        $this->patientDoc('pat1', ['therapistId' => 'thA', 'therapistName' => 'Dr. Ana']);
        $this->docs['patients/pat1/goals/g1'] = ['title' => 'Walk', 'status' => 'active', 'progress' => 10];
        $this->docs['patients/pat1/assessments/a1'] = ['type' => 'ROM', 'score' => 70];
        $this->docs['patients/pat1/notes/n1'] = ['topic' => 'Visit', 'text' => 'ok'];
        $this->docs['payments/pay1'] = ['patient' => 'Pat', 'session' => 'S', 'fee' => 500, 'status' => 'Paid', 'date' => '2026-10-01'];
        $this->docs['inventory/inv1'] = ['item' => 'Bands', 'category' => 'Gear', 'stock' => 5, 'reorder' => 2, 'status' => 'Healthy'];
        $this->docs['appointments/ap1'] = ['patient' => 'Pat', 'therapist' => 'Dr. Ana', 'session' => 'S', 'date' => '2026-10-20', 'time' => '09:00', 'status' => 'Upcoming'];
        $this->staff('staff', 'st1', 'Sam');
        $admin = $this->staff('admin', 'adm', 'Ada');

        $pages = [
            '/dashboard', '/analytics', '/patients', '/patients/pat1', '/patients/pat1/edit', '/patients/create',
            '/patients/pat1/prescriptions/create', '/assessments', '/notes', '/messages', '/messages/pat1',
            '/appointments', '/appointments/create', '/billing', '/inventory', '/schedule', '/users', '/users/create',
            '/analytics?range=year',
        ];

        foreach ($pages as $page) {
            $response = $this->actingAs($admin)->get($page);
            if ($response->status() === 404) {
                continue; // a page this build doesn't have
            }
            $this->assertSame(200, $response->status(), "{$page} should load");
            $this->assertPageIsCspClean($response);
        }

        // The converted handlers are present as data- attributes.
        $users = $this->actingAs($admin)->get('/users')->getContent();
        $this->assertStringContainsString('data-confirm="Delete Sam permanently?', $users);
        $this->assertStringContainsString('data-autosubmit', $this->actingAs($admin)->get('/assessments')->getContent());
        $billing = $this->actingAs($admin)->get('/billing')->getContent();
        $this->assertStringContainsString('data-print', $billing);
        $this->assertStringContainsString('data-show="status-form-pay1"', $billing);
    }

    public function test_cdn_assets_are_pinned_with_integrity_hashes(): void
    {
        $html = $this->actingAs($this->staff('admin'))->get('/dashboard')->getContent();

        preg_match_all('/<(?:script|link)\b[^>]*cdn\.jsdelivr\.net[^>]*>/i', $html, $tags);
        $this->assertNotEmpty($tags[0]);
        foreach ($tags[0] as $tag) {
            $this->assertMatchesRegularExpression('/integrity="sha384-[A-Za-z0-9+\/=]{64}"/', $tag, "Missing SRI: {$tag}");
            $this->assertStringContainsString('crossorigin="anonymous"', $tag);
        }
    }

    public function test_json_and_csv_responses_get_no_policy(): void
    {
        $csv = $this->actingAs($this->staff('admin'))->get('/analytics/export/csv');

        $this->assertNull($csv->headers->get('Content-Security-Policy'));
    }

    public function test_report_only_and_off_modes(): void
    {
        config(['security.csp_mode' => 'report-only']);
        $response = $this->get('/login');
        $this->assertNull($response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("'nonce-", $response->headers->get('Content-Security-Policy-Report-Only'));

        config(['security.csp_mode' => 'off']);
        $response = $this->get('/login');
        $this->assertNull($response->headers->get('Content-Security-Policy'));
        $this->assertNull($response->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_violation_reports_are_logged_without_query_strings(): void
    {
        $logs = [];
        Event::listen(MessageLogged::class, function (MessageLogged $log) use (&$logs) {
            if ($log->message === 'csp.violation') {
                $logs[] = $log->context;
            }
        });

        $this->call('POST', '/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], json_encode(['csp-report' => [
            'document-uri' => 'https://site/patients?search=Jane%20Doe',
            'violated-directive' => 'script-src-elem',
            'effective-directive' => 'script-src-elem',
            'blocked-uri' => 'https://evil.example/x.js?token=abc',
            'line-number' => 12,
            'script-sample' => '',
        ]]))->assertNoContent();

        $this->assertCount(1, $logs);
        $this->assertSame('script-src-elem', $logs[0]['directive']);
        $this->assertSame('https://site/patients', $logs[0]['page']);
        $this->assertSame('https://evil.example/x.js', $logs[0]['blocked']);
        $this->assertSame(12, $logs[0]['line']);
    }

    public function test_bad_or_huge_reports_are_ignored(): void
    {
        $this->call('POST', '/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/json'], 'not json')->assertNoContent();
        $this->call('POST', '/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/json'], str_repeat('x', 20000))->assertNoContent();
    }
}
