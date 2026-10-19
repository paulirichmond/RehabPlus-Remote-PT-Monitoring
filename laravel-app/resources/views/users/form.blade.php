@extends('layouts.admin')

@section('title', $user ? 'Edit Account' : 'New Staff Account')

@section('content')
    @php
        $me = auth()->user();
        $roleLocked = $user && ($user['role'] === 'therapist' || $user['uid'] === $me->id);
    @endphp
    <div class="mb-3">
        <a href="{{ route('users.index') }}" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Back to staff accounts</a>
        <p class="page-title mb-1 mt-1">{{ $user ? 'Edit '.$user['name'] : 'New Staff Account' }}</p>
        <p class="page-subtitle mb-0">{{ $user ? $user['email'] : 'Creates a Firebase login for the clinic dashboard.' }}</p>
    </div>

    @include('partials.flash')

    <form method="POST" action="{{ $user ? route('users.update', $user['uid']) : route('users.store') }}" class="card shadow-sm border-0" style="max-width: 800px;">
        @csrf
        @if ($user)
            @method('PUT')
        @endif

        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label small fw-semibold">Full name</label>
                    <input id="name" type="text" name="name" class="form-control" value="{{ old('name', $user['name'] ?? '') }}" required maxlength="100">
                    @if ($user && $user['role'] === 'therapist')
                        <div class="form-text">Their patients see this name in the app.</div>
                    @endif
                </div>
                <div class="col-md-6">
                    <label for="role" class="form-label small fw-semibold">Role</label>
                    @if ($roleLocked)
                        <input type="text" class="form-control" value="{{ \App\Auth\StaffUser::ROLE_LABELS[$user['role']] }} (locked)" disabled>
                        <div class="form-text">{{ $user['role'] === 'therapist' ? 'Therapist accounts keep their role because patients are assigned to them.' : 'You can\'t change your own role.' }}</div>
                    @else
                        <select id="role" name="role" class="form-select">
                            @foreach (\App\Auth\StaffUser::ROLE_LABELS as $value => $label)
                                <option value="{{ $value }}" @selected(old('role', $user['role'] ?? 'staff') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Therapists manage their own patients; front desk staff see patient contact details and the clinic inbox; administrators can do everything.</div>
                    @endif
                </div>

                @unless ($user)
                    <div class="col-md-6">
                        <label for="email" class="form-label small fw-semibold">Email</label>
                        <input id="email" type="email" name="email" class="form-control" value="{{ old('email') }}" required maxlength="150" autocomplete="off">
                    </div>
                @endunless
                <div class="col-md-6">
                    <label for="password" class="form-label small fw-semibold">{{ $user ? 'New password (optional)' : 'Password' }}</label>
                    <input id="password" type="password" name="password" class="form-control" minlength="{{ \App\Services\Firebase\FirebaseAdmin::MIN_PASSWORD }}" @required(! $user) autocomplete="new-password">
                </div>
            </div>
        </div>
        <div class="card-footer bg-transparent text-end">
            <button type="submit" class="btn btn-primary px-4">{{ $user ? 'Save changes' : 'Create account' }}</button>
        </div>
    </form>
@endsection
