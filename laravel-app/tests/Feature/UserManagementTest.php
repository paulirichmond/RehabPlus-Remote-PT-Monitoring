<?php

namespace Tests\Feature;

use Tests\Support\FakeFirebase;
use Tests\TestCase;

/** Staff accounts: Firebase Auth logins + users/{uid} roles, admin only. */
class UserManagementTest extends TestCase
{
    use FakeFirebase;

    public function test_only_admins_manage_staff(): void
    {
        $this->actingAs($this->staff('therapist'))->get('/users')->assertForbidden();
        $this->actingAs($this->staff('staff'))->post('/users', [])->assertForbidden();
    }

    public function test_list_shows_staff_but_not_patients(): void
    {
        $this->staff('therapist', 'th1', 'Dr. Lee');
        $this->patientDoc('pat1', ['name' => 'Patty Patient']);

        $this->actingAs($this->staff('admin', 'adm', 'Ada Admin'))->get('/users')->assertOk()
            ->assertSee('Dr. Lee')->assertSee('Ada Admin')->assertDontSee('Patty Patient')
            ->assertSee('Patient app accounts');
    }

    public function test_create_staff_account(): void
    {
        $this->actingAs($this->staff('admin'))->post('/users', [
            'name' => 'Dr. New', 'email' => 'New@Clinic.com', 'password' => 'long-enough', 'role' => 'therapist',
        ])->assertRedirect('/users');

        $uid = $this->authUsers['new@clinic.com']['uid'];
        $this->assertSame('long-enough', $this->authUsers['new@clinic.com']['password']);
        $this->assertSame(['role' => 'therapist', 'name' => 'Dr. New', 'email' => 'new@clinic.com', 'active' => true], array_diff_key($this->docs["users/{$uid}"], ['createdAt' => 1]));
    }

    public function test_patient_role_cannot_be_given_here(): void
    {
        $this->actingAs($this->staff('admin'))->post('/users', [
            'name' => 'X', 'email' => 'x@clinic.com', 'password' => 'long-enough', 'role' => 'patient',
        ])->assertSessionHasErrors('role');
        $this->assertArrayNotHasKey('x@clinic.com', $this->authUsers);
    }

    public function test_role_defaults_to_front_desk_staff(): void
    {
        $this->actingAs($this->staff('admin'))->post('/users', ['name' => 'Fran', 'email' => 'fran@clinic.com', 'password' => 'long-enough']);

        $this->assertSame('staff', $this->docs['users/'.$this->authUsers['fran@clinic.com']['uid']]['role']);
    }

    public function test_therapist_role_is_locked_and_rename_reaches_their_patients(): void
    {
        $this->staff('therapist', 'th1', 'Dr. Lee');
        $this->patientDoc('pat1', ['therapistId' => 'th1', 'therapistName' => 'Dr. Lee']);
        $this->patientDoc('pat2', ['therapistId' => 'other', 'therapistName' => 'Dr. Other']);

        $this->actingAs($this->staff('admin'))->put('/users/th1', ['name' => 'Dr. Lee-Santos', 'role' => 'admin', 'password' => 'brand-new-pw'])
            ->assertRedirect('/users');

        $this->assertSame('therapist', $this->docs['users/th1']['role']);
        $this->assertSame('Dr. Lee-Santos', $this->docs['users/th1']['name']);
        $this->assertSame('Dr. Lee-Santos', $this->docs['patients/pat1']['therapistName']);
        $this->assertSame('Dr. Other', $this->docs['patients/pat2']['therapistName']);
        $this->assertSame('brand-new-pw', $this->authUsers['th1@rehabplus.com']['password']);
    }

    public function test_admin_cannot_change_own_role_or_disable_self(): void
    {
        $admin = $this->staff('admin', 'adm');

        $this->actingAs($admin)->put('/users/adm', ['name' => 'Still Admin', 'role' => 'staff']);
        $this->assertSame('admin', $this->docs['users/adm']['role']);

        $this->actingAs($admin)->from('/users')->post('/users/adm/status', ['active' => 0])->assertSessionHas('error');
        $this->assertFalse($this->authUsers['adm@rehabplus.com']['disabled']);
    }

