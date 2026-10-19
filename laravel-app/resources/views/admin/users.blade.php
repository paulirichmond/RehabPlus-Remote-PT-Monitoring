@extends('layouts.admin')

@section('title', 'Staff Accounts')

@section('content')
    @php($me = auth()->user())
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <p class="page-title mb-1">Staff Accounts</p>
            <p class="page-subtitle mb-0">Dashboard logins (Firebase). Patients get their app login when they're added on the <a href="{{ route('patients.index') }}">Patients</a> page.</p>
        </div>
        <a href="{{ route('users.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus-fill me-2"></i>Add Staff Account
        </a>
    </div>

    @include('partials.flash')

    <div class="row g-4 mb-4">
        @foreach ([
            ['Staff accounts', count($users), 'bi-person-badge-fill', 'text-success'],
            ['Therapists', count(array_filter($users, fn ($u) => $u['role'] === 'therapist' && $u['active'])), 'bi-heart-pulse-fill', 'text-info'],
            ['Patient app accounts', $patientCount, 'bi-people-fill', 'text-primary'],
        ] as [$label, $value, $icon, $tone])
            <div class="col-md-4"><div class="card stat-card h-100 border-0"><div class="d-flex justify-content-between align-items-center"><div><div class="stat-label">{{ $label }}</div><h4 class="stat-value mb-0">{{ $value }}</h4></div><i class="bi {{ $icon }} stat-icon {{ $tone }}"></i></div></div></div>
        @endforeach
    </div>

    <div id="staff-roles" class="card border-0 shadow-sm overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th class="px-4">Account</th><th>Email</th><th>Role</th><th>Status</th><th class="text-end pe-4">Actions</th></tr></thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr class="{{ $user['active'] ? '' : 'opacity-50' }}">
                            <td class="px-4 py-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-circle text-white fw-bold d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:linear-gradient(135deg,#14b8a6,#06b6d4);">{{ strtoupper(mb_substr($user['name'], 0, 1)) }}</div>
                                    <div class="fw-bold">{{ $user['name'] }}@if ($user['uid'] === $me->id) <span class="text-muted fw-normal small">(you)</span>@endif</div>
                                </div>
                            </td>
                            <td>{{ $user['email'] }}</td>
                            <td>
                                @php($badge = ['admin' => 'bg-danger', 'therapist' => 'bg-info text-dark', 'staff' => 'bg-secondary'][$user['role']] ?? 'bg-light text-dark')
                                <span class="badge {{ $badge }} rounded-pill px-3 py-2">{{ \App\Auth\StaffUser::ROLE_LABELS[$user['role']] ?? ucfirst($user['role']) }}</span>
                            </td>
                            <td>
                                @if (in_array($user['uid'], $noLogin, true))
                                    <span class="badge bg-warning text-dark" title="This account's Firebase login was deleted. Delete the leftover record here.">No login</span>
                                @else
                                    {!! $user['active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Disabled</span>' !!}
                                @endif
                            </td>
                            <td class="text-end pe-4 text-nowrap">
                                <a href="{{ route('users.edit', $user['uid']) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                @if ($user['uid'] !== $me->id)
                                    <form method="POST" action="{{ route('users.status', $user['uid']) }}" class="d-inline" @if ($user['active']) data-confirm="Disable this account? They will no longer be able to sign in." @endif>
                                        @csrf
                                        <input type="hidden" name="active" value="{{ $user['active'] ? 0 : 1 }}">
                                        <button class="btn btn-sm {{ $user['active'] ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $user['active'] ? 'Disable' : 'Enable' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('users.destroy', $user['uid']) }}" class="d-inline" data-confirm="Delete {{ $user['name'] }} permanently? Their login and staff record are removed. To just stop them signing in, use Disable instead.">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-danger" title="Delete account"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No staff accounts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
