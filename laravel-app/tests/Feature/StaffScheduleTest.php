<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Tests\Support\FakeFirebase;
use Tests\TestCase;

/** Shifts and time off live in Firestore; the staff list comes from Firebase accounts. */
class StaffScheduleTest extends TestCase
{
    use FakeFirebase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->staff('admin', 'adm', 'Admin'));
        $this->staff('therapist', 'th1', 'Dr. Real Therapist');
    }

    public function test_schedule_lists_real_staff_without_patients_or_disabled_accounts(): void
    {
        $this->patientDoc('pat1', ['name' => 'Some Patient']);
        $this->staff('staff', 'gone', 'Former Staffer');
        $this->docs['users/gone']['active'] = false;

        $this->get('/schedule')
            ->assertOk()
            ->assertSee('Dr. Real Therapist')
            ->assertDontSee('Some Patient')
            ->assertDontSee('Former Staffer')
            ->assertDontSee('Dr. Sarah Mitchell');
    }

    public function test_shift_can_be_added_and_shows_today(): void
    {
        $today = Carbon::today()->format('l');

        $this->post('/schedule/save', [
            'staff' => 'Dr. Real Therapist',
            'role' => 'Therapist',
            'day' => $today,
            'shift' => '08:00 - 15:00',
            'room' => 'Room 3',
        ])->assertRedirect('/schedule');

        $shifts = array_values($this->docsLike('staffShifts/*'));
        $this->assertCount(1, $shifts);
        $this->assertSame(['staff' => 'Dr. Real Therapist', 'role' => 'Therapist', 'day' => $today, 'shift' => '08:00 - 15:00', 'room' => 'Room 3'],
            array_intersect_key($shifts[0], array_flip(['staff', 'role', 'day', 'shift', 'room'])));
        $this->assertArrayHasKey('createdAt', $shifts[0]);

        $this->get('/schedule')->assertOk()->assertSee('08:00 - 15:00')->assertSee('Available');
    }

    public function test_overlapping_shift_in_same_room_is_rejected(): void
    {
        $this->staff('therapist', 'thA', 'A');
        $this->staff('staff', 'stB', 'B');
        $this->docs['staffShifts/s1'] = ['staff' => 'A', 'role' => 'Therapist', 'day' => 'Monday', 'shift' => '08:00 - 12:00', 'room' => 'Room 1'];

        $this->post('/schedule/save', [
            'staff' => 'B',
            'role' => 'Therapist',
            'day' => 'Monday',
            'shift' => '10:00 - 14:00',
            'room' => 'Room 1',
        ])->assertRedirect('/schedule')->assertSessionHas('error', fn (string $e) => str_contains($e, 'conflict'));

        $this->assertCount(1, $this->docsLike('staffShifts/*'));
    }

    public function test_only_therapists_and_front_desk_are_listed_never_admins(): void
    {
        $this->staff('admin', 'adm2', 'Second Clinic Admin');
        $this->staff('staff', 'fd1', 'Front Desk Person');
        // An old shift saved under an admin's name doesn't put them back on the schedule.
        $this->docs['staffShifts/old'] = ['staff' => 'Second Clinic Admin', 'role' => 'Admin', 'day' => Carbon::today()->format('l'), 'shift' => '08:00 - 12:00', 'room' => 'Room 9'];

        $this->get('/schedule')
            ->assertOk()
            ->assertSee('Dr. Real Therapist')
            ->assertSee('Front Desk Person')
            ->assertDontSee('Second Clinic Admin')
            ->assertDontSee('Room 9')
            ->assertViewHas('todayShifts', 0);
    }

    public function test_admins_and_unknown_names_cannot_be_given_shifts_or_time_off(): void
    {
        $this->staff('admin', 'adm2', 'Second Clinic Admin');

        foreach (['Second Clinic Admin', 'Admin', 'Nobody At All'] as $name) {
            $this->post('/schedule/save', [
                'staff' => $name,
                'role' => 'Therapist',
                'day' => 'Monday',
                'shift' => '08:00 - 12:00',
                'room' => 'Room 1',
            ])->assertRedirect('/schedule')->assertSessionHas('error', fn (string $e) => str_contains($e, 'Only active therapists'));

            $this->post('/schedule/time-off/save', [
                'staff' => $name,
                'date' => Carbon::today()->toDateString(),
                'reason' => 'Leave',
            ])->assertRedirect('/schedule')->assertSessionHas('error');
        }

        $this->assertCount(0, $this->docsLike('staffShifts/*'));
        $this->assertCount(0, $this->docsLike('staffTimeOff/*'));
    }

    public function test_person_with_therapist_and_front_desk_accounts_is_one_row_with_both_roles(): void
    {
        $this->staff('staff', 'fd2', 'Dr. Real Therapist');

        $this->get('/schedule')
            ->assertOk()
            ->assertViewHas('staffDirectory', fn (array $dir) => collect($dir)->where('name', 'Dr. Real Therapist')->values()->all()
                === [['name' => 'Dr. Real Therapist', 'role' => 'Therapist & Front Desk Staff']])
            ->assertSee('Therapist &amp; Front Desk Staff', false);
    }

    public function test_any_difference_in_the_full_name_means_different_people(): void
    {
        $this->staff('staff', 'fd2', 'dr. real therapist');
        $this->staff('staff', 'fd3', 'Dr. Real  Therapist');
        $this->docs['staffShifts/s1'] = ['staff' => 'dr. real therapist', 'role' => 'Front Desk Staff', 'day' => Carbon::today()->format('l'), 'shift' => '08:00 - 12:00', 'room' => 'Reception'];

        $this->get('/schedule')
            ->assertOk()
            ->assertViewHas('staffDirectory', fn (array $dir) => collect($dir)->pluck('role', 'name')->sortKeys()->all() === collect([
                'Dr. Real Therapist' => 'Therapist',
                'dr. real therapist' => 'Front Desk Staff',
                'Dr. Real  Therapist' => 'Front Desk Staff',
            ])->sortKeys()->all())
            ->assertViewHas('staffAvailability', fn (array $a) => $a['dr. real therapist'] === 'Available'
                && $a['Dr. Real Therapist'] === 'Unavailable' && $a['Dr. Real  Therapist'] === 'Unavailable');

        // The same time slot for the other "Dr. Real Therapist" isn't a clash with the lower-case one.
        $this->post('/schedule/save', [
            'staff' => 'Dr. Real Therapist',
            'role' => 'Therapist',
            'day' => Carbon::today()->format('l'),
            'shift' => '08:00 - 12:00',
            'room' => 'Room 2',
        ])->assertRedirect('/schedule')->assertSessionMissing('error');
    }

    public function test_front_desk_staff_can_be_given_a_shift(): void
    {
        $this->staff('staff', 'fd1', 'Front Desk Person');

        $this->post('/schedule/save', [
            'staff' => 'Front Desk Person',
            'role' => 'Front desk',
            'day' => 'Tuesday',
            'shift' => '09:00 - 17:00',
            'room' => 'Reception',
        ])->assertRedirect('/schedule')->assertSessionMissing('error');

        $this->assertCount(1, $this->docsLike('staffShifts/*'));
    }

    public function test_time_off_marks_staff_on_leave(): void
    {
        $this->post('/schedule/time-off/save', [
            'staff' => 'Dr. Real Therapist',
            'date' => Carbon::today()->toDateString(),
            'time_off_type' => 'Full day',
            'reason' => 'Medical leave',
        ])->assertRedirect('/schedule');

        $off = array_values($this->docsLike('staffTimeOff/*'))[0];
        $this->assertSame('Dr. Real Therapist', $off['staff']);
        $this->assertSame('Medical leave', $off['reason']);
        $this->assertSame(Carbon::today()->toDateString(), $off['date']);
        $this->get('/schedule')->assertOk()->assertSee('On Leave');
    }
}
