@extends('layouts.app')

@section('title', $appointment ? 'Edit Appointment' : 'New Appointment')

@section('content')
    <div class="topbar">
        <div>
            <a href="{{ route('appointments.index') }}">← Back to appointments</a>
        </div>
    </div>

    <div class="panel" style="max-width: 900px; margin: 0 auto;">
        <h1>{{ $appointment ? 'Edit Appointment' : 'Schedule Appointment' }}</h1>

        <form method="POST" action="{{ $appointment ? route('appointments.update', $appointment->id) : route('appointments.store') }}" style="display:grid; gap:16px;">
            @csrf
            @if($appointment)
                @method('PUT')
            @endif

            <div class="grid-two">
                <label style="display:grid; gap:6px; font-weight:bold;">
                    Patient name
                    <input type="text" name="patient" value="{{ old('patient', $appointment->patient ?? '') }}" required>
                </label>

                <label style="display:grid; gap:6px; font-weight:bold;">
                    Therapist
                    <select name="therapist" required>
                        <option value="">Select therapist</option>
                        @foreach($therapists as $therapist)
                            <option value="{{ $therapist['name'] ?? $therapist['email'] ?? '' }}" {{ old('therapist', $appointment->therapist ?? '') === ($therapist['name'] ?? $therapist['email']) ? 'selected' : '' }}>
                                {{ $therapist['name'] ?? 'Therapist' }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="grid-two">
                <label style="display:grid; gap:6px; font-weight:bold;">
                    Condition
                    <input type="text" name="patient_condition" value="{{ old('patient_condition', $appointment->patient_condition ?? '') }}" required>
                </label>

                <label style="display:grid; gap:6px; font-weight:bold;">
                    Contact
                    <input type="text" name="contact" value="{{ old('contact', $appointment->contact ?? '') }}" required>
                </label>
            </div>

            <div class="grid-two">
                <label style="display:grid; gap:6px; font-weight:bold;">
                    Date
                    <input type="date" name="date" value="{{ old('date', $appointment->date ?? '') }}" required>
                </label>

                <label style="display:grid; gap:6px; font-weight:bold;">
                    Time
                    <input type="time" name="time" value="{{ old('time', $appointment->time ?? '') }}" required>
                </label>
            </div>

            <label style="display:grid; gap:6px; font-weight:bold;">
                Session
                <input type="text" name="session" value="{{ old('session', $appointment->session ?? '') }}" required>
            </label>

            <label style="display:grid; gap:6px; font-weight:bold;">
                Notes
                <textarea name="notes">{{ old('notes', $appointment->notes ?? '') }}</textarea>
            </label>

            <label style="display:grid; gap:6px; font-weight:bold;">
                Status
                <select name="status">
                    <option value="Upcoming" {{ old('status', $appointment->status ?? 'Upcoming') === 'Upcoming' ? 'selected' : '' }}>Upcoming</option>
                    <option value="Completed" {{ old('status', $appointment->status ?? 'Upcoming') === 'Completed' ? 'selected' : '' }}>Completed</option>
                    <option value="Cancelled" {{ old('status', $appointment->status ?? 'Upcoming') === 'Cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </label>

            <button type="submit" class="btn">{{ $appointment ? 'Update Appointment' : 'Create Appointment' }}</button>
        </form>
    </div>
@endsection
