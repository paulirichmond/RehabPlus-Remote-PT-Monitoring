@extends('layouts.app')

@section('title', $record ? 'Edit Exercise Record' : 'Add Exercise Record')

@section('content')
    <div class="topbar">
        <div>
            <a href="{{ route('patients.show', $patientId) }}">← Back to patient</a>
        </div>
    </div>

    <div class="panel" style="max-width: 800px; margin: 0 auto;">
        <h1>{{ $record ? 'Edit Exercise Record' : 'Add Exercise Record' }}</h1>
        <p><strong>Patient:</strong> {{ $patient->name }}</p>

        <form method="POST" action="{{ $record ? route('exercises.update', [$patientId, $record->id]) : route('exercises.store', $patientId) }}" style="display:grid; gap:16px;">
            @csrf
            @if($record)
                @method('PUT')
            @endif

            <label style="display:grid; gap:6px; font-weight:bold;">
                Exercise name
                <input type="text" name="exercise_name" value="{{ old('exercise_name', $record->exercise_name ?? '') }}" required>
            </label>

            <div class="grid-two">
                <label style="display:grid; gap:6px; font-weight:bold;">
                    Sets prescribed
                    <input type="number" min="1" name="sets_prescribed" value="{{ old('sets_prescribed', $record->sets_prescribed ?? 1) }}" required>
                </label>

                <label style="display:grid; gap:6px; font-weight:bold;">
                    Sets completed
                    <input type="number" min="0" name="sets_completed" value="{{ old('sets_completed', $record->sets_completed ?? 0) }}" required>
                </label>
            </div>

            <div class="grid-two">
                <label style="display:grid; gap:6px; font-weight:bold;">
                    Pain level (0-10)
                    <input type="number" min="0" max="10" name="pain_level" value="{{ old('pain_level', $record->pain_level ?? 0) }}" required>
                </label>

                <label style="display:grid; gap:6px; font-weight:bold;">
                    Date recorded
                    <input type="date" name="recorded_at" value="{{ old('recorded_at', ($record->recorded_at ? \Carbon\Carbon::parse($record->recorded_at)->format('Y-m-d') : now()->format('Y-m-d'))) }}" required>
                </label>
            </div>

            <label style="display:grid; gap:6px; font-weight:bold;">
                Notes
                <textarea name="notes">{{ old('notes', $record->notes ?? '') }}</textarea>
            </label>

            <button type="submit" class="btn">{{ $record ? 'Update Record' : 'Save Record' }}</button>
        </form>
    </div>
@endsection
