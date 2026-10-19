<?php

namespace App\Http\Controllers;

use App\Services\Auth\PasswordResetService;
use App\Services\Firebase\FirebaseAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Forgot password: email -> 6-digit code -> new password (see PasswordResetService).
 *
 * The session only carries which email is being reset, when this browser last
 * asked for a code, and the token after a correct code. Every limit that
 * matters is enforced in Firestore, since a cookie session can be replayed.
 */
class PasswordResetController extends Controller
{
    /** Same wording whether or not the email has an account. */
    private const SENT = 'If an account exists for that email, we\'ve sent a 6-digit code to it.';

    public function __construct(private PasswordResetService $resets)
    {
    }

    public function request(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.forgot-password', ['step' => 'email']);
    }

    public function send(Request $request): RedirectResponse
    {
        $email = PasswordResetService::normalize($request->validate(['email' => 'required|email|max:254'])['email']);

        $session = $request->session();
        $waited = $session->get('password_reset.email') === $email ? $this->secondsUntilResend($request) : 0;
        if ($waited > 0) {
            return redirect()->route('password.verify')->with('error', "Wait {$waited} seconds before asking for another code.");
        }

        if (! $this->resets->requestCode($email, (string) $request->ip())) {
            return back()->withInput()->with('error', 'Too many reset requests from this network. Try again in an hour.');
        }

        $session->forget('password_reset.token');
        $session->put('password_reset.email', $email);
        $session->put('password_reset.sent_at', now()->getTimestamp());

        return redirect()->route('password.verify')->with('success', self::SENT);
    }

    public function verifyForm(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('password_reset.email')) {
            return redirect()->route('password.request');
        }

        return view('auth.forgot-password', [
            'step' => 'code',
            'email' => $request->session()->get('password_reset.email'),
            'resendIn' => $this->secondsUntilResend($request),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $email = $request->session()->get('password_reset.email');
        if (! $email) {
            return redirect()->route('password.request');
        }

        $code = $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/']], [
            'code.regex' => 'Enter the 6-digit code from the email.',
        ])['code'];

        $token = $this->resets->verifyCode($email, $code);
        if (! $token) {
            return back()->with('error', 'That code is incorrect or has expired. Check the email, or ask for a new code.');
        }

        $request->session()->put('password_reset.token', $token);

        return redirect()->route('password.reset');
    }

    public function resend(Request $request): RedirectResponse
    {
        $email = $request->session()->get('password_reset.email');
        if (! $email) {
            return redirect()->route('password.request');
        }

        $wait = $this->secondsUntilResend($request);
        if ($wait > 0) {
            return back()->with('error', "Wait {$wait} seconds before asking for another code.");
        }

        if (! $this->resets->requestCode($email, (string) $request->ip())) {
            return back()->with('error', 'Too many reset requests from this network. Try again in an hour.');
        }

        $request->session()->put('password_reset.sent_at', now()->getTimestamp());

        return back()->with('success', self::SENT);
    }

    public function resetForm(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('password_reset.token')) {
            return redirect()->route('password.request');
        }

        return view('auth.forgot-password', ['step' => 'password', 'email' => $request->session()->get('password_reset.email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $email = $request->session()->get('password_reset.email');
        $token = $request->session()->get('password_reset.token');
        if (! $email || ! $token) {
            return redirect()->route('password.request');
        }

        $password = $request->validate([
            'password' => 'required|string|min:'.FirebaseAdmin::MIN_PASSWORD.'|max:100|confirmed',
        ])['password'];

        if (! $this->resets->resetPassword($email, $token, $password)) {
            $request->session()->forget(['password_reset.token']);

            return redirect()->route('password.request')->with('error', 'This reset link has expired. Ask for a new code.');
        }

        $request->session()->forget(['password_reset.email', 'password_reset.sent_at', 'password_reset.token']);

        return redirect()->route('login.page')->with('success', 'Your password has been changed. Sign in with your new password.');
    }

    /** Seconds until this browser may ask for another code (the visible cooldown). */
    private function secondsUntilResend(Request $request): int
    {
        $sentAt = (int) $request->session()->get('password_reset.sent_at', 0);

        return max(0, $sentAt + PasswordResetService::RESEND_SECONDS - now()->getTimestamp());
    }
}
