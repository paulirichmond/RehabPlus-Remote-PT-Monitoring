@extends('layouts.app')

@section('title', 'Patient Statistics')

@section('content')
    <div style="margin-bottom: 18px;">
        <h1 class="page-title">Patient Statistics</h1>
        <p class="page-subtitle">Real patient registration, condition distribution, and recovery trends.</p>
    </div>

    <div class="panel" style="padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
        <form method="GET" action="{{ route('patient-statistics') }}" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; align-items:end;">
            <div>
                <label style="display:block; font-size:.8rem; font-weight:700; color: var(--muted); margin-bottom: .5rem;">Range</label>
                <select name="range">
                    <option value="last_12_months" {{ $filters['range'] === 'last_12_months' ? 'selected' : '' }}>Last 12 Months</option>
                    <option value="today" {{ $filters['range'] === 'today' ? 'selected' : '' }}>Today</option>
                    <option value="week" {{ $filters['range'] === 'week' ? 'selected' : '' }}>This Week</option>
                    <option value="month" {{ $filters['range'] === 'month' ? 'selected' : '' }}>This Month</option>
                    <option value="year" {{ $filters['range'] === 'year' ? 'selected' : '' }}>This Year</option>
                    <option value="custom" {{ $filters['range'] === 'custom' ? 'selected' : '' }}>Custom Range</option>
                </select>
            </div>

            <div>
                <label style="display:block; font-size:.8rem; font-weight:700; color: var(--muted); margin-bottom: .5rem;">From</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}">
            </div>

            <div>
                <label style="display:block; font-size:.8rem; font-weight:700; color: var(--muted); margin-bottom: .5rem;">To</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}">
            </div>

            <div>
                <label style="display:block; font-size:.8rem; font-weight:700; color: var(--muted); margin-bottom: .5rem;">Condition</label>
                <select name="condition">
                    <option value="">All Conditions</option>
                    @foreach($conditionOptions as $condition)
                        <option value="{{ $condition }}" {{ $filters['condition'] === $condition ? 'selected' : '' }}>{{ $condition ?: 'Unspecified' }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="display:block; font-size:.8rem; font-weight:700; color: var(--muted); margin-bottom: .5rem;">Therapist</label>
                <select name="therapist">
                    <option value="">All Therapists</option>
                    @foreach($therapistOptions as $therapist)
                        <option value="{{ $therapist }}" {{ $filters['therapist'] === $therapist ? 'selected' : '' }}>{{ $therapist ?: 'Unassigned' }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="display:block; font-size:.8rem; font-weight:700; color: var(--muted); margin-bottom: .5rem;">Status</label>
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="active" {{ $filters['status'] === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ $filters['status'] === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="submit" class="btn">Apply Filters</button>
                <a href="{{ route('patient-statistics') }}" class="btn btn-secondary" style="display:inline-flex; align-items:center; justify-content:center;">Reset</a>
            </div>
        </form>
    </div>

    <div class="stats">
        <div class="stat">
            <div class="stat-label">Total Patients</div>
            <div class="stat-value" style="font-size:2.4rem;">{{ $summary['totalPatients'] }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Active Patients</div>
            <div class="stat-value" style="font-size:2.4rem;">{{ $summary['activePatients'] }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Inactive Patients</div>
            <div class="stat-value" style="font-size:2.4rem;">{{ $summary['inactivePatients'] }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">New Patients This Month</div>
            <div class="stat-value" style="font-size:2.4rem;">{{ $summary['newPatientsThisMonth'] }}</div>
        </div>
    </div>

    <div class="panel" style="padding: 1.25rem; margin-bottom: 1.5rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom: 1rem;">
            <div>
                <h3 style="margin:0;">Patient Registration Trend</h3>
                <small style="color: var(--muted);">{{ $rangeLabel }}</small>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <a href="{{ route('patient-statistics.export.csv') }}?{{ http_build_query(request()->query()) }}" class="btn btn-secondary" style="display:inline-flex; align-items:center; justify-content:center;">Export CSV</a>
                <a href="{{ route('patient-statistics.export.pdf') }}?{{ http_build_query(request()->query()) }}" target="_blank" class="btn btn-secondary" style="display:inline-flex; align-items:center; justify-content:center;">Export PDF</a>
            </div>
        </div>
        <div style="height: 320px;">
            <canvas id="patientTrendChart"></canvas>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 1.5rem;">
        <div class="panel" style="padding: 1.25rem; min-height: 360px;">
            <h3 style="margin-bottom: 1rem;">Patients by Condition</h3>
            <div style="height: 280px;">
                <canvas id="conditionChart"></canvas>
            </div>
        </div>

        <div class="panel" style="padding: 1.25rem; min-height: 360px;">
            <h3 style="margin-bottom: 1rem;">Patient Growth</h3>
            <div style="padding: 1rem 0;">
                <div style="font-size: 2rem; font-weight:800; margin-bottom: 8px;">{{ $growthSummary['currentPatients'] }}</div>
                <div style="color: var(--muted); margin-bottom: 18px;">Patients in selected period</div>

                @if($growthSummary['comparisonMessage'])
                    <div style="background: #f0fdfa; border: 1px solid #ccfbf1; border-radius: 16px; padding: 1rem; color: var(--primary-dark); font-weight: 600;">
                        {{ $growthSummary['comparisonMessage'] }}
                    </div>
                @else
                    <div style="background: #f0fdfa; border: 1px solid #ccfbf1; border-radius: 16px; padding: 1rem; color: var(--primary-dark); font-weight: 700;">
                        {{ $growthSummary['trendText'] }}
                    </div>
                @endif

                <div style="margin-top: 1.2rem; color: var(--muted);">
                    Current period: {{ $growthSummary['currentPeriodLabel'] }}<br>
                    Previous period: {{ $growthSummary['previousPeriodLabel'] }}
                </div>
            </div>
        </div>
    </div>

    <div class="panel" style="padding: 0; overflow:hidden;">
        <div style="padding: 1.25rem 1.5rem; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
            <h3 style="margin:0;">Recent Patient Registrations</h3>
            <small style="color: var(--muted);">Newest records first</small>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Patient Name</th>
                        <th>Condition</th>
                        <th>Assigned Therapist</th>
                        <th>Registration Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentPatients as $patient)
                        <tr>
                            <td><strong>{{ $patient->name }}</strong></td>
                            <td>{{ $patient->condition ?: 'Unspecified' }}</td>
                            <td>{{ $patient->assigned_to ?: 'Unassigned' }}</td>
                            <td>{{ $patient->created_at ? $patient->created_at->format('M d, Y') : '—' }}</td>
                            <td>
                                @if($patient->patient_is_active === 1)
                                    <span class="badge" style="background:#dcfce7; color:#166534;">Active</span>
                                @else
                                    <span class="badge" style="background:#fef3c7; color:#92400e;">Inactive</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align:center; color: var(--muted); padding: 1.5rem;">No patient registrations match the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($recentPatients->hasPages())
            <div style="padding: 1rem 1.25rem;">
                {{ $recentPatients->appends(request()->query())->links() }}
            </div>
        @endif
    </div>

    <script>
        const patientTrendChart = new Chart(document.getElementById('patientTrendChart'), {
            type: 'line',
            data: {
                labels: @json($registrationTrend['labels']),
                datasets: [{
                    label: 'New Patients',
                    data: @json($registrationTrend['values']),
                    borderColor: '#14b8a6',
                    backgroundColor: 'rgba(20, 184, 166, 0.15)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointBackgroundColor: '#14b8a6'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });

        const conditionChart = new Chart(document.getElementById('conditionChart'), {
            type: 'doughnut',
            data: {
                labels: @json($conditionChart['labels']),
                datasets: [{
                    data: @json($conditionChart['values']),
                    backgroundColor: ['#14b8a6', '#2dd4bf', '#0ea5e9', '#8b5cf6', '#f59e0b', '#ef4444', '#10b981', '#f97316'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
@endsection
