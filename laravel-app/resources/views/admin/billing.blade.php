@extends('layouts.admin')

@section('title', 'Billing and Payment Records')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <p class="page-title mb-1">Billing and Payment Records</p>
            <p class="page-subtitle mb-0">Track session fees, balances, payment activity, and printable receipts.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header">
            <h5 class="mb-0">Add payment record</h5>
        </div>
        <div class="card-body">
            <form method="post" action="{{ route('billing.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label small fw-semibold">Patient</label><input type="text" name="patient" class="form-control" placeholder="Patient name" required></div>
                    <div class="col-md-3"><label class="form-label small fw-semibold">Session</label><input type="text" name="session" class="form-control" placeholder="Session type" required></div>
                    <div class="col-md-2"><label class="form-label small fw-semibold">Fee</label><input type="number" name="fee" step="0.01" class="form-control" placeholder="₱120.00" required></div>
                    <div class="col-md-2"><label class="form-label small fw-semibold">Status</label><select name="status" class="form-select"><option value="Paid">Paid</option><option value="Pending">Pending</option><option value="Partial">Partial</option></select></div>
                    <div class="col-md-2"><label class="form-label small fw-semibold">Date</label><input type="date" name="date" class="form-control" required></div>
                    <div class="col-12 text-end"><button type="submit" class="btn btn-primary">Add payment</button></div>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Session fees</span>
                        <i class="bi bi-wallet2 text-primary"></i>
                    </div>
                    <h4 class="mb-0">{{ $sessionFees }}</h4>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Payment status</span>
                        <i class="bi bi-check-circle text-success"></i>
                    </div>
                    <h4 class="mb-0">{{ $paymentStatus }}</h4>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Outstanding balances</span>
                        <i class="bi bi-exclamation-circle text-warning"></i>
                    </div>
                    <h4 class="mb-0">{{ $outstanding }}</h4>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Payment history</span>
                        <i class="bi bi-clock-history text-info"></i>
                    </div>
                    <h4 class="mb-0">{{ $paymentHistory }} records</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Recent payment activity</h5>
            <button class="btn btn-outline-primary btn-sm" data-print>Printable receipts</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>Patient</th><th>Session</th><th>Fee</th><th>Status</th><th>Date</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            <tr>
                                <td>{{ $payment['patient'] }}</td>
                                <td>{{ $payment['session'] }}</td>
                                <td>{{ $payment['fee'] }}</td>
                                <td>
                                    <div id="status-view-{{ $payment->id }}">
                                        @switch($payment['status']) @case('Paid')<span class="badge bg-success-subtle text-success">Paid</span>@break @case('Pending')<span class="badge bg-warning-subtle text-warning">Pending</span>@break @default<span class="badge bg-info-subtle text-info">Partial</span>@endswitch
                                        <button type="button" class="btn btn-sm btn-link p-0 ms-1" title="Edit status" data-hide="status-view-{{ $payment->id }}" data-show="status-form-{{ $payment->id }}"><i class="bi bi-pencil-square"></i> Edit</button>
                                    </div>
                                    <form id="status-form-{{ $payment->id }}" method="post" action="{{ route('billing.status', $payment->id) }}" class="d-flex gap-1 align-items-center" hidden>
                                        @csrf @method('PATCH')
                                        <select name="status" class="form-select form-select-sm w-auto">
                                            @foreach (['Paid', 'Pending', 'Partial'] as $status)
                                                <option value="{{ $status }}" @selected($payment['status'] === $status)>{{ $status }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-hide="status-form-{{ $payment->id }}" data-show="status-view-{{ $payment->id }}">Cancel</button>
                                    </form>
                                </td>
                                <td>{{ $payment['date'] }}</td>
                                <td class="text-end"><form method="post" action="{{ route('billing.delete', $payment->id) }}" class="d-inline" data-confirm="Delete this payment record?">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger">Delete</button></form></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No payment records available.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
