<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeFirebase;
use Tests\TestCase;

/** Appointments are stored in the Firestore `appointments` collection. */
class AppointmentTest extends TestCase
{
    use FakeFirebase;

    private function form(array $overrides = []): array
    {
        return $overrides + [
            'patient' => 'Emily Johnson',
            'therapist' => 'Dr. Lee',
            'patient_condition' => 'Knee rehab',
            'contact' => '555-1201',
            'date' => '2026-10-20',
            'time' => '09:30',
            'session' => 'Mobility Session',
            'notes' => 'Focus on flexion.',
            'status' => 'Upcoming',
        ];
    }

    public function test_appointment_is_created_listed_and_shown(): void
    {
        $this->actingAs($this->staff('admin'));

        $this->post('/appointments', $this->form())->assertRedirect('/appointments')->assertSessionHas('success');

        $path = array_key_first($this->docsLike('appointments/*'));
        $id = explode('/', $path)[1];
        $doc = $this->docs[$path];
        $this->assertSame('Emily Johnson', $doc['patient']);
        $this->assertSame('2026-10-20', $doc['date']);
        $this->assertSame('09:30', $doc['time']);
        $this->assertNull($doc['completed_at']);
        $this->assertArrayHasKey('createdAt', $doc);

        $this->get('/appointments')->assertOk()->assertSee('Emily Johnson')->assertSee('Oct 20, 2026');
        $this->get("/appointments/{$id}")->assertOk()->assertSee('Knee rehab')->assertSee('9:30 AM');
        $this->get("/appointments/{$id}/edit")->assertOk()->assertSee('Update Appointment')->assertSee('555-1201');
    }

    public function test_status_change_stamps_completion_and_counts_today(): void
    {
        $this->actingAs($this->staff('admin'));
        $this->docs['appointments/ap1'] = $this->form(['date' => now()->toDateString(), 'completed_at' => null]);

        $this->post('/appointments/ap1/status', ['status' => 'Completed'])->assertRedirect('/appointments');
        $this->assertSame('Completed', $this->docs['appointments/ap1']['status']);
        $this->assertInstanceOf(CarbonImmutable::class, $this->docs['appointments/ap1']['completed_at']);
        $this->assertSame('Emily Johnson', $this->docs['appointments/ap1']['patient']);

        $this->post('/appointments/ap1/status', ['status' => 'Bogus'])->assertSessionHas('error');
        $this->assertSame('Completed', $this->docs['appointments/ap1']['status']);

        $this->post('/appointments/ap1/status', ['status' => 'Cancelled']);
        $this->assertNull($this->docs['appointments/ap1']['completed_at']);
    }

    public function test_update_keeps_original_completion_time(): void
    {
        $this->actingAs($this->staff('admin'));
        $done = CarbonImmutable::parse('2026-10-01 10:00:00');
        $this->docs['appointments/ap1'] = $this->form(['status' => 'Completed', 'completed_at' => $done]);

        $this->put('/appointments/ap1', $this->form(['status' => 'Completed', 'notes' => 'Edited']))->assertRedirect('/appointments');

        $this->assertSame('Edited', $this->docs['appointments/ap1']['notes']);
        $this->assertTrue($done->equalTo($this->docs['appointments/ap1']['completed_at']));
    }

    public function test_delete_and_missing_ids(): void
    {
        $this->actingAs($this->staff('admin'));
        $this->docs['appointments/ap1'] = $this->form();

        $this->delete('/appointments/ap1')->assertRedirect('/appointments');
        $this->assertArrayNotHasKey('appointments/ap1', $this->docs);

        $this->get('/appointments/ap1')->assertNotFound();
        $this->put('/appointments/nope', $this->form())->assertNotFound();
        $this->get('/appointments/bad.id')->assertNotFound();
        $this->assertSame([], $this->commits);
    }

    public function test_firestore_outage_shows_friendly_page(): void
    {
        $this->actingAs($this->staff('admin'));
        Http::fake(fn () => Http::failedConnection());

        $this->get('/appointments')->assertStatus(503)->assertSee('Could not reach Firebase');
    }
}
