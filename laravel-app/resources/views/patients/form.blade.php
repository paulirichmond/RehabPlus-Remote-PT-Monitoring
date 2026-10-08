@extends('layouts.app')

@section('title', $patient ? 'Edit Patient' : 'New Patient')

@section('content')
    <div class="topbar">
        <div>
            <a href="{{ route('patients.index') }}">← Back to patients</a>
        </div>
    </div>

    <div class="panel" style="max-width: 900px; margin: 0 auto;">
        <h1>{{ $patient ? 'Edit Patient' : 'Add New Patient' }}</h1>

        <form method="POST" action="{{ $patient ? route('patients.update', $patient->id) : route('patients.store') }}" enctype="multipart/form-data" style="display:grid; gap:16px;">
            @csrf
            @if($patient)
                @method('PUT')
            @endif

            <div class="grid-two">
                <label style="display:grid; gap:6px; font-weight:bold;">
                    Full name
                    <input type="text" name="name" value="{{ old('name', $patient->name ?? '') }}" required>
                </label>

                <label style="display:grid; gap:6px; font-weight:bold;">
                    Condition
                    <input type="text" name="condition" value="{{ old('condition', $patient->condition ?? '') }}" required>
                </label>
            </div>

            <label style="display:grid; gap:6px; font-weight:bold;">
                Medical summary
                <textarea name="medical_summary">{{ old('medical_summary', $patient->medical_summary ?? '') }}</textarea>
            </label>

            <label style="display:grid; gap:6px; font-weight:bold;">
                Therapy plan
                <textarea name="therapy_plan">{{ old('therapy_plan', $patient->therapy_plan ?? '') }}</textarea>
            </label>

            <label style="display:grid; gap:6px; font-weight:bold;">
                Avatar
                <input type="file" name="avatar" accept="image/*">
            </label>

            <button type="submit" class="btn">{{ $patient ? 'Update Patient' : 'Create Patient' }}</button>
        </form>
    </div>
@endsection
