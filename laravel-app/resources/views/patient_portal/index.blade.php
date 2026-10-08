@extends('layouts.app')

@section('title', 'Patient Portal')

@section('content')
    <div class="topbar">
        <h1>Patient Portal</h1>
        <form method="POST" action="{{ route('logout') }}" class="inline">@csrf<button type="submit">Logout</button></form>
    </div>

    <div class="panel">
        <h2>Welcome, {{ $patient->name }}</h2>
        <p><strong>Condition:</strong> <span class="badge">{{ $patient->condition }}</span></p>
        <p><strong>Therapy plan:</strong> {{ $patient->therapy_plan ?? 'No plan recorded yet.' }}</p>
    </div>

    <div class="stats" style="margin-top: 24px;">
        <div class="stat"><strong>Compliance</strong><div style="font-size: 30px; margin-top: 8px;">{{ $compliance }}%</div></div>
        <div class="stat"><strong>Avg Pain</strong><div style="font-size: 30px; margin-top: 8px;">{{ $avgPain }}</div></div>
        <div class="stat"><strong>Sessions</strong><div style="font-size: 30px; margin-top: 8px;">{{ $records->count() }}</div></div>
    </div>

    <div class="panel">
        <h3>Upcoming Appointments</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Session</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($appointments as $appointment)
                        <tr>
                            <td>{{ $appointment->date }}</td>
                            <td>{{ $appointment->time }}</td>
                            <td>{{ $appointment->session }}</td>
                            <td>{{ $appointment->status }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">No appointments scheduled.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
