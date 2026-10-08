@extends('layouts.app')

@section('title', $user ? 'Edit User' : 'New User')

@section('content')
    <div class="topbar">
        <div>
            <a href="{{ route('users.index') }}">← Back to users</a>
        </div>
    </div>

    <div class="panel" style="max-width: 800px; margin: 0 auto;">
        <h1>{{ $user ? 'Edit Account' : 'Create Account' }}</h1>

        <form method="POST" action="{{ $user ? route('users.update', $user->id) : route('users.store') }}" style="display:grid; gap:16px;">
            @csrf
            @if($user)
                @method('PUT')
            @endif

            <div class="grid-two">
                <label style="display:grid; gap:6px; font-weight:bold;">
                    Full name
                    <input type="text" name="name" value="{{ old('name', $user->name ?? '') }}" required>
                </label>

                @if($user && $user->role === 'therapist')
                    <label style="display:grid; gap:6px; font-weight:bold;">
                        Role
                        <input type="hidden" name="role" value="therapist">
                        <div style="padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 16px; background: #f8fafc; color: #0f172a; font-weight: 700;">
                            Therapist (locked)
                        </div>
                    </label>
                @else
                    <label style="display:grid; gap:6px; font-weight:bold;">
                        Role
                        <select name="role">
                            <option value="staff" {{ old('role', $user->role ?? 'staff') === 'staff' ? 'selected' : '' }}>Staff</option>
                            <option value="therapist" {{ old('role', $user->role ?? 'staff') === 'therapist' ? 'selected' : '' }}>Therapist</option>
                            <option value="superadmin" {{ old('role', $user->role ?? 'staff') === 'superadmin' ? 'selected' : '' }}>Super Admin</option>
                            <option value="patient" {{ old('role', $user->role ?? 'staff') === 'patient' ? 'selected' : '' }}>Patient</option>
                        </select>
                    </label>
                @endif
            </div>

            <label style="display:grid; gap:6px; font-weight:bold;">
                Email
                <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" required>
            </label>

            @if(!$user)
                <label style="display:grid; gap:6px; font-weight:bold;">
                    Password
                    <input type="password" name="password" required>
                </label>
            @else
                <label style="display:grid; gap:6px; font-weight:bold;">
                    New password (optional)
                    <input type="password" name="password">
                </label>
            @endif

            <button type="submit" class="btn">{{ $user ? 'Update User' : 'Create User' }}</button>
        </form>
    </div>
@endsection
