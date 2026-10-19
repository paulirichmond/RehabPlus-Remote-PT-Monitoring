<?php

namespace App\Http\Controllers;

use App\Auth\StaffUser;
use App\Helpers\UserValidation;
use App\Services\User\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Staff accounts (admin only): Firebase Auth logins with a role in Firestore
 * users/{uid}. Disabling is the normal way to remove access; deleting is for
 * mistakes and leftovers, and is refused while patients are assigned.
 */
class UserController extends Controller
{
    public function __construct(private UserService $users)
    {
    }

    public function index(): View
    {
        $all = $this->users->all();
        $staff = array_values(array_filter($all, fn ($u) => in_array($u['role'], StaffUser::ROLES, true)));

        return view('admin.users', [
            'users' => $staff,
            'noLogin' => $this->users->withoutLogin($staff),
            'patientCount' => count(array_filter($all, fn ($u) => $u['role'] === 'patient')),
        ]);
    }

    public function create(): View
    {
        return view('users.form', ['user' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->users->create(UserValidation::newAccount($request));

        return redirect()->route('users.index')->with('success', 'Account created. They can now sign in to the dashboard.');
    }

    public function edit(string $uid): View
    {
        return view('users.form', ['user' => $this->staffOr404($uid)]);
    }

    public function update(Request $request, string $uid): RedirectResponse
    {
        $user = $this->staffOr404($uid);
        $this->users->update($user, UserValidation::update($request, $user, $request->user()));

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    /** Enable or disable a login. */
    public function status(Request $request, string $uid): RedirectResponse
    {
        $user = $this->staffOr404($uid);
        if ($user['uid'] === $request->user()->id) {
            return back()->with('error', 'You can\'t disable your own account.');
        }

        $active = $request->boolean('active');
        $this->users->setActive($user, $active);

        return back()->with('success', $active ? 'Account enabled.' : 'Account disabled. They can no longer sign in.');
    }

    /** Delete the login and users/{uid}. */
    public function destroy(Request $request, string $uid): RedirectResponse
    {
        $user = $this->staffOr404($uid);
        if ($user['uid'] === $request->user()->id) {
            return back()->with('error', 'You can\'t delete your own account.');
        }

        if ($reason = $this->users->delete($user)) {
            return back()->with('error', $reason);
        }

        return redirect()->route('users.index')->with('success', 'Account deleted.');
    }

    private function staffOr404(string $uid): array
    {
        $user = $this->users->findStaff($uid);
        abort_unless($user, 404);

        return $user;
    }
}
