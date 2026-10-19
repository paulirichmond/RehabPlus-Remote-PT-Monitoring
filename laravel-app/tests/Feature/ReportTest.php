<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Tests\Support\FakeFirebase;
use Tests\TestCase;

/** Reports & Analytics: one page, sections by role, exports, and the old URLs. */
class ReportTest extends TestCase
{
    use FakeFirebase;

    protected function setUp(): void
    {
        parent::setUp();

        $now = CarbonImmutable::now();
        $today = $now->toDateString();

        $this->patientDoc('pOne', ['name' => 'Olive One', 'condition' => 'ACL', 'therapistId' => 'thA', 'therapistName' => 'Dr. Ana', 'createdAt' => $now->subDays(3)]);
        $this->patientDoc('pTwo', ['name' => 'Tom Two', 'condition' => 'Shoulder', 'therapistId' => 'thB', 'therapistName' => 'Dr. Ben', 'createdAt' => $now->subMonths(2)]);
        $this->patientDoc('pOld', ['name' => 'Otto Old', 'condition' => 'ACL', 'archived' => true, 'createdAt' => $now->subMonths(1)]);

        $this->docs['patients/pOne/sessions/s1'] = ['startTime' => $now->subDay(), 'overallCompliance' => 0.9, 'painLevel' => 2];
        $this->docs['patients/pTwo/sessions/s2'] = ['startTime' => $now->subDay(), 'overallCompliance' => 0.5, 'painLevel' => 6];

        $this->docs['patients/pOne/goals/g1'] = ['title' => 'Walk', 'status' => 'achieved', 'progress' => 100, 'achievedAt' => $now->subDays(2)];
        $this->docs['patients/pOne/goals/g2'] = ['title' => 'Run', 'status' => 'active', 'progress' => 40];
        $this->docs['patients/pTwo/goals/g3'] = ['title' => 'Lift', 'status' => 'active', 'progress' => 10];

        $this->docs['appointments/a1'] = ['patient' => 'Olive One', 'therapist' => 'Dr. Ana', 'date' => $today, 'time' => '09:00', 'status' => 'Completed'];
        $this->docs['appointments/a2'] = ['patient' => 'Tom Two', 'therapist' => 'Dr. Ben', 'date' => $today, 'time' => '10:00', 'status' => 'Cancelled'];
        $this->docs['appointments/a3'] = ['patient' => 'Tom Two', 'therapist' => 'Dr. Ben', 'date' => $today, 'time' => '11:00', 'status' => 'Upcoming'];
        $this->docs['appointments/old'] = ['patient' => 'Olive One', 'therapist' => 'Dr. Ana', 'date' => '2001-01-01', 'time' => '09:00', 'status' => 'Completed'];

        $this->docs['payments/p1'] = ['patient' => 'Olive One', 'session' => 'Rehab', 'fee' => 1000, 'status' => 'Paid', 'date' => $today];
        $this->docs['payments/p2'] = ['patient' => 'Tom Two', 'session' => 'Rehab', 'fee' => 500, 'status' => 'Pending', 'date' => $today];
        $this->docs['payments/p3'] = ['patient' => 'Tom Two', 'session' => 'Rehab', 'fee' => 250, 'status' => 'Partial', 'date' => $today];
        $this->docs['payments/old'] = ['patient' => 'Old', 'session' => 'Rehab', 'fee' => 9999, 'status' => 'Paid', 'date' => '2001-01-01'];
    }

    private function report(string $role, string $query = '', ?string $uid = null): array
    {
        $response = $this->actingAs($this->staff($role, $uid))->get('/analytics'.($query ? "?{$query}" : ''))->assertOk();

        return $response->viewData('sections') === null ? [] : [
            'response' => $response,
            'sections' => $response->viewData('sections'),
            'appointments' => $response->viewData('appointments'),
            'billing' => $response->viewData('billing'),
            'goals' => $response->viewData('goals'),
            'patients' => $response->viewData('patients'),
            'recovery' => $response->viewData('recovery'),
        ];
    }

    public function test_admin_sees_every_section_with_real_numbers(): void
    {
        $r = $this->report('admin');

        $this->assertSame(['recovery', 'appointments', 'billing', 'goals', 'patients'], $r['sections']);

        // Appointments in range (the 2001 one is outside "last 12 months").
        $this->assertSame(3, $r['appointments']['total']);
        $this->assertSame(1, $r['appointments']['completed']);
        $this->assertSame(1, $r['appointments']['cancelled']);
        $this->assertSame(1, $r['appointments']['upcoming']);
        $this->assertSame(50, $r['appointments']['completionRate']);

        // Billing: same definitions as the Billing page.
        $this->assertEquals(1750, $r['billing']['billed']);
        $this->assertEquals(1000, $r['billing']['collected']);
        $this->assertEquals(750, $r['billing']['outstanding']);

        // Goals: 1 achieved of 3 counted.
        $this->assertSame(33, $r['goals']['achievementRate']);
        $this->assertSame(2, $r['goals']['active']);
        $this->assertSame(1, $r['goals']['achievedInRange']);

        // Registrations include archived patients; recovery covers active ones.
        $this->assertSame(3, $r['patients']['summary']['totalPatients']);
        $this->assertSame(2, $r['recovery']['totalPatients']);

        $r['response']->assertSee('Reports &amp; Analytics', false)->assertSee('Olive One')->assertSee('Otto Old')->assertSee('₱1,000.00');
    }

    public function test_front_desk_staff_see_operations_but_no_clinical_or_registration_data(): void
    {
        $r = $this->report('staff');

        $this->assertSame(['recovery', 'appointments', 'billing'], $r['sections']);
        $this->assertNull($r['goals']);
        $this->assertNull($r['patients']);
        $r['response']->assertDontSee('Export CSV');
    }

