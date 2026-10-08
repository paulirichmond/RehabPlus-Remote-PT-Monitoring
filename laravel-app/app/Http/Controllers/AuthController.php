<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function login(): View|
    \Illuminate\Http\RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectAuthenticatedUser();
        }

        Auth::logout();

        return view('auth.login', ['patientLogin' => false]);
    }

    public function patientLogin(): View|\Illuminate\Http\RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectAuthenticatedUser();
        }

        Auth::logout();

        return view('auth.login', ['patientLogin' => true]);
    }

    protected function redirectAuthenticatedUser()
    {
        $user = Auth::user();

        if ($user && $user->role === 'patient') {
            return redirect()->route('patient.portal');
        }

        return redirect()->route('dashboard');
    }

    public function attempt(Request $request)
    {
        $email = $request->input('email');
        $password = $request->input('password');

        $user = User::where('email', $email)->first();

        if ($user && $user->role === 'patient') {
            return back()->with('error', 'Patient accounts cannot use the admin login. Use the patient portal login.')->withInput();
        }

        if ($user && (int) $user->is_active === 1 && Auth::attempt(['email' => $email, 'password' => $password])) {
            return redirect()->intended('/dashboard');
        }

        return back()->with('error', 'Invalid email or password.')->withInput();
    }

    public function attemptPatient(Request $request)
    {
        $email = $request->input('email');
        $password = $request->input('password');

        $user = User::where('email', $email)->first();

        if ($user && $user->role === 'patient' && (int) $user->is_active === 1 && Auth::attempt(['email' => $email, 'password' => $password])) {
            return redirect()->route('patient.portal');
        }

        return back()->with('error', 'Invalid patient email or password.')->withInput();
    }

    public function doLogout()
    {
        Auth::logout();

        return redirect()->route('login.page')->with('success', 'You have been signed out.');
    }
}
