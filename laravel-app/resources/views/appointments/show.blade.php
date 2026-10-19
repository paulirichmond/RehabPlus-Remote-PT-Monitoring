@extends('layouts.admin')

@section('title', 'Appointment Details')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('appointments.index') }}" class="text-muted small text-decoration-none d-inline-flex align-items-center gap-1 mb-2">
                <i class="bi bi-arrow-left"></i>
                Back to Appointments
            </a>
            <h1 class="page-title mb-1">Appointment Details</h1>
        </div>
        <a href="{{ route('appointments.edit', $appointment->id) }}" class="btn btn-primary">
            <i class="bi bi-pencil me-2"></i>Edit
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="text-muted small">Patient</div>
                    <div class="fs-5 fw-semibold">{{ $appointment->patient }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Therapist</div>
                    <div class="fs-5 fw-semibold">{{ $appointment->therapist }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Condition</div>
                    <div class="fs-5 fw-semibold">{{ $appointment->patient_condition }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Contact</div>
                    <div class="fs-5 fw-semibold">{{ $appointment->contact }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Date</div>
                    <div class="fs-5 fw-semibold">{{ date('M d, Y', strtotime($appointment->date)) }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Time</div>
                    <div class="fs-5 fw-semibold">{{ date('g:i A', strtotime($appointment->time)) }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Session</div>
                    <div class="fs-5 fw-semibold">{{ $appointment->session }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Status</div>
                    <div>
                        @if ($appointment->status === 'Upcoming')
                            <span class="badge bg-info">Upcoming</span>
                        @elseif ($appointment->status === 'Completed')
                            <span class="badge bg-success">Completed</span>
                        @else
                            <span class="badge bg-danger">Cancelled</span>
                        @endif
                    </div>
                </div>
                <div class="col-12">
                    <div class="text-muted small">Notes</div>
                    <div class="fs-6">{!! nl2br(e($appointment->notes ?? 'No notes provided.')) !!}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
