@extends('layouts.admin')

@section('title', 'Therapy Notes & Care Plans')

@section('content')
    @php($tz = config('app.timezone'))
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <p class="page-title mb-1">Therapy Notes &amp; Care Plans</p>
            <p class="page-subtitle mb-0">Progress notes for {{ auth()->user()->isTherapist() ? 'your' : 'all' }} patients. Notes are visible to clinic staff only, not in the patient's app.</p>
        </div>
    </div>

    @include('partials.flash')

    <div class="row g-4 mb-4">
        <div class="col-md-6"><div class="card stat-card h-100 border-0"><div class="d-flex justify-content-between align-items-center"><div><div class="stat-label">Notes in the last 7 days</div><h4 class="stat-value mb-0">{{ $recentCount }}</h4></div><i class="bi bi-journal-check stat-icon text-primary"></i></div></div></div>
        <div class="col-md-6"><div class="card stat-card h-100 border-0"><div class="d-flex justify-content-between align-items-center"><div><div class="stat-label">Notes dated today</div><h4 class="stat-value mb-0">{{ $todayNotes }}</h4></div><i class="bi bi-calendar-event stat-icon text-success"></i></div></div></div>
    </div>

    @if (! $patients)
        <div class="alert alert-info">No patients yet. <a href="{{ route('patients.create') }}">Add a patient</a> to write therapy notes.</div>
    @else
        <form method="POST" action="{{ route('notes.store') }}" class="card shadow-sm mb-4 border-0">
            @csrf
            <div class="card-header"><h5 class="mb-0">Add therapy note</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold" for="n_patient">Patient</label>
                        @include('partials.patient-select', ['id' => 'n_patient'])
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold" for="n_topic">Topic</label>
                        <input id="n_topic" type="text" name="topic" class="form-control" maxlength="100" value="{{ old('topic') }}" placeholder="e.g. Mobility progress" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold" for="n_date">Date</label>
                        <input id="n_date" type="date" name="date" class="form-control" value="{{ old('date', now($tz)->format('Y-m-d')) }}" max="{{ now($tz)->format('Y-m-d') }}" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold" for="n_text">Care plan / note</label>
                        <textarea id="n_text" name="text" class="form-control" rows="4" maxlength="2000" placeholder="Write the patient progress note here..." required>{{ old('text') }}</textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent text-end"><button class="btn btn-primary">Save note</button></div>
        </form>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-header"><h5 class="mb-0">Latest notes</h5></div>
        <div class="card-body p-0"><div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th>Date</th><th>Patient</th><th>Topic</th><th>Note</th><th>Therapist</th><th></th></tr></thead>
                <tbody>
                    @forelse ($notes as $n)
                        <tr>
                            <td class="small text-nowrap">{{ $n['noteDate']?->setTimezone($tz)->format('M j, Y') }}</td>
                            <td><a href="{{ route('patients.show', $n['patientUid']) }}">{{ $n['patientName'] }}</a></td>
                            <td class="fw-semibold">{{ $n['topic'] }}</td>
                            <td style="white-space: pre-wrap; max-width: 480px;">{{ $n['text'] }}</td>
                            <td class="small text-muted">{{ $n['therapistName'] }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('records.delete', [$n['patientUid'], 'notes', $n['id']]) }}" data-confirm="Delete this note?">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No notes yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div>
    </div>
@endsection
