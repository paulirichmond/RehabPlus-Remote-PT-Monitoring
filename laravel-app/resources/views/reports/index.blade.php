@extends('layouts.admin')

@section('title', 'Reports & Analytics')

@section('content')
    @php
        $shows = fn (string $section) => in_array($section, $sections, true);
        $peso = fn (float $amount) => '₱'.number_format($amount, 2);
        $sectionNames = [
            'recovery' => 'Recovery',
            'appointments' => 'Appointments',
            'billing' => 'Billing',
            'goals' => 'Treatment goals',
            'patients' => 'Patient registrations',
        ];
        $patientFilters = $patients['filters'] ?? null;
    @endphp

    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-4">
        <div>
            <p class="page-title mb-1">Reports &amp; Analytics</p>
            <p class="page-subtitle mb-0">
                {{ auth()->user()->isTherapist() ? 'Your patients' : 'The whole clinic' }} · {{ $rangeLabel }}
            </p>
        </div>
        @if ($shows('patients'))
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('analytics.export.csv', request()->query()) }}" class="btn btn-outline-primary">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV
                </a>
                <a href="{{ route('analytics.export.pdf', request()->query()) }}" class="btn btn-primary" target="_blank" rel="noopener">
                    <i class="bi bi-printer me-1"></i>Printable PDF
                </a>
            </div>
        @endif
    </div>

    {{-- Filters: the date range applies to every dated section; the rest to patient registrations. --}}
    @if (count(array_diff($sections, ['recovery'])) > 0)
        <form method="GET" action="{{ route('analytics') }}" class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="range" class="form-label small fw-semibold">Date range</label>
                        <select id="range" name="range" class="form-select">
                            @foreach (['last_12_months' => 'Last 12 months', 'today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'year' => 'This year', 'custom' => 'Custom range'] as $value => $label)
                                <option value="{{ $value }}" @selected($filters['range'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <label for="from" class="form-label small fw-semibold">From <span class="fw-normal text-muted">(custom range)</span></label>
                        <input id="from" type="date" name="from" class="form-control" value="{{ $filters['from'] }}">
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <label for="to" class="form-label small fw-semibold">To <span class="fw-normal text-muted">(custom range)</span></label>
                        <input id="to" type="date" name="to" class="form-control" value="{{ $filters['to'] }}">
                    </div>

                    @if ($patientFilters)
                        <div class="col-12 mb-n2"><span class="small fw-semibold text-muted text-uppercase">Patient registration filters</span></div>
                        <div class="col-md-4">
                            <label for="condition" class="form-label small fw-semibold">Condition</label>
                            <select id="condition" name="condition" class="form-select">
                                <option value="">All conditions</option>
                                @foreach ($patients['conditionOptions'] as $condition)
                                    <option value="{{ $condition }}" @selected($patientFilters['condition'] === $condition)>{{ $condition }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="therapist" class="form-label small fw-semibold">Therapist</label>
                            <select id="therapist" name="therapist" class="form-select">
                                <option value="">All therapists</option>
                                @foreach ($patients['therapistOptions'] as $therapist)
                                    <option value="{{ $therapist }}" @selected($patientFilters['therapist'] === $therapist)>{{ $therapist }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="status" class="form-label small fw-semibold">Patient status</label>
                            <select id="status" name="status" class="form-select">
                                <option value="">All statuses</option>
                                <option value="active" @selected($patientFilters['status'] === 'active')>Active</option>
                                <option value="inactive" @selected($patientFilters['status'] === 'inactive')>Inactive</option>
                            </select>
                        </div>
                    @endif
                </div>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                    <small class="text-muted">
                        The date range applies to appointments, billing, goals achieved and patient registrations.
                    </small>
                    <div class="d-flex gap-2">
                        <a href="{{ route('analytics') }}" class="btn btn-outline-secondary">Reset</a>
                        <button type="submit" class="btn btn-primary">Apply</button>
                    </div>
                </div>
            </div>
        </form>
    @endif

    @if (count($sections) > 1)
        <nav class="d-flex flex-wrap gap-2 mb-4" aria-label="Report sections">
            @foreach ($sections as $section)
                <a href="#{{ $section }}" class="btn btn-sm btn-outline-secondary rounded-pill">{{ $sectionNames[$section] }}</a>
            @endforeach
        </nav>
    @endif

    {{-- Recovery --}}
    <section id="recovery" class="mb-5">
        <h2 class="h5 fw-bold mb-3">Recovery</h2>
        <div class="row g-4 mb-4">
            @foreach ([
                ['Active patients', $recovery['totalPatients'], 'bi-people-fill', 'text-primary'],
                ['Avg movement accuracy', $recovery['avgCompliance'].'%', 'bi-graph-up-arrow', 'text-success'],
                ['Avg pain (0-10)', $recovery['avgPain'], 'bi-activity', 'text-warning'],
                ['Session data', $recovery['hasRecoveryData'] ? 'Live' : 'No data yet', 'bi-heart-pulse', 'text-info'],
            ] as [$label, $value, $icon, $tone])
                <div class="col-md-6 col-xl-3"><div class="card stat-card h-100 border-0"><div class="d-flex justify-content-between align-items-center"><div><div class="stat-label">{{ $label }}</div><h3 class="stat-value mb-0">{{ $value }}</h3></div><i class="bi {{ $icon }} stat-icon {{ $tone }}"></i></div></div></div>
            @endforeach
        </div>
        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header"><h3 class="h6 mb-0">Recovery score by patient</h3></div>
                    <div class="card-body">
                        @if ($recovery['hasRecoveryData'])
                            <div style="height: 280px;"><canvas id="recoveryChart"></canvas></div>
                            <p class="small text-muted mb-0 mt-2">Movement accuracy minus a pain penalty (5 points per pain level), 0-100, from the sessions patients record in the app.</p>
                        @else
                            <p class="text-muted mb-0">No exercise sessions recorded yet.</p>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header"><h3 class="h6 mb-0">Patients by condition</h3></div>
                    <div class="card-body p-0"><div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="table-light"><tr><th>Condition</th><th class="text-end">Patients</th></tr></thead>
                            <tbody>
                                @forelse ($recovery['conditionCounts'] as $condition => $count)
                                    <tr><td>{{ $condition }}</td><td class="text-end">{{ $count }}</td></tr>
                                @empty
                                    <tr><td colspan="2" class="text-muted text-center py-3">No patients yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div></div>
                </div>
            </div>
        </div>
    </section>

    {{-- Appointments --}}
    @if ($appointments)
        <section id="appointments" class="mb-5">
            <h2 class="h5 fw-bold mb-3">Appointments <small class="text-muted fw-normal">· {{ $rangeLabel }}</small></h2>
            <div class="row g-4 mb-4">
                @foreach ([
                    ['Total', $appointments['total'], 'bi-calendar-event', 'text-primary'],
                    ['Completed', $appointments['completed'], 'bi-check-circle-fill', 'text-success'],
                    ['Upcoming', $appointments['upcoming'], 'bi-hourglass-split', 'text-info'],
                    ['Cancelled', $appointments['cancelled'], 'bi-x-circle-fill', 'text-danger'],
                    ['Completion rate', $appointments['completionRate'] === null ? '—' : $appointments['completionRate'].'%', 'bi-bar-chart-line-fill', 'text-success'],
                ] as [$label, $value, $icon, $tone])
                    <div class="col-sm-6 col-xl"><div class="card stat-card h-100 border-0"><div class="d-flex justify-content-between align-items-center"><div><div class="stat-label">{{ $label }}</div><h3 class="stat-value mb-0">{{ $value }}</h3></div><i class="bi {{ $icon }} stat-icon {{ $tone }}"></i></div></div></div>
                @endforeach
            </div>
            <div class="card shadow-sm border-0">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="h6 mb-0">By therapist</h3>
                    <small class="text-muted">Completion rate = completed out of completed + cancelled</small>
                </div>
                <div class="card-body p-0"><div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead class="table-light"><tr><th>Therapist</th><th class="text-end">Appointments</th><th class="text-end">Completed</th><th class="text-end">Cancelled</th></tr></thead>
                        <tbody>
                            @forelse ($appointments['byTherapist'] as $row)
                                <tr><td>{{ $row['therapist'] }}</td><td class="text-end">{{ $row['total'] }}</td><td class="text-end">{{ $row['completed'] }}</td><td class="text-end">{{ $row['cancelled'] }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="text-muted text-center py-3">No appointments in this range.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div></div>
            </div>
        </section>
    @endif

    {{-- Billing --}}
    @if ($billing)
        <section id="billing" class="mb-5">
            <h2 class="h5 fw-bold mb-3">Billing <small class="text-muted fw-normal">· {{ $rangeLabel }}</small></h2>
            <div class="row g-4">
                @foreach ([
                    ['Billed', $peso($billing['billed']), $billing['count'].' payment records', 'bi-receipt', 'text-primary'],
                    ['Collected', $peso($billing['collected']), $billing['byStatus']['Paid'].' paid', 'bi-cash-stack', 'text-success'],
                    ['Outstanding', $peso($billing['outstanding']), $billing['byStatus']['Pending'].' pending, '.$billing['byStatus']['Partial'].' partial', 'bi-wallet2', 'text-warning'],
                ] as [$label, $value, $note, $icon, $tone])
                    <div class="col-md-4"><div class="card stat-card h-100 border-0"><div class="d-flex justify-content-between align-items-center"><div><div class="stat-label">{{ $label }}</div><h3 class="stat-value mb-0">{{ $value }}</h3><small class="text-muted">{{ $note }}</small></div><i class="bi {{ $icon }} stat-icon {{ $tone }}"></i></div></div></div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Treatment goals --}}
    @if ($goals)
        <section id="goals" class="mb-5">
            <h2 class="h5 fw-bold mb-3">Treatment goals</h2>
            <div class="row g-4">
                @foreach ([
                    ['Goals achieved (all time)', $goals['achievementRate'] === null ? '—' : $goals['achievementRate'].'%', 'of '.$goals['total'].' goals, discontinued ones excluded', 'bi-bullseye', 'text-success'],
                    ['In progress', $goals['active'], 'active goals', 'bi-flag', 'text-warning'],
                    ['Achieved in range', $goals['achievedInRange'], $rangeLabel, 'bi-trophy', 'text-info'],
                ] as [$label, $value, $note, $icon, $tone])
                    <div class="col-md-4"><div class="card stat-card h-100 border-0"><div class="d-flex justify-content-between align-items-center"><div><div class="stat-label">{{ $label }}</div><h3 class="stat-value mb-0">{{ $value }}</h3><small class="text-muted">{{ $note }}</small></div><i class="bi {{ $icon }} stat-icon {{ $tone }}"></i></div></div></div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Patient registrations (admin) --}}
    @if ($patients)
        <section id="patients" class="mb-4">
            <h2 class="h5 fw-bold mb-3">Patient registrations <small class="text-muted fw-normal">· {{ $rangeLabel }}</small></h2>
            <div class="row g-4 mb-4">
                @foreach ([
                    ['Patients (filtered)', $patients['summary']['totalPatients'], 'bi-people', 'text-primary'],
                    ['Active', $patients['summary']['activePatients'], 'bi-person-check-fill', 'text-success'],
                    ['Inactive (archived)', $patients['summary']['inactivePatients'], 'bi-person-dash', 'text-secondary'],
                    ['New this month', $patients['summary']['newPatientsThisMonth'], 'bi-person-plus-fill', 'text-info'],
                ] as [$label, $value, $icon, $tone])
                    <div class="col-md-6 col-xl-3"><div class="card stat-card h-100 border-0"><div class="d-flex justify-content-between align-items-center"><div><div class="stat-label">{{ $label }}</div><h3 class="stat-value mb-0">{{ $value }}</h3></div><i class="bi {{ $icon }} stat-icon {{ $tone }}"></i></div></div></div>
                @endforeach
            </div>

            <div class="row g-4 mb-4">
                <div class="col-xl-8">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header"><h3 class="h6 mb-0">New registrations</h3></div>
                        <div class="card-body"><div style="height: 280px;"><canvas id="patientTrendChart"></canvas></div></div>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header"><h3 class="h6 mb-0">Growth vs previous period</h3></div>
                        <div class="card-body">
                            <div class="display-6 fw-bold">{{ $patients['growthSummary']['currentPatients'] }}</div>
                            <p class="text-muted">registered in the selected period</p>
                            <p class="fw-semibold mb-3">{{ $patients['growthSummary']['comparisonMessage'] ?? $patients['growthSummary']['trendText'] }}</p>
                            <p class="small text-muted mb-0">
                                Current: {{ $patients['growthSummary']['currentPeriodLabel'] }}<br>
                                Previous: {{ $patients['growthSummary']['previousPeriodLabel'] }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-xl-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header"><h3 class="h6 mb-0">Registrations by condition</h3></div>
                        <div class="card-body">
                            @if ($patients['conditionChart']['values'])
                                <div style="height: 260px;"><canvas id="conditionChart"></canvas></div>
                            @else
                                <p class="text-muted mb-0">No registrations match the filters.</p>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-xl-8">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="h6 mb-0">Registered patients</h3>
                            <small class="text-muted">Newest first</small>
                        </div>
                        <div class="card-body p-0"><div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light"><tr><th>Patient</th><th>Condition</th><th>Therapist</th><th>Registered</th><th>Status</th></tr></thead>
                                <tbody>
                                    @forelse ($patients['recentPatients'] as $patient)
                                        <tr>
                                            <td><a href="{{ route('patients.show', $patient->id) }}" class="fw-semibold">{{ $patient->name }}</a></td>
                                            <td>{{ $patient->condition ?: 'Unspecified' }}</td>
                                            <td>{{ $patient->assigned_to ?: 'Unassigned' }}</td>
                                            <td class="text-nowrap">{{ $patient->created_at?->format('M d, Y') ?? '—' }}</td>
                                            <td>{!! $patient->patient_is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' !!}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted py-4">No patient registrations match the filters.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div></div>
                        @if ($patients['recentPatients']->hasPages())
                            <div class="card-footer bg-transparent">{{ $patients['recentPatients']->fragment('patients')->links() }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    @endif

    <script nonce="{{ Vite::cspNonce() }}">
        (() => {
            const draw = (id, config) => {
                const canvas = document.getElementById(id);
                if (canvas && window.Chart) new Chart(canvas, config);
            };

            draw('recoveryChart', {
                type: 'bar',
                data: {
                    labels: @json($recovery['recoveryLabels']),
                    datasets: [{ label: 'Recovery score', data: @json($recovery['recoveryValues']), backgroundColor: '#14b8a6', borderRadius: 6 }],
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100 } } },
            });

            @if ($patients)
                draw('patientTrendChart', {
                    type: 'line',
                    data: {
                        labels: @json($patients['registrationTrend']['labels']),
                        datasets: [{ label: 'New patients', data: @json($patients['registrationTrend']['values']), borderColor: '#14b8a6', backgroundColor: 'rgba(20, 184, 166, 0.15)', fill: true, tension: 0.35 }],
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 } } } },
                });

                draw('conditionChart', {
                    type: 'doughnut',
                    data: {
                        labels: @json($patients['conditionChart']['labels']),
                        datasets: [{ data: @json($patients['conditionChart']['values']), backgroundColor: ['#14b8a6', '#2dd4bf', '#0ea5e9', '#8b5cf6', '#f59e0b', '#ef4444', '#10b981', '#f97316'], borderWidth: 0 }],
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
                });
            @endif
        })();
    </script>
@endsection
