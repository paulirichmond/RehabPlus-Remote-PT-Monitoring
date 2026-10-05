<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Statistics</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #0f172a;
            margin: 0;
            padding: 24px;
            background: white;
        }
        h1, h2, h3, p { margin: 0 0 12px; }
        .header {
            border-bottom: 2px solid #14b8a6;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .subtitle {
            color: #64748b;
            font-size: 12px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin: 20px 0;
        }
        .stat {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px;
            background: #f8fafc;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        th, td {
            border: 1px solid #e2e8f0;
            padding: 8px;
            text-align: left;
            font-size: 12px;
        }
        th {
            background: #f1f5f9;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>RehabPlus</h1>
        <h2>Patient Statistics</h2>
        <p class="subtitle">Range: {{ $rangeLabel }} | Generated: {{ now()->format('F j, Y') }}</p>
    </div>

    <div class="stats">
        <div class="stat">
            <strong>Total Patients</strong><br>
            {{ $summary['totalPatients'] }}
        </div>
        <div class="stat">
            <strong>Active Patients</strong><br>
            {{ $summary['activePatients'] }}
        </div>
        <div class="stat">
            <strong>Inactive Patients</strong><br>
            {{ $summary['inactivePatients'] }}
        </div>
        <div class="stat">
            <strong>New This Month</strong><br>
            {{ $summary['newPatientsThisMonth'] }}
        </div>
    </div>

    <h3>Recent Registrations</h3>
    <table>
        <thead>
            <tr>
                <th>Patient</th>
                <th>Condition</th>
                <th>Therapist</th>
                <th>Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($recentPatients as $patient)
                <tr>
                    <td>{{ $patient->name }}</td>
                    <td>{{ $patient->condition ?: 'Unspecified' }}</td>
                    <td>{{ $patient->assigned_to ?: 'Unassigned' }}</td>
                    <td>{{ $patient->created_at ? $patient->created_at->format('M d, Y') : '—' }}</td>
                    <td>{{ ($patient->patient_is_active ?? 0) ? 'Active' : 'Inactive' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
