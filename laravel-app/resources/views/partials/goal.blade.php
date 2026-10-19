{{-- One goal with its progress bar and (if allowed) an inline update form. Needs $goal, $canManage; optional $showPatient. --}}
@php($badge = ['active' => 'bg-info text-dark', 'achieved' => 'bg-success', 'discontinued' => 'bg-secondary'][$goal['status']])
<div class="mb-3 pb-3 border-bottom">
    <div class="d-flex justify-content-between align-items-start gap-2">
        <div>
            <span class="fw-semibold">{{ $goal['title'] }}</span>
            <span class="badge {{ $badge }} ms-1">{{ \App\Services\Patient\ClinicalRecordService::GOAL_STATUSES[$goal['status']] }}</span>
            @if (! empty($showPatient))
                <div class="small"><a href="{{ route('patients.show', $goal['patientUid']) }}">{{ $goal['patientName'] }}</a></div>
            @endif
            @if ($goal['details'])<div class="small text-muted">{{ $goal['details'] }}</div>@endif
            <div class="small text-muted">
                {{ $goal['therapistName'] }}
                @if ($goal['targetDate']) · target {{ $goal['targetDate']->setTimezone(config('app.timezone'))->format('M j, Y') }}@endif
                @if ($goal['achievedAt']) · achieved {{ $goal['achievedAt']->setTimezone(config('app.timezone'))->format('M j, Y') }}@endif
            </div>
        </div>
        <span class="fw-bold">{{ $goal['progress'] }}%</span>
    </div>
    <div class="progress mt-2" style="height:8px;"><div class="progress-bar {{ $goal['status'] === 'achieved' ? 'bg-success' : 'bg-info' }}" style="width: {{ $goal['progress'] }}%"></div></div>

    @if ($canManage)
        <div class="d-flex flex-wrap gap-2 mt-2">
            <form method="POST" action="{{ route('goals.update', [$goal['patientUid'], $goal['id']]) }}" class="d-flex gap-2 align-items-center">
                @csrf @method('PUT')
                <select name="status" class="form-select form-select-sm" style="width:auto;" aria-label="Goal status">
                    @foreach (\App\Services\Patient\ClinicalRecordService::GOAL_STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected($goal['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="input-group input-group-sm" style="width:110px;">
                    <input type="number" name="progress" class="form-control" min="0" max="100" value="{{ $goal['progress'] }}" aria-label="Progress percent">
                    <span class="input-group-text">%</span>
                </div>
                <button class="btn btn-sm btn-outline-primary">Update</button>
            </form>
            <form method="POST" action="{{ route('records.delete', [$goal['patientUid'], 'goals', $goal['id']]) }}" data-confirm="Delete this goal?">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger">Delete</button>
            </form>
        </div>
    @endif
</div>