    public function test_disable_and_enable_account(): void
    {
        $this->staff('staff', 'st1', 'Sam');

        $this->actingAs($this->staff('admin'))->from('/users')->post('/users/st1/status', ['active' => 0])->assertRedirect('/users');
        $this->assertTrue($this->authUsers['st1@rehabplus.com']['disabled']);
        $this->assertFalse($this->docs['users/st1']['active']);

        $this->actingAs($this->staff('admin'))->post('/users/st1/status', ['active' => 1]);
        $this->assertFalse($this->authUsers['st1@rehabplus.com']['disabled']);
        $this->assertTrue($this->docs['users/st1']['active']);
    }

    public function test_delete_removes_login_and_staff_record(): void
    {
        $this->staff('staff', 'st1', 'Sam');

        $this->actingAs($this->staff('admin'))->from('/users')->delete('/users/st1')
            ->assertRedirect('/users')->assertSessionHas('success');

        $this->assertArrayNotHasKey('st1@rehabplus.com', $this->authUsers);
        $this->assertArrayNotHasKey('users/st1', $this->docs);
        $this->assertContains('users/st1', $this->deleted);
    }

    public function test_leftover_record_without_login_is_flagged_and_deletable(): void
    {
        $this->staff('admin', 'gone', 'Old Admin', 'you@rehabplus.com');
        unset($this->authUsers['you@rehabplus.com']); // login deleted in the Firebase console
        $admin = $this->staff('admin', 'adm', 'Ada Admin');

        $this->actingAs($admin)->get('/users')->assertOk()->assertSee('Old Admin')->assertSee('No login');

        $this->actingAs($admin)->from('/users')->delete('/users/gone')->assertRedirect('/users')->assertSessionHas('success');
        $this->assertArrayNotHasKey('users/gone', $this->docs);
        $this->actingAs($admin)->get('/users')->assertDontSee('Old Admin')->assertDontSee('No login');
    }

    public function test_cannot_delete_self_or_therapist_with_patients(): void
    {
        $admin = $this->staff('admin', 'adm');
        $this->staff('therapist', 'th1', 'Dr. Lee');
        $this->patientDoc('pat1', ['therapistId' => 'th1', 'therapistName' => 'Dr. Lee']);

        $this->actingAs($admin)->from('/users')->delete('/users/adm')->assertSessionHas('error');
        $this->actingAs($admin)->from('/users')->delete('/users/th1')->assertSessionHas('error');

        $this->assertArrayHasKey('users/adm', $this->docs);
        $this->assertArrayHasKey('users/th1', $this->docs);
        $this->assertArrayHasKey('th1@rehabplus.com', $this->authUsers);
    }

    public function test_only_admins_can_delete_and_patients_cannot_be_deleted_here(): void
    {
        $this->staff('staff', 'st1');
        $this->patientDoc('pat1');

        $this->actingAs($this->staff('therapist'))->delete('/users/st1')->assertForbidden();
        $this->actingAs($this->staff('admin'))->delete('/users/pat1')->assertNotFound();
        $this->assertArrayHasKey('users/st1', $this->docs);
        $this->assertArrayHasKey('users/pat1', $this->docs);
    }

    public function test_patients_are_not_editable_here(): void
    {
        $this->patientDoc('pat1');

        $this->actingAs($this->staff('admin'))->get('/users/pat1/edit')->assertNotFound();
    }

    public function test_make_admin_command_creates_the_first_admin(): void
    {
        $this->artisan('rehab:make-admin', ['email' => 'Boss@Clinic.com', 'name' => 'Clinic Boss'])
            ->expectsQuestion('Password for the new admin (min 8 characters)', 'boss-password')
            ->expectsQuestion('Repeat the password', 'boss-password')
            ->assertSuccessful();

        $uid = $this->authUsers['boss@clinic.com']['uid'];
        $this->assertSame('admin', $this->docs["users/{$uid}"]['role']);

        // Running it for an existing staff login promotes it without touching the password.
        $this->staff('staff', 'st1', 'Sam', 'sam@clinic.com');
        $this->artisan('rehab:make-admin', ['email' => 'sam@clinic.com', 'name' => 'Sam'])->assertSuccessful();
        $this->assertSame('admin', $this->docs['users/st1']['role']);
        $this->assertSame('secret-pass', $this->authUsers['sam@clinic.com']['password']);
    }
}
