<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeFirebase;
use Tests\TestCase;

/** Dashboard numbers, computed from Firestore. Reports & Analytics: ReportTest. */
class DashboardTest extends TestCase
{
    use FakeFirebase;

    protected function setUp(): void
    {
        parent::setUp();

        $now = CarbonImmutable::now();
        $this->patientDoc('pOne', ['name' => 'Olive One', 'condition' => 'ACL', 'therapistId' => 'thA', 'therapistName' => 'Dr. Ana', 'createdAt' => $now->subDays(3)]);
        $this->patientDoc('pTwo', ['name' => 'Tom Two', 'condition' => 'Shoulder', 'therapistId' => 'thB', 'therapistName' => 'Dr. Ben', 'createdAt' => $now->subMonths(2)]);
        $this->patientDoc('pOld', ['name' => 'Otto Old', 'condition' => 'ACL', 'archived' => true, 'createdAt' => $now->subMonths(1)]);
        $this->docs['patients/pOne/sessions/s1'] = ['exerciseId' => 'kneeExtension', 'startTime' => $now->subDay(), 'overallCompliance' => 0.9, 'painLevel' => 2];
        $this->docs['patients/pOne/sessions/s2'] = ['exerciseId' => 'kneeExtension', 'startTime' => $now->subDays(2), 'overallCompliance' => 0.7, 'painLevel' => 4];
        $this->docs['patients/pTwo/sessions/s3'] = ['exerciseId' => 'sideRaise', 'startTime' => $now->subDay(), 'overallCompliance' => 0.5, 'painLevel' => 6];
    }

    public function test_dashboard_aggregates_sessions_for_the_whole_clinic(): void
    {
        $this->actingAs($this->staff('admin'))->get('/dashboard')->assertOk()
            ->assertSee('Olive One')->assertSee('Tom Two')->assertDontSee('Otto Old')
            ->assertSee('65%')   // avg accuracy over patients with sessions: (80 + 50) / 2
            ->assertSee('4.5');  // avg pain: (3 + 6) / 2
    }

    public function test_therapist_dashboard_only_covers_their_patients(): void
    {
        $this->actingAs($this->staff('therapist', 'thA', 'Dr. Ana'))->get('/dashboard')->assertOk()
            ->assertSee('Olive One')->assertDontSee('Tom Two')
            ->assertSee('80%');
        $this->actingAs($this->staff('therapist', 'thA', 'Dr. Ana'))->get('/analytics')->assertOk()->assertSee('Live');

        // Only their own patients' sessions are read, never the whole clinic's.
        $this->assertNotContains('sessions', $this->collectionGroupReads());
    }

    public function test_admin_dashboard_reads_clinic_sessions_in_one_collection_group_query(): void
    {
        $this->actingAs($this->staff('admin'))->get('/dashboard')->assertOk()->assertSee('Olive One')->assertSee('Tom Two');

        $this->assertSame(['sessions'], $this->collectionGroupReads());
    }

    public function test_firebase_outage_shows_friendly_page_instead_of_500(): void
    {
        $user = $this->staff('admin');
        Http::swap(new Factory);
        Http::fake(fn () => Http::failedConnection());

        $this->actingAs($user)->get('/dashboard')->assertStatus(503)->assertSee('reach its data');
    }
}
