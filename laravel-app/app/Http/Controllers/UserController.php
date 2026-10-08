<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', ['users' => User::all()]);
    }

    public function create(): View
    {
        return view('users.form', ['user' => null, 'errors' => []]);
    }

    public function store(Request $request)
    {
        $role = $request->input('role', 'staff');

        $data = $request->validate([
            'name' => 'required|min:2|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
        ]);

        $data['role'] = $role;
        $data['is_active'] = true;
        $data['password'] = bcrypt($data['password']);

        User::create($data);

        return redirect()->route('users.index')->with('success', 'Account created successfully.');
    }

    public function createPatient(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|min:2|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
        ]);

        $data['role'] = 'patient';
        $data['is_active'] = true;
        $data['password'] = bcrypt($data['password']);

        $user = User::create($data);
        Patient::create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'condition' => 'Not specified',
        ]);

        return redirect()->route('users.index')->with('success', 'Patient account created successfully.');
    }

    public function edit(int $id)
    {
        $user = User::findOrFail($id);

        return view('users.form', ['user' => $user, 'errors' => []]);
    }

    public function show(int $id)
    {
        $user = User::findOrFail($id);

        return view('users.show', ['user' => $user]);
    }

    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        $rules = [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ];

        if ($request->filled('password')) {
            $rules['password'] = 'nullable|min:6';
        }

        $data = $request->validate($rules);

        if ($user->role === 'therapist') {
            $data['role'] = 'therapist';
        } elseif ($user->role !== 'patient') {
            $data['role'] = $request->input('role', $user->role);
        } else {
            $data['role'] = 'patient';
        }

        if ($request->filled('password')) {
            $data['password'] = bcrypt($request->input('password'));
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function delete(int $id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted.');
    }
}