    public function test_therapist_sees_only_their_patients_recovery_and_goals(): void
    {
        $r = $this->report('therapist', uid: 'thA');

        $this->assertSame(['recovery', 'goals'], $r['sections']);
        $this->assertSame(1, $r['recovery']['totalPatients']);
        $this->assertSame(['Olive One'], $r['recovery']['recoveryLabels']);
        $this->assertSame(2, $r['goals']['total']); // pOne's goals only
        $this->assertNull($r['appointments']);
        $this->assertNull($r['billing']);
        $r['response']->assertDontSee('Tom Two')->assertDontSee('₱');

        // Only their own patients' data was read, never a clinic-wide collection group.
        $this->assertSame([], $this->collectionGroupReads());
    }

    public function test_date_range_narrows_the_dated_sections(): void
    {
        $r = $this->report('admin', 'range=custom&from=2001-01-01&to=2001-01-31');

        $this->assertSame(1, $r['appointments']['total']);
        $this->assertEquals(9999, $r['billing']['billed']);
        $this->assertSame(0, $r['goals']['achievedInRange']);
        $this->assertSame(0, $r['patients']['summary']['totalPatients']);
    }

    public function test_patient_filters_apply_to_registrations(): void
    {
        $r = $this->report('admin', 'status=inactive');
        $r['response']->assertSee('Otto Old');
        $this->assertSame(1, $r['patients']['summary']['totalPatients']);

        $r = $this->report('admin', 'condition=Shoulder');
        $this->assertSame(1, $r['patients']['summary']['totalPatients']);
    }

    public function test_malformed_filters_do_not_break_the_page(): void
    {
        foreach (['range=custom&from=nonsense&to=2026-01-01', 'range=custom&from=2026-13-45&to=2026-02-31', 'from[]=x', 'condition[]=x&status[]=y&range[]=z'] as $query) {
            $this->actingAs($this->staff('admin'))->get("/analytics?{$query}")->assertOk();
        }

        // Reversed dates are swapped rather than giving an empty report.
        $this->actingAs($this->staff('admin'))->get('/analytics?range=custom&from=2030-01-01&to=2000-01-01')
            ->assertOk()->assertSee('Jan 1, 2000 - Jan 1, 2030');
    }

    public function test_csv_export_has_every_section_and_the_filtered_patients(): void
    {
        $csv = $this->actingAs($this->staff('admin'))->get('/analytics/export/csv?condition=ACL')->streamedContent();

        foreach (['Recovery', 'Appointments', 'Billing', 'Treatment goals', 'Patient registrations'] as $section) {
            $this->assertStringContainsString($section, $csv);
        }
        $this->assertStringContainsString('"Collected (paid)",1000.00', $csv);
        $this->assertStringContainsString('"Olive One",ACL,"Dr. Ana"', $csv);
        $this->assertStringNotContainsString('Tom Two', $csv);
    }

    public function test_csv_export_neutralises_spreadsheet_formulas(): void
    {
        $this->patientDoc('evil', ['name' => '=HYPERLINK("http://x","click")', 'condition' => '+SUM(A1)', 'createdAt' => CarbonImmutable::now()]);

        $csv = $this->actingAs($this->staff('admin'))->get('/analytics/export/csv')->streamedContent();

        $this->assertStringContainsString('"\'=HYPERLINK(""http://x"",""click"")"', $csv);
        $this->assertStringContainsString("'+SUM(A1)", $csv);
        $this->assertDoesNotMatchRegularExpression('/(^|,)"?[=+@]/m', $csv);
    }

    public function test_pdf_export_renders_every_section(): void
    {
        $this->actingAs($this->staff('admin'))->get('/analytics/export/pdf')->assertOk()
            ->assertSee('Appointments')->assertSee('Billing')->assertSee('Treatment goals')
            ->assertSee('Olive One')->assertSee('₱1,000.00');
    }

    public function test_exports_are_admin_only(): void
    {
        foreach (['therapist', 'staff'] as $role) {
            $this->actingAs($this->staff($role))->get('/analytics/export/csv')->assertForbidden();
            $this->actingAs($this->staff($role))->get('/analytics/export/pdf')->assertForbidden();
        }
    }

    public function test_old_addresses_redirect_and_keep_filters(): void
    {
        $admin = $this->staff('admin');

        $this->actingAs($admin)->get('/reports')->assertRedirect(route('analytics'))->assertStatus(301);
        $this->actingAs($admin)->get('/patient-statistics?status=inactive')
            ->assertRedirect(route('analytics').'?status=inactive#patients');
        $this->actingAs($admin)->get('/patient-statistics/export/csv?condition=ACL')
            ->assertRedirect(route('analytics.export.csv').'?condition=ACL');
    }

    public function test_sidebar_has_one_reports_entry(): void
    {
        $html = $this->actingAs($this->staff('admin'))->get('/dashboard')->getContent();

        $this->assertSame(1, substr_count($html, 'Reports &amp; Analytics'));
        $this->assertStringNotContainsString('Patient Statistics', $html);
    }

    public function test_page_reads_firestore_in_two_parallel_rounds(): void
    {
        $this->actingAs($this->staff('admin'))->get('/analytics')->assertOk();

        $metrics = app(\App\Services\Firebase\FirebaseMetrics::class);
        $this->assertLessThanOrEqual(3, $metrics->batches, 'auth lookup excluded, data should take two rounds');
        $this->assertGreaterThanOrEqual(4, $metrics->largestBatch);
    }
}
