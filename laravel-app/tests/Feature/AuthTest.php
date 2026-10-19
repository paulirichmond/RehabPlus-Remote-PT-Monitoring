<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeFirebase;
use Tests\TestCase;

/** Staff sign in with their Firebase Auth email/password; roles come from users/{uid}. */
class AuthTest extends TestCase
{
    use FakeFirebase;

    public function test_login_page_loads(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    public function test_home_sends_guests_to_login_and_staff_to_dashboard(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->actingAs($this->staff('admin'))->get('/')->assertRedirect('/dashboard');
    }

    public function test_staff_can_log_in_with_their_firebase_password(): void
    {
        $this->staff('therapist', 'thUid', 'Dr. Reyes', 'reyes@clinic.com');

        $this->post('/login', ['email' => 'Reyes@Clinic.com', 'password' => 'secret-pass'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $this->assertSame('thUid', auth()->id());
        $this->assertSame('therapist', auth()->user()->role);
        $this->assertSame('Dr. Reyes', auth()->user()->name);
    }

    public function test_session_reloads_the_user_from_firestore(): void
    {
        $this->staff('admin', 'adm1', 'Ada Admin', 'ada@clinic.com');
        $this->post('/login', ['email' => 'ada@clinic.com', 'password' => 'secret-pass']);

        // A new request resolves the user via the provider (users/adm1).
        $this->app['auth']->forgetGuards();
        $this->get('/dashboard')->assertOk()->assertSee('Ada Admin');
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->staff('admin', 'adm1', 'Ada', 'ada@clinic.com');

        $this->from('/login')->post('/login', ['email' => 'ada@clinic.com', 'password' => 'nope'])
            ->assertRedirect('/login')
            ->assertSessionHas('error', 'Invalid email or password.');
        $this->assertGuest();
    }

    public function test_patients_are_told_to_use_the_app(): void
    {
        $this->patientDoc('pat1');
        $this->authUsers['pat1@mail.com'] = ['uid' => 'pat1', 'password' => 'secret-pass', 'disabled' => false, 'displayName' => 'Pat'];

        $this->from('/login')->post('/login', ['email' => 'pat1@mail.com', 'password' => 'secret-pass'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'mobile app'));
        $this->assertGuest();
    }

    public function test_disabled_and_roleless_accounts_cannot_log_in(): void
    {
        $this->staff('staff', 'st1', 'Sam', 'sam@clinic.com');
        $this->authUsers['sam@clinic.com']['disabled'] = true;
        $this->from('/login')->post('/login', ['email' => 'sam@clinic.com', 'password' => 'secret-pass'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'disabled'));

        $this->authUsers['nobody@clinic.com'] = ['uid' => 'noDoc', 'password' => 'secret-pass', 'disabled' => false, 'displayName' => 'X'];
        $this->from('/login')->post('/login', ['email' => 'nobody@clinic.com', 'password' => 'secret-pass'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'does not have access'));

        $this->assertGuest();
    }

    public function test_firebase_outage_shows_a_friendly_login_error(): void
    {
        Http::swap(new Factory);
        Http::fake(fn () => Http::failedConnection());

        $this->from('/login')->post('/login', ['email' => 'ada@clinic.com', 'password' => 'secret-pass'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Could not reach Firebase'));
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAs($this->staff('admin'));
        $originalSessionId = session()->getId();
        $originalCsrfToken = csrf_token();

        $this->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
        $this->assertNotSame($originalSessionId, session()->getId());
        $this->assertNotSame($originalCsrfToken, csrf_token());
    }
}
