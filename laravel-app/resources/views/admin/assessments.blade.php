@extends('layouts.admin')

@section('title', 'Assessments & Goals')

@section('content')
    @php($tz = config('app.timezone'))
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <p class="page-title mb-1">Assessments &amp; Goals</p>
            <p class="page-subtitle mb-0">
                @if ($focusPatient)
                    Evaluation scores and treatment goals for <a href="{{ route('patients.show', $focusPatient['uid']) }}">{{ $focusPatient['name'] }}</a>.
                @else
                    Evaluation scores and treatment goals for {{ auth()->user()->isTherapist() ? 'your' : 'all' }} patients.
                @endif
            </p>
        </div>
        @if ($patients)
            <form method="GET" action="{{ route('assessments') }}" class="d-flex gap-2 align-items-center">
                <label for="patient_filter" class="form-label small fw-semibold mb-0 text-nowrap">Show</label>
                <select id="patient_filter" name="patient" class="form-select" data-autosubmit>
                    <option value="">All patients</option>
                    @foreach ($patients as $p)
                        <option value="{{ $p['uid'] }}" @selected($selectedPatient === $p['uid'])>{{ $p['name'] }}</option>
                    @endforeach
                </select>
                <noscript><button class="btn btn-outline-primary">Show</button></noscript>
            </form>
        @endif
    </div>

    @include('partials.flash')

    <div class="row g-4 mb-4">
        @foreach ([
            ['Average score', $averageScore, 'bi-bar-chart', 'text-primary'],
            ['Goals achieved', $goalTracking, 'bi-bullseye', 'text-success'],
            ['Goals in progress', $activeGoals, 'bi-flag', 'text-warning'],
            $focusPatient ? ['Assessments', $assessmentCount, 'bi-clipboard-check', 'text-info'] : ['Active patients', $activePatients, 'bi-people', 'text-info'],
        ] as [$label, $value, $icon, $tone])
            <div class="col-md-6 col-xl-3"><div class="card stat-card h-100 border-0"><div class="d-flex justify-content-between align-items-center"><div><div class="stat-label">{{ $label }}</div><h4 class="stat-value mb-0">{{ $value }}</h4></div><i class="bi {{ $icon }} stat-icon {{ $tone }}"></i></div></div></div>
        @endforeach
    </div>

    @if (! $patients)
        <div class="alert alert-info">No patients yet. <a href="{{ route('patients.create') }}">Add a patient</a> to record assessments and goals.</div>
    @else
        <div class="row g-4 mb-4">
            <div class="col-xl-6">
                <form method="POST" action="{{ route('assessments.store') }}" class="card shadow-sm border-0 h-100">
                    @csrf
                    <div class="card-header"><h5 class="mb-0">Add assessment</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold" for="a_patient">Patient</label>
                                @include('partials.patient-select', ['id' => 'a_patient'])
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold" for="a_type">Type</label>
                                <input id="a_type" type="text" name="type" class="form-control" list="assessment-types" maxlength="60" value="{{ old('type') }}" placeholder="e.g. Range of motion" required>
                                <datalist id="assessment-types">
                                    @foreach (['Range of motion', 'Strength', 'Balance', 'Mobility', 'Pain', 'Function', 'Gait'] as $t)<option value="{{ $t }}">@endforeach
                                </datalist>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold" for="a_score">Score (%)</label>
                                <input id="a_score" type="number" name="score" class="form-control" min="0" max="100" value="{{ old('score') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold" for="a_date">Date</label>
                                <input id="a_date" type="date" name="date" class="form-control" value="{{ old('date', now($tz)->format('Y-m-d')) }}" max="{{ now($tz)->format('Y-m-d') }}" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold" for="a_notes">Findings</label>
                                <textarea id="a_notes" name="notes" class="form-control" rows="2" maxlength="1000" placeholder="e.g. Knee flexion 110°, up from 95°">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent text-end"><button class="btn btn-primary">Save assessment</button></div>
                </form>
            </div>

            <div class="col-xl-6" id="goal-form">
                <form method="POST" action="{{ route('goals.store') }}" class="card shadow-sm border-0 h-100">
                    @csrf
                    <div class="card-header"><h5 class="mb-0">Add goal</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold" for="g_patient">Patient</label>
                                @include('partials.patient-select', ['id' => 'g_patient'])
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold" for="g_target">Target date</label>
                                <input id="g_target" type="date" name="target_date" class="form-control" value="{{ old('target_date') }}">
                            </div>
                            <div class="col-md-9">
                                <label class="form-label small fw-semibold" for="g_title">Goal</label>
                                <input id="g_title" type="text" name="title" class="form-control" maxlength="150" value="{{ old('title') }}" placeholder="e.g. Climb stairs without support" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold" for="g_progress">Progress</label>
                                <div class="input-group"><input id="g_progress" type="number" name="progress" class="form-control" min="0" max="100" value="{{ old('progress', 0) }}"><span class="input-group-text">%</span></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold" for="g_details">Details</label>
                                <textarea id="g_details" name="details" class="form-control" rows="2" maxlength="1000">{{ old('details') }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent text-end"><button class="btn btn-primary">Add goal</button></div>
                </form>
            </div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card shadow-sm border-0">
                <div class="card-header"><h5 class="mb-0">{{ $focusPatient ? 'Assessments' : 'Latest assessments' }}</h5></div>
                <div class="card-body p-0"><div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light"><tr><th>Date</th><th>Patient</th><th>Type</th><th>Score</th><th>Therapist</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($assessments as $a)
                                <tr>
                                    <td class="small text-nowrap">{{ $a['assessedAt']?->setTimezone($tz)->format('M j, Y') }}</td>
                                    <td><a href="{{ route('patients.show', $a['patientUid']) }}">{{ $a['patientName'] }}</a></td>
                                    <td>{{ $a['type'] }}@if ($a['notes'])<div class="small text-muted" style="white-space:pre-line">{{ $focusPatient ? $a['notes'] : Str::limit($a['notes'], 80) }}</div>@endif</td>
                                    <td class="fw-bold">{{ $a['score'] }}%</td>
                                    <td class="small text-muted">{{ $a['therapistName'] }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('records.delete', [$a['patientUid'], 'assessments', $a['id']]) }}" data-confirm="Delete this assessment?">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">No assessments {{ $focusPatient ? 'for '.$focusPatient['name'].' ' : '' }}yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div></div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card shadow-sm border-0">
                <div class="card-header"><h5 class="mb-0">Goals</h5></div>
                <div class="card-body">
                    @forelse ($goals as $goal)
                        @include('partials.goal', ['goal' => $goal, 'canManage' => true, 'showPatient' => true])
                    @empty
                        <p class="text-muted mb-0">No goals yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
