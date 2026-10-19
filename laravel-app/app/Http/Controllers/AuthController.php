<?php

namespace App\Http\Controllers;

use App\Auth\StaffUser;
use App\Services\Firebase\FirebaseAdmin;
use App\Services\Firebase\FirebaseException;
use App\Services\Firebase\FirestoreClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Staff login with their Firebase Auth email and password. Only accounts whose
 * users/{uid}.role is admin, therapist or staff get in; patients use the app.
 */
class AuthController extends Controller
{
    public function __construct(private FirebaseAdmin $admin, private FirestoreClient $db)
    {
    }

    public function login(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function attempt(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Logs identify the attempt by a short hash, never the email itself.
        $who = substr(hash('sha256', strtolower(trim($credentials['email']))), 0, 12);

        try {
            $result = $this->admin->verifyPassword($credentials['email'], $credentials['password']);
            $doc = $result['uid'] ? $this->db->get("users/{$result['uid']}") : null;
        } catch (FirebaseException $e) {
            report($e);
            $this->audit('firebase_error', $who);

            return $this->fail('Could not reach Firebase to check your login. Try again in a moment.');
        }

        if ($result['error'] === 'disabled') {
            $this->audit('disabled', $who);

            return $this->fail('This account has been disabled. Ask an administrator to re-enable it.');
        }
        if ($result['error'] === 'throttled') {
            $this->audit('throttled_by_firebase', $who);

            return $this->fail('Too many failed attempts. Wait a few minutes, then try again.');
        }
        if (! $result['uid']) {
            $this->audit('invalid_credentials', $who);

            return $this->fail('Invalid email or password.');
        }
        if (($doc['role'] ?? null) === 'patient') {
            $this->audit('patient_account', $who, $result['uid']);

            return $this->fail('Patient accounts sign in on the RehabPlus mobile app, not the clinic dashboard.');
        }

        $user = StaffUser::fromDocument($doc);
        if (! $user) {
            $this->audit($doc ? 'not_staff_or_inactive' : 'no_profile', $who, $result['uid']);

            return $this->fail('This account does not have access to the clinic dashboard.');
        }

        Auth::login($user);
        $request->session()->regenerate();
        $this->audit('success', $who, $user->id, $user->role);

        return redirect()->intended(route('dashboard'));
    }

    public function doLogout(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            Log::info('auth.logout', ['uid' => Auth::id()]);
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login.page');
    }

    /** One line per login attempt: outcome, hashed email, uid/role when known, client IP. */
    private function audit(string $outcome, string $emailHash, ?string $uid = null, ?string $role = null): void
    {
        $context = array_filter([
            'outcome' => $outcome,
            'email_hash' => $emailHash,
            'uid' => $uid,
            'role' => $role,
            'ip' => request()->ip(),
        ]);

        $outcome === 'success' ? Log::info('auth.login', $context) : Log::warning('auth.login', $context);
    }

    private function fail(string $message): RedirectResponse
    {
        return back()->with('error', $message)->onlyInput('email');
    }
}
