@extends('layouts.admin')

@section('title', 'Dashboard - RehabPlus')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="page-title mb-1">RehabPlus Dashboard</h2>
            <p class="page-subtitle mb-0">Physical Therapy Recovery Monitoring</p>
        </div>

        @if (auth()->user()->isClinician())
            <a href="{{ route('patients.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
                <i class="bi bi-plus-lg"></i>
                Add Patient
            </a>
        @endif
    </div>

    <div class="row g-4 mb-4">
        <x-admin.stat-card label="Total Patients" :value="$totalPatients" icon="bi-people-fill" tone="info" />
        <x-admin.stat-card label="Avg Compliance" :value="$avgCompliance . '%'" icon="bi-clipboard2-check-fill" tone="success" />
        <x-admin.stat-card label="Avg Pain Level" :value="$avgPain" icon="bi-heart-pulse-fill" tone="danger" />
    </div>

    {{-- TEMPORARY: deploy check for the Vercel URL. Remove after testing. --}}
    <div class="text-center fw-bold my-5" style="font-size: 8rem; line-height: 1; color: #dc3545;">TEST</div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">Recovery Analytics</div>
                <div class="card-body">
                    <canvas id="recoveryChart"></canvas>
                    @unless ($hasRecoveryData)
                        <div class="alert alert-info mt-3 mb-0">
                            Recovery and pain analytics appear once patients complete exercises in the RehabPlus app.
                        </div>
                    @endunless
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">Patient Conditions</div>
                <div class="card-body">
                    <canvas id="conditionChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>Patient Recovery Overview</span>
            <div class="input-group" style="width:250px;">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="patientSearch" class="form-control" placeholder="Search patient...">
            </div>
        </div>

        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0" id="patientsTable">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Condition</th>
                        <th>Sessions</th>
                        <th>Accuracy</th>
                        <th>Pain</th>
                        <th>Recovery</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($patientStats as $patient)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-info text-dark fw-bold d-flex align-items-center justify-content-center" style="width:38px;height:38px;">
                                        {{ strtoupper(substr($patient['name'], 0, 1)) }}
                                    </div>
                                    {{ $patient['name'] }}
                                </div>
                            </td>
                            <td>{{ $patient['condition'] }}</td>
                            <td>{{ $patient['total_sessions'] }}</td>
                            <td>
                                <div class="progress" style="height:8px;">
                                    <div class="progress-bar bg-info" style="width:{{ min((float) $patient['compliance_rate'], 100) }}%"></div>
                                </div>
                                <small>{{ $patient['compliance_rate'] }}%</small>
                            </td>
                            <td><span class="badge bg-warning text-dark">{{ $patient['avg_pain'] }}/10</span></td>
                            <td><span class="text-success fw-semibold">{{ $patient['recovery_score'] }}%</span></td>
                            <td>
                                <a href="{{ route('patients.show', $patient['id']) }}" class="btn btn-sm btn-outline-secondary">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <script nonce="{{ Vite::cspNonce() }}">
        window.rehabPlusDashboardData = {
            recoveryLabels: @json($recoveryLabels ?? []),
            recoveryValues: @json($recoveryValues ?? []),
            conditionLabels: @json(array_keys($conditionCounts ?? [])),
            conditionValues: @json(array_values($conditionCounts ?? []))
        };
    </script>
@endsection
