@extends('layouts.app')

@section('title', 'RehabPlus Analytics')

@section('content')
    <div class="topbar">
        <div class="brand">
            <div class="brand-mark">R</div>
            <div>
                <div class="brand-title">RehabPlus</div>
            </div>
        </div>
        <div class="nav">
            <a href="{{ route('dashboard') }}">Dashboard</a>
            <a href="{{ route('patients.index') }}">Patients</a>
            <a href="{{ route('appointments.index') }}">Appointments</a>
            <form method="POST" action="{{ route('logout') }}" class="inline">@csrf<button type="submit">Logout</button></form>
        </div>
    </div>

    <div style="margin-bottom: 18px;">
        <h1 style="font-size: 2.1rem; margin-bottom: 6px;">Analytics</h1>
        <p style="color: var(--muted); margin: 0;">Clinical performance trends and recovery indicators.</p>
    </div>

    <div class="stats">
        <div class="stat"><strong>Total Patients</strong><div style="font-size: 28px; margin-top: 8px;">{{ $totalPatients }}</div></div>
        <div class="stat"><strong>Avg Compliance</strong><div style="font-size: 28px; margin-top: 8px;">{{ $avgCompliance }}%</div></div>
        <div class="stat"><strong>Avg Pain</strong><div style="font-size: 28px; margin-top: 8px;">{{ $avgPain }}</div></div>
        <div class="stat"><strong>Recovery Data</strong><div style="font-size: 28px; margin-top: 8px;">{{ $hasRecoveryData ? 'Live' : 'No data' }}</div></div>
    </div>

    <div class="panel" style="margin-bottom: 24px;">
        <h3>Recovery Trends</h3>
        <div style="height: 220px; display: grid; place-items: center; background: linear-gradient(135deg, #eff6ff, #f8fafc); border-radius: 12px;">
            @if($hasRecoveryData)
                <div style="width: 100%; max-width: 700px;">
                    <p style="margin-bottom: 12px; font-weight: bold;">Patient Recovery Index</p>
                    <div style="display: flex; align-items: end; gap: 12px; height: 150px; padding: 10px; border-bottom: 2px solid #d1d5db;">
                        @foreach($recoveryValues as $value)
                            <div style="flex: 1; display: flex; flex-direction: column; justify-content: end; align-items: center; gap: 8px;">
                                <div style="width: 100%; max-width: 40px; height: {{ max(20, $value * 1.5) }}px; background: linear-gradient(180deg, #3b82f6, #1d4ed8); border-radius: 6px 6px 0 0; display: block;"></div>
                                <small>{{ number_format($value, 0) }}</small>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <p>No patient recovery data is available yet.</p>
            @endif
        </div>
    </div>

    <div class="panel">
        <h3>Condition Summary</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Condition</th>
                        <th>Patients</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($conditionCounts as $condition => $count)
                        <tr>
                            <td>{{ $condition }}</td>
                            <td>{{ $count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
