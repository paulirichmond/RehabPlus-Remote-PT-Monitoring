<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Tests\Support\FakeFirebase;
use Tests\TestCase;

class PatientTest extends TestCase
{
    use FakeFirebase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff('therapist', 'thA', 'Dr. Ana');
        $this->staff('therapist', 'thB', 'Dr. Ben');
        $this->patientDoc('pOne', ['name' => 'Olive One', 'condition' => 'ACL', 'therapistId' => 'thA', 'therapistName' => 'Dr. Ana']);
        $this->patientDoc('pTwo', ['name' => 'Tom Two', 'condition' => 'Shoulder', 'therapistId' => 'thB', 'therapistName' => 'Dr. Ben']);
        $this->patientDoc('pOld', ['name' => 'Otto Archived', 'therapistId' => 'thA', 'archived' => true]);
    }

    private function newPatient(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Nina New', 'condition' => 'Ankle sprain', 'email' => 'Nina@Mail.com',
            'password' => 'temp-pass-1', 'password_confirmation' => 'temp-pass-1',
            'phone' => '0917 555 0101', 'dob' => '1990-05-14',
            'medical_summary' => 'Inversion sprain', 'therapy_plan' => 'Balance work',
        ];
    }

    public function test_list_is_scoped_by_role(): void
    {
        $this->actingAs($this->staff('therapist', 'thA', 'Dr. Ana'))->get('/patients')
            ->assertOk()->assertSee('Olive One')->assertDontSee('Tom Two')->assertDontSee('Otto Archived');

        $this->actingAs($this->staff('admin'))->get('/patients')
            ->assertOk()->assertSee('Olive One')->assertSee('Tom Two')->assertDontSee('Otto Archived');

        $this->actingAs($this->staff('staff'))->get('/patients?archived=1')
            ->assertOk()->assertSee('Otto Archived')->assertDontSee('Olive One');
    }

    public function test_search_and_condition_filter(): void
    {
        $this->actingAs($this->staff('admin'))->get('/patients?search=tom')
            ->assertSee('Tom Two')->assertDontSee('Olive One');
        $this->actingAs($this->staff('admin'))->get('/patients?condition=ACL')
            ->assertSee('Olive One')->assertDontSee('Tom Two');
    }

    public function test_therapist_creates_patient_with_app_login_assigned_to_them(): void
    {
        $response = $this->actingAs($this->staff('therapist', 'thA', 'Dr. Ana'))
            ->post('/patients', $this->newPatient());

        // Firebase Auth login with the temporary password; its uid keys everything else.
        $uid = $this->authUsers['nina@mail.com']['uid'];
        $this->assertSame(['uid' => $uid, 'password' => 'temp-pass-1', 'disabled' => false, 'displayName' => 'Nina New'], $this->authUsers['nina@mail.com']);
        $response->assertRedirect("/patients/{$uid}")->assertSessionHas('success');

        // users/{uid} + patients/{uid} created together, never overwriting.
        [$user, $patient] = $this->commits[0];
        $this->assertSame(self::ROOT."/users/{$uid}", $user['update']['name']);
        $this->assertSame(['exists' => false], $patient['currentDocument']);
        $this->assertSame(['role' => 'patient', 'name' => 'Nina New', 'email' => 'nina@mail.com', 'active' => true], array_diff_key($this->docs["users/{$uid}"], ['createdAt' => 1]));

        $doc = $this->docs["patients/{$uid}"];
        $this->assertSame('thA', $doc['therapistId']);
        $this->assertSame('Dr. Ana', $doc['therapistName']);
        $this->assertSame('Ankle sprain', $doc['condition']);
        $this->assertSame('Inversion sprain', $doc['medicalSummary']);
        $this->assertSame('Balance work', $doc['therapyPlan']);
        $this->assertSame('0917 555 0101', $doc['phone']);
        $this->assertSame('1990-05-14', $doc['dob']);
        $this->assertSame('nina@mail.com', $doc['email']);
        $this->assertFalse($doc['archived']);
        $this->assertInstanceOf(CarbonImmutable::class, $doc['createdAt']);
    }

    public function test_admin_picks_the_therapist(): void
    {
        $this->actingAs($this->staff('admin'))
            ->post('/patients', $this->newPatient(['therapist_id' => 'thB']))
            ->assertRedirect();

        $doc = collect($this->docs)->first(fn ($d, $p) => str_starts_with($p, 'patients/newUid') && substr_count($p, '/') === 1);
        $this->assertSame('thB', $doc['therapistId']);
        $this->assertSame('Dr. Ben', $doc['therapistName']);
    }

    public function test_front_desk_staff_cannot_add_patients(): void
    {
        $this->actingAs($this->staff('staff'))->get('/patients/create')->assertForbidden();
        $this->actingAs($this->staff('staff'))->post('/patients', $this->newPatient())->assertForbidden();
    }

    public function test_validation_and_taken_email(): void
    {
        $user = $this->staff('admin');
        $this->actingAs($user)->post('/patients', $this->newPatient(['password_confirmation' => 'different']))
            ->assertSessionHasErrors('password');
        $this->actingAs($user)->post('/patients', $this->newPatient(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertSessionHasErrors('password');

        $this->authUsers['nina@mail.com'] = ['uid' => 'existing', 'password' => 'x', 'disabled' => false, 'displayName' => 'x'];
        $this->actingAs($user)->from('/patients/create')->post('/patients', $this->newPatient())
            ->assertRedirect('/patients/create')
            ->assertSessionHas('error', 'That email address already has an account.')
            ->assertSessionHasInput('name', 'Nina New')
            ->assertSessionMissing('_old_input.password');
        $this->assertSame([], $this->commits);
    }

    public function test_login_is_deleted_again_if_firestore_write_fails(): void
    {
        $this->failCommits = true;

        $this->actingAs($this->staff('admin'))->from('/patients/create')->post('/patients', $this->newPatient())
            ->assertRedirect('/patients/create')
            ->assertSessionHas('error');

        $this->assertArrayNotHasKey('nina@mail.com', $this->authUsers);
        $this->assertStringEndsWith('accounts:delete', end($this->authCalls)['url']);
    }

    public function test_therapist_cannot_see_or_change_another_therapists_patient(): void
    {
        $user = $this->staff('therapist', 'thA', 'Dr. Ana');
        $this->actingAs($user)->get('/patients/pTwo')->assertNotFound();
        $this->actingAs($user)->get('/patients/pTwo/edit')->assertNotFound();
        $this->actingAs($user)->put('/patients/pTwo', ['name' => 'X', 'condition' => 'Y'])->assertNotFound();
        $this->actingAs($user)->post('/patients/pTwo/archive')->assertNotFound();
        $this->assertSame([], $this->commits);
    }

    public function test_front_desk_sees_profile_but_not_clinical_records_or_edit(): void
    {
        $user = $this->staff('staff');
        $this->actingAs($user)->get('/patients/pOne')
            ->assertOk()->assertSee('Olive One')->assertDontSee('Assigned exercises')->assertDontSee('Therapy notes');
        $this->actingAs($user)->get('/patients/pOne/edit')->assertForbidden();
    }

    public function test_update_profile_and_admin_reassignment(): void
    {
        $this->actingAs($this->staff('therapist', 'thA', 'Dr. Ana'))
            ->put('/patients/pOne', ['name' => 'Olive Renamed', 'condition' => 'ACL reconstruction', 'phone' => '123', 'therapist_id' => 'thB'])
            ->assertRedirect('/patients/pOne');

        $doc = $this->docs['patients/pOne'];
        $this->assertSame('Olive Renamed', $doc['name']);
        $this->assertSame('ACL reconstruction', $doc['condition']);
        $this->assertSame('thA', $doc['therapistId'], 'only admins reassign');
        $this->assertSame('Olive Renamed', $this->docs['users/pOne']['name']);
        $this->assertSame('Olive Renamed', $this->authUsers['pOne@mail.com']['displayName'] ?? 'Olive Renamed');

        $this->actingAs($this->staff('admin'))
            ->put('/patients/pOne', ['name' => 'Olive Renamed', 'condition' => 'ACL', 'therapist_id' => 'thB']);
        $this->assertSame('thB', $this->docs['patients/pOne']['therapistId']);
        $this->assertSame('Dr. Ben', $this->docs['patients/pOne']['therapistName']);
    }

    public function test_archive_disables_login_and_restore_reenables(): void
    {
        $this->authUsers['pOne@mail.com'] = ['uid' => 'pOne', 'password' => 'x', 'disabled' => false, 'displayName' => 'Olive One'];
        $user = $this->staff('therapist', 'thA', 'Dr. Ana');

        $this->actingAs($user)->post('/patients/pOne/archive')->assertRedirect('/patients');
        $this->assertTrue($this->authUsers['pOne@mail.com']['disabled']);
        $this->assertTrue($this->docs['patients/pOne']['archived']);
        $this->assertFalse($this->docs['users/pOne']['active']);

        $this->actingAs($user)->post('/patients/pOne/restore')->assertRedirect('/patients/pOne');
        $this->assertFalse($this->authUsers['pOne@mail.com']['disabled']);
        $this->assertFalse($this->docs['patients/pOne']['archived']);
    }

    public function test_temporary_password_reset(): void
    {
        $this->authUsers['pOne@mail.com'] = ['uid' => 'pOne', 'password' => 'old', 'disabled' => false, 'displayName' => 'Olive One'];

        $this->actingAs($this->staff('admin'))
            ->post('/patients/pOne/password', ['password' => 'new-temp-9', 'password_confirmation' => 'new-temp-9'])
            ->assertRedirect('/patients/pOne');

        $this->assertSame('new-temp-9', $this->authUsers['pOne@mail.com']['password']);
    }

    public function test_profile_shows_progress_from_app_sessions(): void
    {
        $now = CarbonImmutable::now();
        $this->docs['patients/pOne/prescriptions/rx1'] = ['exerciseType' => 'kneeExtension', 'targetReps' => 10, 'frequencyPerWeek' => 2, 'active' => true, 'createdAt' => $now->subDays(20)];
        $this->docs['patients/pOne/prescriptions/rx2'] = ['exerciseType' => 'wallSit', 'targetReps' => 3, 'holdSeconds' => 20, 'frequencyPerWeek' => 3, 'active' => false, 'createdAt' => $now->subDays(30)];
        $this->docs['patients/pOne/sessions/s1'] = ['exerciseId' => 'kneeExtension', 'exerciseName' => 'Knee Extension', 'prescriptionId' => 'rx1', 'startTime' => $now->subDays(1), 'completedReps' => 10, 'targetReps' => 10, 'rejectedReps' => 2, 'overallCompliance' => 0.8, 'painLevel' => 4, 'painNote' => 'Sore after', 'endedEarly' => false, 'outsideSchedule' => false];
        $this->docs['patients/pOne/sessions/s2'] = ['exerciseId' => 'kneeExtension', 'prescriptionId' => 'rx1', 'startTime' => $now->subDays(2), 'completedReps' => 4, 'targetReps' => 10, 'overallCompliance' => 0.6, 'painLevel' => 6, 'endedEarly' => true, 'outsideSchedule' => true];
        $this->docs['patients/pOne/goals/g1'] = ['title' => 'Walk 1km', 'status' => 'achieved', 'progress' => 100, 'createdAt' => $now];
        $this->docs['patients/pOne/goals/g2'] = ['title' => 'Climb stairs', 'status' => 'active', 'progress' => 40, 'createdAt' => $now];

        $this->actingAs($this->staff('therapist', 'thA', 'Dr. Ana'))->get('/patients/pOne')
            ->assertOk()
            ->assertSee('Knee Extension')
            ->assertSee('Wall Sit')
            ->assertSee('Stopped')
            ->assertSee('1/2 sessions')      // only the completed session counts this week
            ->assertSee('50%')               // adherence
            ->assertSee('70%')               // accuracy (0.8 + 0.6) / 2
            ->assertSee('5/10')              // average pain
            ->assertSee('Ended early')
            ->assertSee('Outside schedule')
            ->assertSee('Sore after')
            ->assertSee('Climb stairs')
            ->assertSee('50% achieved');
    }
}
