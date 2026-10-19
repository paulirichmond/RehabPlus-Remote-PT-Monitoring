@extends('layouts.admin')

@section('title', $patient['name'])

@section('content')
    @php
        $me = auth()->user();
        $tz = config('app.timezone');
        $canMessage = $me->isTherapist() ? $patient['therapistId'] === $me->id : true;
        $scheduleText = function (array $rx) {
            $days = $rx['days'] ? implode(', ', array_map(fn ($d) => \App\Services\Patient\ExerciseCatalog::DAY_NAMES[$d] ?? $d, $rx['days'])) : 'Any day';
            $hours = $rx['startHour'] !== null ? sprintf('%02d:00–%02d:00', $rx['startHour'], $rx['endHour']) : 'any time';
            return "{$days}, {$hours}";
        };
    @endphp

    <div class="mb-3">
        <a href="{{ route('patients.index') }}" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>All patients</a>
    </div>

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <p class="page-title mb-1">
                {{ $patient['name'] }}
                @if ($patient['archived'])
                    <span class="badge bg-secondary align-middle ms-2" style="font-size:.8rem;">Archived</span>
                @endif
            </p>
            <p class="page-subtitle mb-1">{{ $patient['condition'] }} · Therapist: {{ $patient['therapistName'] ?: 'Unassigned' }}</p>
            <p class="text-muted small mb-0">
                <i class="bi bi-envelope me-1"></i>{{ $patient['email'] ?: '—' }}
                <span class="ms-3"><i class="bi bi-telephone me-1"></i>{{ $patient['phone'] ?: '—' }}</span>
                <span class="ms-3"><i class="bi bi-calendar3 me-1"></i>Born {{ $patient['dob'] ?: '—' }}</span>
                <span class="ms-3">Added {{ $patient['createdAt']?->setTimezone($tz)->format('M j, Y') ?? '—' }}</span>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if ($canMessage && ! $patient['archived'])
                <a href="{{ route('messages.show', $patient['uid']) }}" class="btn btn-outline-primary"><i class="bi bi-chat-dots me-1"></i>Message</a>
            @endif
            @if ($canManage)
                <a href="{{ route('patients.edit', $patient['uid']) }}" class="btn btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Edit</a>
                @if ($patient['archived'])
                    <form method="POST" action="{{ route('patients.restore', $patient['uid']) }}">@csrf
                        <button class="btn btn-outline-success"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('patients.archive', $patient['uid']) }}" data-confirm="Archive this patient? Their app login is disabled; their records are kept and you can restore them later.">@csrf
                        <button class="btn btn-outline-danger"><i class="bi bi-archive me-1"></i>Archive</button>
                    </form>
                @endif
            @endif
        </div>
    </div>

    @include('partials.flash')

    @if ($canManage)
        <details class="mb-4" @if ($errors->has('password')) open @endif>
            <summary class="small text-muted mb-2" style="cursor:pointer;"><i class="bi bi-key me-1"></i>Set a new temporary app password</summary>
            <form method="POST" action="{{ route('patients.password', $patient['uid']) }}" class="card card-body shadow-sm border-0">
                @csrf
                <p class="text-muted small">Use this if the patient can't receive the "Forgot password" email. Give them the new password; they can change it in the app.</p>
                <div class="row g-2 align-items-end">
                    <div class="col-md-4"><input type="password" name="password" class="form-control" placeholder="New password" minlength="{{ \App\Services\Firebase\FirebaseAdmin::MIN_PASSWORD }}" required autocomplete="new-password"></div>
                    <div class="col-md-4"><input type="password" name="password_confirmation" class="form-control" placeholder="Repeat password" required autocomplete="new-password"></div>
                    <div class="col-md-4"><button class="btn btn-primary">Set password</button></div>
                </div>
            </form>
        </details>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100"><div class="card-body">
                <p class="fw-semibold mb-2">Medical summary</p>
                <p class="mb-0" style="white-space: pre-wrap;">{{ $patient['medicalSummary'] ?: 'No summary recorded yet.' }}</p>
            </div></div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100"><div class="card-body">
                <p class="fw-semibold mb-2">Therapy plan</p>
                <p class="mb-0" style="white-space: pre-wrap;">{{ $patient['therapyPlan'] ?: 'No therapy plan recorded yet.' }}</p>
            </div></div>
        </div>
    </div>

    @if ($clinical)
        {{-- Progress from the sessions the patient records in the app --}}
        <div class="row g-4 mb-4">
            <x-admin.stat-card label="Adherence this week" :value="$adherence['overall'] === null ? '—' : $adherence['overall'].'%'" icon="bi-calendar-check" tone="info" />
            <x-admin.stat-card label="Movement accuracy" :value="$stats['total'] ? $stats['accuracy'].'%' : '—'" icon="bi-bullseye" tone="success" />
            <x-admin.stat-card label="Average pain" :value="$stats['total'] ? $stats['avgPain'].'/10' : '—'" icon="bi-heart-pulse-fill" tone="danger" />
        </div>
        <p class="text-muted small mb-4">
            {{ $stats['completed'] }} completed {{ Str::plural('session', $stats['completed']) }}
            · {{ $stats['endedEarly'] }} ended early
            · {{ $stats['outsideSchedule'] }} outside the schedule
            @if ($stats['lastSessionAt'])
                · last session {{ $stats['lastSessionAt']->setTimezone($tz)->diffForHumans() }}
            @endif
            (from the last {{ count($sessions) }} sessions)
        </p>

        {{-- Prescriptions --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Assigned exercises</h5>
                @if ($canManage && ! $patient['archived'])
                    <a href="{{ route('prescriptions.create', $patient['uid']) }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Assign exercise</a>
                @endif
            </div>
            <div class="card-body p-0"><div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light"><tr><th>Exercise</th><th>Target</th><th>Schedule</th><th style="min-width:160px;">This week</th><th>Status</th><th class="text-end"></th></tr></thead>
                    <tbody>
                        @forelse ($prescriptions as $rx)
                            @php($week = $adherence['perPrescription'][$rx['id']] ?? null)
                            <tr class="{{ $rx['active'] ? '' : 'opacity-50' }}">
                                <td class="fw-semibold">{{ $rx['exerciseName'] }}
                                    @if ($rx['notes'])<div class="text-muted small fw-normal">{{ Str::limit($rx['notes'], 80) }}</div>@endif
                                </td>
                                <td class="small">{{ $rx['targetReps'] }} {{ $rx['isHold'] ? 'holds' : 'reps' }}@if ($rx['holdSeconds']) × {{ $rx['holdSeconds'] }}s @endif, {{ $rx['frequencyPerWeek'] }}×/week</td>
                                <td class="small text-muted">{{ $scheduleText($rx) }}</td>
                                <td>
                                    @if ($week)
                                        <div class="progress" style="height:8px;"><div class="progress-bar bg-info" style="width: {{ $week['percent'] }}%"></div></div>
                                        <small>{{ $week['done'] }}/{{ $week['target'] }} sessions</small>
                                    @else
                                        <small class="text-muted">—</small>
                                    @endif
                                </td>
                                <td>{!! $rx['active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Stopped</span>' !!}</td>
                                <td class="text-end text-nowrap">
                                    @if ($canManage)
                                        <a href="{{ route('prescriptions.edit', [$patient['uid'], $rx['id']]) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                        <form method="POST" action="{{ route('prescriptions.toggle', [$patient['uid'], $rx['id']]) }}" class="d-inline">@csrf
                                            <input type="hidden" name="active" value="{{ $rx['active'] ? 0 : 1 }}">
                                            <button class="btn btn-sm {{ $rx['active'] ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $rx['active'] ? 'Stop' : 'Resume' }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No exercises assigned yet. Assigned exercises appear in the patient's app.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div></div>
        </div>

        {{-- Pain over the last 14 days --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header"><h5 class="mb-0">Pain, last 14 days</h5></div>
            <div class="card-body">
                <div class="d-flex align-items-end gap-2" style="height:110px;">
                    @foreach ($painTrend as $day)
                        <div class="flex-fill text-center" title="{{ $day['date']->format('M j') }}: {{ $day['pain'] === null ? 'no session' : $day['pain'].'/10' }}">
                            <div class="small text-muted" style="font-size:.7rem;">{{ $day['pain'] ?? '' }}</div>
                            <div style="height: {{ $day['pain'] === null ? 2 : max(4, $day['pain'] * 8) }}px; border-radius:4px 4px 0 0; background: {{ $day['pain'] === null ? 'var(--border)' : ($day['pain'] >= 7 ? '#ef4444' : ($day['pain'] >= 4 ? '#f59e0b' : '#14b8a6')) }};"></div>
                            <div class="text-muted" style="font-size:.65rem;">{{ $day['date']->format('j') }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Sessions --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header"><h5 class="mb-0">Recent sessions</h5></div>
            <div class="card-body p-0"><div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light"><tr><th>When</th><th>Exercise</th><th>Reps</th><th>Accuracy</th><th>Pain</th><th>Flags</th></tr></thead>
                    <tbody>
                        @forelse (array_slice($sessions, 0, 30) as $s)
                            <tr>
                                <td class="small text-nowrap">{{ $s['startTime']?->setTimezone($tz)->format('M j, g:i A') ?? '—' }}</td>
                                <td>{{ $s['exerciseName'] }}</td>
                                <td class="small">{{ $s['completedReps'] }}/{{ $s['targetReps'] }}@if ($s['rejectedReps']) <span class="text-muted">({{ $s['rejectedReps'] }} rejected)</span>@endif</td>
                                <td class="small">{{ $s['accuracy'] === null ? '—' : $s['accuracy'].'%' }}</td>
                                <td class="small">{{ $s['painLevel'] ?? '—' }}/10
                                    @if ($s['painNote'])<div class="text-muted" style="white-space: pre-wrap;">“{{ $s['painNote'] }}”</div>@endif
                                </td>
                                <td>
                                    @if ($s['endedEarly'])<span class="badge bg-warning text-dark">Ended early</span>@endif
                                    @if ($s['outsideSchedule'])<span class="badge bg-info text-dark">Outside schedule</span>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No sessions yet. They appear here when the patient exercises in the app.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div></div>
        </div>

        <div class="row g-4">
            {{-- Goals --}}
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Goals @if ($goalAchievement !== null)<small class="text-muted fw-normal">· {{ $goalAchievement }}% achieved</small>@endif</h5>
                        @if ($canManage)<a href="{{ route('assessments', ['patient' => $patient['uid']]) }}#goal-form" class="btn btn-sm btn-outline-primary">Add goal</a>@endif
                    </div>
                    <div class="card-body">
                        @forelse ($goals as $goal)
                            @include('partials.goal', ['goal' => $goal, 'canManage' => $canManage])
                        @empty
                            <p class="text-muted mb-0">No goals yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Assessments --}}
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Assessments</h5>
                        @if ($canManage)<a href="{{ route('assessments', ['patient' => $patient['uid']]) }}" class="btn btn-sm btn-outline-primary">Add assessment</a>@endif
                    </div>
                    <div class="card-body p-0">
                        <table class="table mb-0 align-middle">
                            <tbody>
                                @forelse ($assessments as $a)
                                    <tr>
                                        <td class="small text-nowrap">{{ $a['assessedAt']?->setTimezone($tz)->format('M j, Y') }}</td>
                                        <td><span class="fw-semibold">{{ $a['type'] }}</span>
                                            @if ($a['notes'])<div class="small text-muted">{{ $a['notes'] }}</div>@endif
                                            <div class="small text-muted">{{ $a['therapistName'] }}</div></td>
                                        <td class="fw-bold">{{ $a['score'] }}%</td>
                                    </tr>
                                @empty
                                    <tr><td class="text-muted p-3">No assessments yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Therapy notes --}}
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Therapy notes</h5>
                        @if ($canManage)<a href="{{ route('notes', ['patient' => $patient['uid']]) }}" class="btn btn-sm btn-outline-primary">Add note</a>@endif
                    </div>
                    <div class="card-body">
                        @forelse ($notes as $note)
                            <div class="mb-3 pb-3 border-bottom">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-semibold">{{ $note['topic'] }}</span>
                                    <small class="text-muted">{{ $note['noteDate']?->setTimezone($tz)->format('M j, Y') }} · {{ $note['therapistName'] }}</small>
                                </div>
                                <div style="white-space: pre-wrap;">{{ $note['text'] }}</div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">No notes yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
