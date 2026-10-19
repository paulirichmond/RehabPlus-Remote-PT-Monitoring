<?php

namespace Tests\Feature;

use Tests\Support\FakeFirebase;
use Tests\TestCase;

/** Pages of the original dashboard still load; billing/inventory live in Firestore. */
class RehabPlusConversionTest extends TestCase
{
    use FakeFirebase;

    public function test_admin_management_pages_load(): void
    {
        $this->actingAs($this->staff('admin', 'adm', 'Super Admin'));

        $pages = [
            '/dashboard' => 'RehabPlus Dashboard',
            '/analytics' => 'Reports & Analytics',
            '/patients' => 'Patients',
            '/patients/create' => 'Add Patient',
            '/messages' => 'Messages',
            '/appointments' => 'Appointments',
            '/appointments/create' => 'Appointment',
            '/billing' => 'Billing and Payment Records',
            '/inventory' => 'Inventory and Supplies',
            '/schedule' => 'Staff Schedule',
            '/notes' => 'Therapy Notes & Care Plans',
            '/assessments' => 'Assessments & Goals',
            '/users' => 'Staff Accounts',
            '/users/create' => 'New Staff Account',
        ];

        foreach ($pages as $path => $heading) {
            $this->get($path)
                ->assertOk()
                ->assertSee($heading)
                ->assertSee('RehabPlus');
        }
    }

    public function test_sidebar_matches_role(): void
    {
        $this->actingAs($this->staff('staff'))->get('/dashboard')->assertOk()
            ->assertDontSee('Therapy Notes &amp; Care Plans', false)
            ->assertDontSee('Staff Accounts')
            ->assertSee('Front Desk Staff');

        $this->actingAs($this->staff('therapist'))->get('/dashboard')->assertOk()
            ->assertSee('Therapy Notes &amp; Care Plans', false)
            ->assertDontSee('Staff Accounts');
    }

    public function test_appointment_form_lists_firebase_therapists(): void
    {
        $this->staff('therapist', 'th1', 'Dr. Lee');

        $this->actingAs($this->staff('admin'))->get('/appointments/create')->assertOk()->assertSee('Dr. Lee');
    }

    public function test_billing_page_uses_pesos(): void
    {
        $this->actingAs($this->staff('admin'))->get('/billing')->assertOk()->assertSee('₱0.00');
    }

    public function test_payment_record_can_be_created(): void
    {
        $this->actingAs($this->staff('admin'));

        $this->post('/billing', [
            'patient' => 'Maria Santos',
            'session' => 'Physical Therapy',
            'fee' => '1500.00',
            'status' => 'Paid',
            'date' => '2026-10-09',
        ])->assertRedirect('/billing');

        $payment = array_values($this->docsLike('payments/*'))[0];
        $this->assertSame('Maria Santos', $payment['patient']);
        $this->assertSame(1500.0, $payment['fee']);
        $this->assertSame('Paid', $payment['status']);
        $this->assertSame('2026-10-09', $payment['date']);

        $this->get('/billing')->assertOk()->assertSee('Maria Santos')->assertSee('1500.00')->assertSee('₱1,500.00');
    }

    public function test_payment_status_can_be_updated_and_deleted(): void
    {
        $this->actingAs($this->staff('admin'));
        $this->docs['payments/pay1'] = ['patient' => 'Maria Santos', 'session' => 'Physical Therapy', 'fee' => 1500.0, 'status' => 'Pending', 'date' => '2026-10-09'];

        $this->patch('/billing/pay1/status', ['status' => 'Paid'])->assertRedirect('/billing');
        $this->assertSame('Paid', $this->docs['payments/pay1']['status']);
        $this->assertSame('Maria Santos', $this->docs['payments/pay1']['patient']);
        $this->patch('/billing/pay1/status', ['status' => 'Refunded'])->assertSessionHasErrors('status');

        $this->delete('/billing/pay1')->assertRedirect('/billing');
        $this->assertArrayNotHasKey('payments/pay1', $this->docs);
    }

    public function test_missing_or_malformed_payment_ids_are_404(): void
    {
        $this->actingAs($this->staff('admin'));

        $this->patch('/billing/nope/status', ['status' => 'Paid'])->assertNotFound();
        $this->delete('/billing/a..b')->assertNotFound();
        $this->assertSame([], $this->commits);
    }

    public function test_inventory_item_can_be_created_and_status_changed(): void
    {
        $this->actingAs($this->staff('admin'));

        $this->post('/inventory', [
            'item' => 'Resistance Band', 'category' => 'Equipment', 'stock' => 20, 'reorder' => 10, 'status' => 'Healthy',
        ])->assertRedirect('/inventory');
        $path = array_key_first($this->docsLike('inventory/*'));
        $id = explode('/', $path)[1];
        $this->assertSame(20, $this->docs[$path]['stock']);

        $this->patch("/inventory/{$id}/status", ['status' => 'Critical'])->assertRedirect('/inventory');
        $this->assertSame('Critical', $this->docs[$path]['status']);
        $this->assertSame(20, $this->docs[$path]['stock']);
        $this->patch("/inventory/{$id}/status", ['status' => 'Unknown'])->assertSessionHasErrors('status');

        $this->get('/inventory')->assertOk()->assertSee('Resistance Band');
        $this->get("/inventory/{$id}/edit")->assertOk()->assertSee('Save changes');

        $this->put("/inventory/{$id}", [
            'item' => 'Resistance Band (heavy)', 'category' => 'Equipment', 'stock' => 5, 'reorder' => 10, 'status' => 'Low',
        ])->assertRedirect('/inventory');
        $this->assertSame('Resistance Band (heavy)', $this->docs[$path]['item']);
        $this->get('/inventory')->assertOk()->assertSee('1 orders'); // stock 5 <= reorder 10

        $this->delete("/inventory/{$id}")->assertRedirect('/inventory');
        $this->assertSame([], $this->docsLike('inventory/*'));
        $this->get('/inventory/missing/edit')->assertNotFound();
    }
}
