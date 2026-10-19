@extends('layouts.admin')

@section('title', 'Staff Schedule')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><p class="page-title mb-1">Staff Schedule</p><p class="page-subtitle mb-0">Plan staffing coverage, shift assignments, and clinic room usage.</p></div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger mb-4">{{ $errors->first() }}</div>
    @endif

    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header d-flex justify-content-between align-items-center"><h5 class="mb-0">Add staff shift</h5><div class="d-flex gap-2"><a href="{{ url('/schedule?week_offset='.(int)($weekOffset - 1)) }}" class="btn btn-secondary btn-sm">Previous Week</a><a href="{{ url('/schedule?week_offset='.(int)($weekOffset + 1)) }}" class="btn btn-secondary btn-sm">Next Week</a></div></div>
        <div class="card-body"><form method="post" action="{{ url('/schedule/save') }}">@csrf<div class="row g-3"><div class="col-md-3"><label class="form-label small fw-semibold">Staff member</label><select name="staff" class="form-select" required><option value="">Select staff</option>@foreach ($staffDirectory as $staff)<option value="{{ $staff['name'] }}">{{ $staff['name'] }} ({{ $staff['role'] }})</option>@endforeach</select></div><div class="col-md-3"><label class="form-label small fw-semibold">Role</label><input type="text" name="role" class="form-control" placeholder="Therapist" required></div><div class="col-md-2"><label class="form-label small fw-semibold">Day</label><select name="day" class="form-select" required><option value="Monday">Monday</option><option value="Tuesday">Tuesday</option><option value="Wednesday">Wednesday</option><option value="Thursday">Thursday</option><option value="Friday">Friday</option><option value="Saturday">Saturday</option><option value="Sunday">Sunday</option></select></div><div class="col-md-2"><label class="form-label small fw-semibold">Shift</label><input type="text" name="shift" class="form-control" placeholder="08:00 - 15:00" required></div><div class="col-md-2"><label class="form-label small fw-semibold">Room</label><input type="text" name="room" class="form-control" placeholder="Room 3"></div><div class="col-12 text-end"><button type="submit" class="btn btn-primary">Add shift</button></div></div></form></div>
    </div>

    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header"><h5 class="mb-0">Add time off</h5></div>
        <div class="card-body"><form method="post" action="{{ url('/schedule/time-off/save') }}">@csrf<div class="row g-3 align-items-end"><div class="col-md-3"><label class="form-label small fw-semibold">Staff member</label><select name="staff" class="form-select" required><option value="">Select staff</option>@foreach ($staffDirectory as $staff)<option value="{{ $staff['name'] }}">{{ $staff['name'] }}</option>@endforeach</select></div><div class="col-md-2"><label class="form-label small fw-semibold">Date</label><input type="date" name="date" class="form-control" required></div><div class="col-md-2"><label class="form-label small fw-semibold">Type</label><select name="time_off_type" class="form-select"><option value="Full day">Full day</option><option value="Specific time">Specific time</option></select></div><div class="col-md-3"><label class="form-label small fw-semibold">Time</label><input type="text" name="time" class="form-control" placeholder="09:00 - 12:00"></div><div class="col-md-2"><label class="form-label small fw-semibold">Reason</label><input type="text" name="reason" class="form-control" placeholder="Medical leave" required></div><div class="col-12 text-end"><button type="submit" class="btn btn-secondary">Add Time Off</button></div></div></form></div>
    </div>

    <div class="row g-4 mb-4">
        @php($stats = [
            ['label' => 'Staff on duty', 'value' => $staffCount, 'icon' => 'bi-people', 'tone' => 'text-primary'],
            ['label' => 'Today shifts', 'value' => $todayShifts, 'icon' => 'bi-calendar-check', 'tone' => 'text-success'],
            ['label' => 'Active rooms', 'value' => $activeRooms, 'icon' => 'bi-door-open', 'tone' => 'text-info'],
        ])
        @foreach ($stats as $stat)<div class="col-md-6 col-xl-4"><div class="card stat-card h-100 border-0"><div class="d-flex justify-content-between align-items-center"><div><div class="stat-label">{{ $stat['label'] }}</div><h4 class="stat-value mb-0">{{ $stat['value'] }}</h4></div><i class="bi {{ $stat['icon'] }} stat-icon {{ $stat['tone'] }}"></i></div></div></div>@endforeach
    </div>

    <div class="card shadow-sm mb-4 border-0"><div class="card-header"><h5 class="mb-0">Staff availability</h5></div><div class="card-body"><div class="row g-3">@foreach ($staffDirectory as $staff)@php($status = $staffAvailability[$staff['name']] ?? 'Unavailable')<div class="col-md-6 col-xl-4"><div class="border rounded-4 p-3 h-100"><div class="d-flex justify-content-between align-items-center mb-2"><strong>{{ $staff['name'] }}</strong><span class="badge bg-light text-dark">{{ $staff['role'] }}</span></div><div class="small text-muted mb-2">{{ $status === 'Available' ? 'Ready for assignment' : ($status === 'On Leave' ? 'Time off recorded' : ($status === 'Fully Booked' ? 'No room available' : 'Not scheduled')) }}</div><span class="badge {{ $status === 'Available' ? 'bg-success-subtle text-success' : ($status === 'On Leave' ? 'bg-warning-subtle text-warning' : ($status === 'Fully Booked' ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary')) }}">{{ $status }}</span></div></div>@endforeach</div></div></div>

    <div class="card shadow-sm mb-4 border-0"><div class="card-header"><h5 class="mb-0">Weekly schedule</h5></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead class="table-light"><tr><th>Staff</th>@foreach ($weekDays as $day)<th>{{ $day['label'] }}<br><small class="text-muted">{{ $day['short'] }}</small></th>@endforeach</tr></thead><tbody>@foreach ($staffDirectory as $staff)<tr><td><div class="fw-semibold">{{ $staff['name'] }}</div><small class="text-muted">{{ $staff['role'] }}</small></td>@foreach ($weekDays as $day)@php($entry = $weeklySchedule[$staff['name']][$day['label']] ?? null)<td>@if ($entry)<div class="fw-semibold text-primary">{{ $entry['shift'] }}</div><small class="text-muted">{{ $entry['room'] }}</small>@else<span class="text-muted">Off</span>@endif</td>@endforeach</tr>@endforeach</tbody></table></div></div></div>

    <div class="card shadow-sm border-0"><div class="card-header"><h5 class="mb-0">Today's staff list</h5></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead class="table-light"><tr><th>Staff</th><th>Role</th><th>Shift</th><th>Room</th><th>Status</th></tr></thead><tbody>@forelse ($todayStaffList as $entry)<tr><td>{{ $entry['name'] }}</td><td>{{ $entry['role'] }}</td><td>{{ $entry['shift'] }}</td><td>{{ $entry['room'] }}</td><td><span class="badge {{ $entry['status'] === 'Available' ? 'bg-success-subtle text-success' : ($entry['status'] === 'On Leave' ? 'bg-warning-subtle text-warning' : ($entry['status'] === 'Fully Booked' ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary')) }}">{{ $entry['status'] }}</span></td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">No staff scheduled.</td></tr>@endforelse</tbody></table></div></div></div>
@endsection
