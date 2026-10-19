@extends('layouts.admin')

@section('title', 'Patients')

@section('content')
    @php($me = auth()->user())
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <p class="page-title mb-1">{{ $showArchived ? 'Archived Patients' : 'Patients' }}</p>
            <p class="page-subtitle mb-0">
                @if ($me->isTherapist())
                    Patients assigned to you. They sign in to the RehabPlus app with the email shown here.
                @else
                    Every patient registered with the clinic. They sign in to the RehabPlus app with the email shown here.
                @endif
            </p>
        </div>
        @if ($me->isClinician())
            <a href="{{ route('patients.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Add Patient
            </a>
        @endif
    </div>

    @include('partials.flash')

    <div class="card shadow-sm border-0">
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('patients.index') }}" class="row g-2 align-items-end">
                @if ($showArchived)
                    <input type="hidden" name="archived" value="1">
                @endif
                <div class="col-md-5">
                    <label for="patient-search" class="form-label small fw-semibold">Search patients</label>
                    <input id="patient-search" type="search" name="search" class="form-control" value="{{ $search }}" placeholder="Name, email or condition">
                </div>
                <div class="col-md-4">
                    <label for="condition-filter" class="form-label small fw-semibold">Condition</label>
                    <select id="condition-filter" name="condition" class="form-select">
                        <option value="">All conditions</option>
                        @foreach ($conditions as $condition)
                            <option value="{{ $condition }}" @selected($selectedCondition === $condition)>{{ $condition }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                    <a href="{{ route('patients.index', $showArchived ? ['archived' => 1] : []) }}" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>Patient</th><th>Condition</th><th>Therapist</th><th>Added</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($data as $patient)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-bold" style="width:38px;height:38px;font-size:.8rem;background:#0e9aaa;flex-shrink:0;">{{ strtoupper(mb_substr($patient['name'], 0, 1)) }}</span>
                                        <div>
                                            <a href="{{ route('patients.show', $patient['uid']) }}" class="fw-semibold text-decoration-none">{{ $patient['name'] }}</a>
                                            <div class="text-muted small">{{ $patient['email'] }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-muted small">{{ $patient['condition'] }}</td>
                                <td class="text-muted small">{{ $patient['therapistName'] ?: 'Unassigned' }}</td>
                                <td class="text-muted small">{{ $patient['createdAt']?->setTimezone(config('app.timezone'))->format('M j, Y') ?? '—' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('patients.show', $patient['uid']) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                    @if ($me->isAdmin() || $patient['therapistId'] === $me->id)
                                        <a href="{{ route('patients.edit', $patient['uid']) }}" class="btn btn-sm btn-outline-primary ms-1">Edit</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-people fs-3 d-block mb-2 opacity-50"></i>No patients found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3 small">
        @if ($showArchived)
            <a href="{{ route('patients.index') }}"><i class="bi bi-arrow-left me-1"></i>Back to current patients</a>
        @else
            <a href="{{ route('patients.index', ['archived' => 1]) }}" class="text-muted"><i class="bi bi-archive me-1"></i>Show archived patients</a>
        @endif
    </div>
@endsection
