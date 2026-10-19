@extends('layouts.admin')

@section('title', 'Appointments')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><p class="page-title mb-1">Appointments</p><p class="page-subtitle mb-0">Manage scheduled patient appointments and sessions.</p></div>
        <a href="{{ route('appointments.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Schedule Appointment</a>
    </div>

    <div class="row g-4 mb-4">
        @php($stats = [
            ['label' => 'Today', 'value' => $todayAppointments, 'icon' => 'bi-calendar-day', 'tone' => 'text-primary'],
            ['label' => 'Upcoming', 'value' => $upcomingAppointments, 'icon' => 'bi-calendar-event', 'tone' => 'text-success'],
            ['label' => 'Active Patients', 'value' => $activePatients, 'icon' => 'bi-people-fill', 'tone' => 'text-info'],
            ['label' => 'Completed', 'value' => $completedToday, 'icon' => 'bi-check-circle-fill', 'tone' => 'text-success'],
        ])
        @foreach ($stats as $stat)
            <div class="col-md-6 col-xl-3"><div class="card stat-card h-100 border-0"><div class="d-flex justify-content-between align-items-center"><div><div class="stat-label">{{ $stat['label'] }}</div><h3 class="stat-value mb-0">{{ $stat['value'] }}</h3></div><i class="bi {{ $stat['icon'] }} stat-icon {{ $stat['tone'] }}"></i></div></div></div>
        @endforeach
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead class="table-light"><tr><th>Patient</th><th>Therapist</th><th>Session</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead><tbody>@forelse($appointments as $appointment)<tr><td>{{ $appointment->patient }}</td><td>{{ $appointment->therapist }}</td><td>{{ $appointment->session }}</td><td>{{ \Carbon\Carbon::parse($appointment->date)->format('M d, Y') }} @ {{ $appointment->time }}</td><td><span class="badge {{ $appointment->status === 'Completed' ? 'bg-success-subtle text-success' : ($appointment->status === 'Pending' ? 'bg-warning-subtle text-warning' : 'bg-info-subtle text-info') }}">{{ $appointment->status }}</span></td><td><a href="{{ route('appointments.show', $appointment->id) }}" class="btn btn-sm btn-outline-secondary me-1">View</a><a href="{{ route('appointments.edit', $appointment->id) }}" class="btn btn-sm btn-outline-primary me-1">Edit</a><form method="POST" action="{{ route('appointments.delete', $appointment->id) }}" data-confirm="Delete appointment?" class="d-inline">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">No appointments available.</td></tr>@endforelse</tbody></table></div></div>
    </div>
@endsection
