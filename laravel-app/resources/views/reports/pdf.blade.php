<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RehabPlus report · {{ $rangeLabel }}</title>
    {{-- Self-contained print layout (no dashboard chrome); use the browser's Print > Save as PDF. --}}
    <style>
        body { font-family: Arial, sans-serif; color: #0f172a; margin: 0; padding: 24px; background: #fff; font-size: 13px; }
        h1, h2, h3, p { margin: 0 0 10px; }
        h2 { font-size: 16px; margin-top: 22px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        .header { border-bottom: 2px solid #14b8a6; padding-bottom: 12px; margin-bottom: 8px; }
        .muted { color: #64748b; font-size: 12px; }
        .stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin: 10px 0; }
        .stat { border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px; background: #f8fafc; }
        .stat strong { display: block; font-size: 11px; color: #475569; margin-bottom: 4px; }
        .stat span { font-size: 18px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #e2e8f0; padding: 6px 8px; text-align: left; font-size: 12px; }
        th { background: #f1f5f9; }
        @media print { body { padding: 0; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>RehabPlus</h1>
        <p><strong>Reports &amp; Analytics</strong></p>
        <p class="muted">Range: {{ $rangeLabel }} · Generated {{ now()->format('F j, Y g:i A') }}</p>
    </div>

    <h2>Recovery</h2>
    <div class="stats">
        <div class="stat"><strong>Active patients</strong><span>{{ $recovery['totalPatients'] }}</span></div>
        <div class="stat"><strong>Avg movement accuracy</strong><span>{{ $recovery['avgCompliance'] }}%</span></div>
        <div class="stat"><strong>Avg pain (0-10)</strong><span>{{ $recovery['avgPain'] }}</span></div>
        <div class="stat"><strong>Conditions</strong><span>{{ count($recovery['conditionCounts']) }}</span></div>
    </div>

    @if ($appointments)
        <h2>Appointments</h2>
        <div class="stats">
            <div class="stat"><strong>Total</strong><span>{{ $appointments['total'] }}</span></div>
            <div class="stat"><strong>Completed</strong><span>{{ $appointments['completed'] }}</span></div>
            <div class="stat"><strong>Upcoming</strong><span>{{ $appointments['upcoming'] }}</span></div>
            <div class="stat"><strong>Cancelled</strong><span>{{ $appointments['cancelled'] }}</span></div>
        </div>
    @endif

    @if ($billing)
        <h2>Billing</h2>
        <div class="stats">
            <div class="stat"><strong>Billed</strong><span>₱{{ number_format($billing['billed'], 2) }}</span></div>
            <div class="stat"><strong>Collected</strong><span>₱{{ number_format($billing['collected'], 2) }}</span></div>
            <div class="stat"><strong>Outstanding</strong><span>₱{{ number_format($billing['outstanding'], 2) }}</span></div>
            <div class="stat"><strong>Records</strong><span>{{ $billing['count'] }}</span></div>
        </div>
    @endif

    @if ($goals)
        <h2>Treatment goals</h2>
        <div class="stats">
            <div class="stat"><strong>Achieved (all time)</strong><span>{{ $goals['achievementRate'] === null ? '—' : $goals['achievementRate'].'%' }}</span></div>
            <div class="stat"><strong>In progress</strong><span>{{ $goals['active'] }}</span></div>
            <div class="stat"><strong>Achieved in range</strong><span>{{ $goals['achievedInRange'] }}</span></div>
            <div class="stat"><strong>Total goals</strong><span>{{ $goals['total'] }}</span></div>
        </div>
    @endif

    @if ($patients)
        <h2>Patient registrations</h2>
        <div class="stats">
            <div class="stat"><strong>Patients (filtered)</strong><span>{{ $patients['summary']['totalPatients'] }}</span></div>
            <div class="stat"><strong>Active</strong><span>{{ $patients['summary']['activePatients'] }}</span></div>
            <div class="stat"><strong>Inactive</strong><span>{{ $patients['summary']['inactivePatients'] }}</span></div>
            <div class="stat"><strong>New this month</strong><span>{{ $patients['summary']['newPatientsThisMonth'] }}</span></div>
        </div>

        <table>
            <thead><tr><th>Patient</th><th>Condition</th><th>Therapist</th><th>Registered</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($printPatients as $patient)
                    <tr>
                        <td>{{ $patient->name }}</td>
                        <td>{{ $patient->condition ?: 'Unspecified' }}</td>
                        <td>{{ $patient->assigned_to ?: 'Unassigned' }}</td>
                        <td>{{ $patient->created_at?->format('M d, Y') ?? '—' }}</td>
                        <td>{{ $patient->patient_is_active ? 'Active' : 'Inactive' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">No patient registrations match the filters.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($patients['selected']->count() > $printPatients->count())
            <p class="muted">Showing the newest {{ $printPatients->count() }} of {{ $patients['selected']->count() }}; use the CSV export for the full list.</p>
        @endif
    @endif
</body>
</html>
