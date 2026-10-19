<?php $pageTitle = 'Billing and Payment Records – RehabPlus'; ?>
<?= view('layouts/header') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <p class="page-title mb-1">Billing and Payment Records</p>
        <p class="page-subtitle mb-0">Track session fees, balances, payment activity, and printable receipts.</p>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0">Add payment record</h5>
    </div>
    <div class="card-body">
        <form method="post" action="<?= site_url('billing/save') ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Patient</label>
                    <input type="text" name="patient" class="form-control" placeholder="Patient name" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Session</label>
                    <input type="text" name="session" class="form-control" placeholder="Session type" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Fee</label>
                    <input type="number" name="fee" step="0.01" class="form-control" placeholder="120.00" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="Paid">Paid</option>
                        <option value="Pending">Pending</option>
                        <option value="Partial">Partial</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Date</label>
                    <input type="date" name="date" class="form-control" required>
                </div>
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary">Add payment</button>
                </div>
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
                <h4 class="mb-0"><?= esc($sessionFees) ?></h4>
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
                <h4 class="mb-0"><?= esc($paymentStatus) ?></h4>
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
                <h4 class="mb-0"><?= esc($outstanding) ?></h4>
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
                <h4 class="mb-0"><?= esc($paymentHistory) ?> records</h4>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Recent payment activity</h5>
        <button class="btn btn-outline-primary btn-sm" onclick="window.print()">Printable receipts</button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Patient</th>
                        <th>Session</th>
                        <th>Fee</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?= esc($payment['patient']) ?></td>
                            <td><?= esc($payment['session']) ?></td>
                            <td><?= esc($payment['fee']) ?></td>
                            <td>
                                <?php if ($payment['status'] === 'Paid'): ?>
                                    <span class="badge bg-success-subtle text-success">Paid</span>
                                <?php elseif ($payment['status'] === 'Pending'): ?>
                                    <span class="badge bg-warning-subtle text-warning">Pending</span>
                                <?php else: ?>
                                    <span class="badge bg-info-subtle text-info">Partial</span>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($payment['date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= view('layouts/footer') ?>
