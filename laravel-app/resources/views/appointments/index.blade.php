@extends('layouts.app')

@section('title', 'Appointments')

@section('content')
    <div class="topbar">
        <h1>Appointments</h1>
        <div class="nav">
            <a href="{{ route('dashboard') }}">Dashboard</a>
            <a href="{{ route('patients.index') }}">Patients</a>
            <a class="btn" href="{{ route('appointments.create') }}">Schedule Appointment</a>
        </div>
    </div>

    <div class="stats">
        <div class="stat"><strong>Today</strong><div style="font-size:28px; margin-top:8px;">{{ $todayAppointments }}</div></div>
        <div class="stat"><strong>Upcoming</strong><div style="font-size:28px; margin-top:8px;">{{ $upcomingAppointments }}</div></div>
        <div class="stat"><strong>Active Patients</strong><div style="font-size:28px; margin-top:8px;">{{ $activePatients }}</div></div>
        <div class="stat"><strong>Completed</strong><div style="font-size:28px; margin-top:8px;">{{ $completedToday }}</div></div>
    </div>

    <div class="panel">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Therapist</th>
                        <th>Session</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($appointments as $appointment)
                        <tr>
                            <td>{{ $appointment->patient }}</td>
                            <td>{{ $appointment->therapist }}</td>
                            <td>{{ $appointment->session }}</td>
                            <td>{{ \Carbon\Carbon::parse($appointment->date)->format('M d, Y') }} @ {{ $appointment->time }}</td>
                            <td><span class="status">{{ $appointment->status }}</span></td>
                            <td>
                                <div class="nav">
                                    <a href="{{ route('appointments.show', $appointment->id) }}">View</a>
                                    <a href="{{ route('appointments.edit', $appointment->id) }}">Edit</a>
                                    <form method="POST" action="{{ route('appointments.delete', $appointment->id) }}" onsubmit="return confirm('Delete appointment?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
