@extends('layouts.admin')

@section('title', $patient ? 'Edit Patient' : 'Add Patient')

@section('content')
    @php($me = auth()->user())
    <div class="mb-3">
        <a href="{{ $patient ? route('patients.show', $patient['uid']) : route('patients.index') }}" class="text-decoration-none small">
            <i class="bi bi-arrow-left me-1"></i>{{ $patient ? 'Back to patient' : 'Back to patients' }}
        </a>
        <p class="page-title mb-1 mt-1">{{ $patient ? 'Edit '.$patient['name'] : 'Add Patient' }}</p>
        <p class="page-subtitle mb-0">
            @if ($patient)
                Changes show in the patient's app the next time it loads.
            @else
                This also creates the patient's RehabPlus app login.
            @endif
        </p>
    </div>

    @include('partials.flash')

    <form method="POST" action="{{ $patient ? route('patients.update', $patient['uid']) : route('patients.store') }}" class="card shadow-sm border-0" style="max-width: 900px;">
        @csrf
        @if ($patient)
            @method('PUT')
        @endif

        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label small fw-semibold">Full name</label>
                    <input id="name" type="text" name="name" class="form-control" value="{{ old('name', $patient['name'] ?? '') }}" required maxlength="100">
                </div>
                <div class="col-md-6">
                    <label for="condition" class="form-label small fw-semibold">Condition</label>
                    <input id="condition" type="text" name="condition" class="form-control" value="{{ old('condition', $patient['condition'] ?? '') }}" required maxlength="150" placeholder="e.g. MCL sprain, post-acute">
                </div>

                @if (! $patient)
                    <div class="col-12"><hr class="my-1"><p class="fw-semibold mb-0 mt-2">App login</p>
                        <p class="text-muted small mb-0">Give the patient this email and temporary password. They can set their own password from the app's "Forgot password" link.</p></div>
                    <div class="col-md-4">
                        <label for="email" class="form-label small fw-semibold">Email</label>
                        <input id="email" type="email" name="email" class="form-control" value="{{ old('email') }}" required maxlength="150" autocomplete="off">
                    </div>
                    <div class="col-md-4">
                        <label for="password" class="form-label small fw-semibold">Temporary password</label>
                        <input id="password" type="password" name="password" class="form-control" required minlength="{{ \App\Services\Firebase\FirebaseAdmin::MIN_PASSWORD }}" autocomplete="new-password">
                    </div>
                    <div class="col-md-4">
                        <label for="password_confirmation" class="form-label small fw-semibold">Repeat password</label>
                        <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
                    </div>
                    <div class="col-12"><hr class="my-1"></div>
                @else
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">App login email</label>
                        <input type="text" class="form-control" value="{{ $patient['email'] }}" disabled>
                    </div>
                @endif

                <div class="col-md-3">
                    <label for="phone" class="form-label small fw-semibold">Phone</label>
                    <input id="phone" type="text" name="phone" class="form-control" value="{{ old('phone', $patient['phone'] ?? '') }}" maxlength="30">
                </div>
                <div class="col-md-3">
                    <label for="dob" class="form-label small fw-semibold">Date of birth</label>
                    <input id="dob" type="text" name="dob" class="form-control" value="{{ old('dob', $patient['dob'] ?? '') }}" maxlength="30" placeholder="e.g. 1990-05-14">
                </div>

                @if ($me->isAdmin())
                    <div class="col-md-6">
                        <label for="therapist_id" class="form-label small fw-semibold">Assigned therapist</label>
                        <select id="therapist_id" name="therapist_id" class="form-select">
                            <option value="">Unassigned</option>
                            @foreach ($therapists as $t)
                                <option value="{{ $t['uid'] }}" @selected(old('therapist_id', $patient['therapistId'] ?? '') === $t['uid'])>{{ $t['name'] }}</option>
                            @endforeach
                        </select>
                        @if (! $therapists)
                            <div class="form-text">No therapist accounts yet. Add one under Staff Accounts.</div>
                        @endif
                    </div>
                @elseif (! $patient)
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Assigned therapist</label>
                        <input type="text" class="form-control" value="{{ $me->name }} (you)" disabled>
                    </div>
                @endif

                <div class="col-12">
                    <label for="medical_summary" class="form-label small fw-semibold">Medical summary</label>
                    <textarea id="medical_summary" name="medical_summary" class="form-control" rows="3" maxlength="2000">{{ old('medical_summary', $patient['medicalSummary'] ?? '') }}</textarea>
                </div>
                <div class="col-12">
                    <label for="therapy_plan" class="form-label small fw-semibold">Therapy plan</label>
                    <textarea id="therapy_plan" name="therapy_plan" class="form-control" rows="3" maxlength="2000">{{ old('therapy_plan', $patient['therapyPlan'] ?? '') }}</textarea>
                </div>
            </div>
        </div>
        <div class="card-footer bg-transparent text-end">
            <button type="submit" class="btn btn-primary px-4">{{ $patient ? 'Save changes' : 'Create patient' }}</button>
        </div>
    </form>
@endsection
