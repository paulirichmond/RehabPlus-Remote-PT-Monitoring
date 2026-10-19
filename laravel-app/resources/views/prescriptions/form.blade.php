@extends('layouts.admin')

@section('title', $prescription ? 'Edit Exercise' : 'Assign Exercise')

@section('content')
    @php
        $rx = $prescription;
        $selectedType = old('exercise_type', $rx['exerciseType'] ?? '');
        $selectedDays = array_map('intval', old('days', $rx['days'] ?? []));
    @endphp
    <div class="mb-3">
        <a href="{{ route('patients.show', $patient['uid']) }}" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Back to {{ $patient['name'] }}</a>
        <p class="page-title mb-1 mt-1">{{ $rx ? 'Edit '.$rx['exerciseName'] : 'Assign Exercise' }}</p>
        <p class="page-subtitle mb-0">For {{ $patient['name'] }}. The app tracks the exercise with the camera and counts reps.</p>
    </div>

    @include('partials.flash')

    <form method="POST" action="{{ $rx ? route('prescriptions.update', [$patient['uid'], $rx['id']]) : route('prescriptions.store', $patient['uid']) }}" class="card shadow-sm border-0" style="max-width: 900px;">
        @csrf
        @if ($rx)
            @method('PUT')
        @endif

        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="exercise_type" class="form-label small fw-semibold">Exercise</label>
                    <select id="exercise_type" name="exercise_type" class="form-select" required>
                        <option value="" disabled @selected($selectedType === '')>Choose an exercise</option>
                        @foreach ($catalog as $code => [$name, $kind, $defaultTarget, $defaultHold])
                            <option value="{{ $code }}" data-kind="{{ $kind }}" data-target="{{ $defaultTarget }}" data-hold="{{ $defaultHold }}" @selected($selectedType === $code)>
                                {{ $name }}{{ $kind === 'hold' ? ' (hold)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="target_reps" class="form-label small fw-semibold" id="target_label">Reps per session</label>
                    <input id="target_reps" type="number" name="target_reps" class="form-control" min="1" max="100" value="{{ old('target_reps', $rx['targetReps'] ?? 10) }}" required>
                </div>
                <div class="col-md-3" id="hold_group">
                    <label for="hold_seconds" class="form-label small fw-semibold">Hold length (seconds)</label>
                    <input id="hold_seconds" type="number" name="hold_seconds" class="form-control" min="1" max="300" value="{{ old('hold_seconds', $rx['holdSeconds'] ?? '') }}" placeholder="Default">
                </div>

                <div class="col-md-3">
                    <label for="frequency_per_week" class="form-label small fw-semibold">Sessions per week</label>
                    <input id="frequency_per_week" type="number" name="frequency_per_week" class="form-control" min="1" max="14" value="{{ old('frequency_per_week', $rx['frequencyPerWeek'] ?? 3) }}" required>
                    <div class="form-text">Drives the weekly progress bar.</div>
                </div>

                <div class="col-md-9">
                    <span class="form-label small fw-semibold d-block">Allowed days <span class="text-muted fw-normal">(none ticked = any day)</span></span>
                    <div class="d-flex flex-wrap gap-3 mt-1">
                        @foreach (\App\Services\Patient\ExerciseCatalog::DAY_NAMES as $num => $day)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="days[]" value="{{ $num }}" id="day{{ $num }}" @checked(in_array($num, $selectedDays, true))>
                                <label class="form-check-label" for="day{{ $num }}">{{ $day }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="col-md-3">
                    <label for="start_hour" class="form-label small fw-semibold">From hour</label>
                    <select id="start_hour" name="start_hour" class="form-select">
                        <option value="">Any time</option>
                        @for ($h = 0; $h <= 23; $h++)
                            <option value="{{ $h }}" @selected((string) old('start_hour', $rx['startHour'] ?? '') === (string) $h)>{{ sprintf('%02d:00', $h) }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="end_hour" class="form-label small fw-semibold">Until hour</label>
                    <select id="end_hour" name="end_hour" class="form-select">
                        <option value="">Any time</option>
                        @for ($h = 1; $h <= 24; $h++)
                            <option value="{{ $h }}" @selected((string) old('end_hour', $rx['endHour'] ?? '') === (string) $h)>{{ sprintf('%02d:00', $h) }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <p class="form-text mb-2">Sessions outside these days/hours still count, but are flagged "outside schedule" (unassisted practice).</p>
                </div>

                <div class="col-12">
                    <label for="notes" class="form-label small fw-semibold">Instructions for the patient</label>
                    <textarea id="notes" name="notes" class="form-control" rows="3" maxlength="500" placeholder="Shown in the app, e.g. Keep your back straight; stop if pain goes above 6.">{{ old('notes', $rx['notes'] ?? '') }}</textarea>
                </div>
            </div>
        </div>
        <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
            <small class="text-muted">{{ $rx ? 'Changes apply the next time the app loads the plan.' : 'The patient gets a notification on their phone.' }}</small>
            <button type="submit" class="btn btn-primary px-4">{{ $rx ? 'Save changes' : 'Assign exercise' }}</button>
        </div>
    </form>

    <script nonce="{{ Vite::cspNonce() }}">
        // Hold exercises count holds and can set a hold length; reps exercises can't.
        (function () {
            const type = document.getElementById('exercise_type');
            const target = document.getElementById('target_reps');
            const isNew = {{ $rx ? 'false' : 'true' }};
            function sync(applyDefaults) {
                const opt = type.selectedOptions[0];
                const hold = opt && opt.dataset.kind === 'hold';
                document.getElementById('hold_group').style.display = hold ? '' : 'none';
                document.getElementById('target_label').textContent = hold ? 'Holds per session' : 'Reps per session';
                if (applyDefaults && opt && opt.dataset.target) target.value = opt.dataset.target;
            }
            type.addEventListener('change', () => sync(isNew));
            sync(false);
        })();
    </script>
@endsection
