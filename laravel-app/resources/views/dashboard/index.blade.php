@extends('layouts.app')

@section('title', 'RehabPlus Dashboard')

@section('content')
    <div class="topbar">
        <div class="brand">
            <div class="brand-mark">R</div>
            <div>
                <div class="brand-title">RehabPlus</div>
            </div>
        </div>
        <div class="nav">
            <a href="{{ route('patients.index') }}">Patients</a>
            <a href="{{ route('appointments.index') }}">Appointments</a>
            <a href="{{ route('analytics') }}">Analytics</a>
            <a href="{{ route('users.index') }}">Users</a>
            <form method="POST" action="{{ route('logout') }}" class="inline">@csrf<button type="submit">Logout</button></form>
        </div>
    </div>

    <div style="margin-bottom: 18px;">
        <h1 style="font-size: 2.1rem; margin-bottom: 6px;">RehabPlus Dashboard</h1>
        <p style="color: var(--muted); margin: 0;">Operational overview for patient care, appointments, and progress.</p>
    </div>

    <div class="stats">
        <div class="stat"><strong>Total Patients</strong><div style="font-size:30px; margin-top:8px;">{{ $totalPatients }}</div></div>
        <div class="stat"><strong>Avg Compliance</strong><div style="font-size:30px; margin-top:8px;">{{ $avgCompliance }}%</div></div>
        <div class="stat"><strong>Avg Pain</strong><div style="font-size:30px; margin-top:8px;">{{ $avgPain }}</div></div>
        <div class="stat"><strong>Recovery Data</strong><div style="font-size:30px; margin-top:8px;">{{ $hasRecoveryData ? 'Live' : 'No data' }}</div></div>
    </div>

    <div class="panel" style="margin-top: 24px;">
        <div class="toolbar">
            <h3 style="margin: 0;">Recent Exercise Records</h3>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Exercise</th>
                        <th>Completed</th>
                        <th>Pain</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentRecords as $record)
                        <tr>
                            <td>{{ $record['patient_name'] ?? 'Unknown' }}</td>
                            <td>{{ $record['exercise_name'] }}</td>
                            <td>{{ $record['sets_completed'] }}/{{ $record['sets_prescribed'] }}</td>
                            <td>{{ $record['pain_level'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
