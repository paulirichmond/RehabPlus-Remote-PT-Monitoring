<?= view('layouts/header', ['pageTitle' => 'Appointment Details']) ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="<?= site_url('appointments') ?>" class="text-muted small text-decoration-none d-inline-flex align-items-center gap-1 mb-2">
            <i class="bi bi-arrow-left"></i>
            Back to Appointments
        </a>
        <h1 class="page-title mb-1">Appointment Details</h1>
    </div>
    <a href="<?= site_url('appointments/' . $appointment['id'] . '/edit') ?>" class="btn btn-primary">
        <i class="bi bi-pencil me-2"></i>Edit
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="text-muted small">Patient</div>
                <div class="fs-5 fw-semibold"><?= esc($appointment['patient']) ?></div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small">Therapist</div>
                <div class="fs-5 fw-semibold"><?= esc($appointment['therapist']) ?></div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small">Condition</div>
                <div class="fs-5 fw-semibold"><?= esc($appointment['patient_condition']) ?></div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small">Contact</div>
                <div class="fs-5 fw-semibold"><?= esc($appointment['contact']) ?></div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small">Date</div>
                <div class="fs-5 fw-semibold"><?= date('M d, Y', strtotime($appointment['date'])) ?></div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small">Time</div>
                <div class="fs-5 fw-semibold"><?= date('g:i A', strtotime($appointment['time'])) ?></div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small">Session</div>
                <div class="fs-5 fw-semibold"><?= esc($appointment['session']) ?></div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small">Status</div>
                <div>
                    <?php if ($appointment['status'] == 'Upcoming'): ?>
                        <span class="badge bg-info">Upcoming</span>
                    <?php elseif ($appointment['status'] == 'Completed'): ?>
                        <span class="badge bg-success">Completed</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Cancelled</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-12">
                <div class="text-muted small">Notes</div>
                <div class="fs-6"><?= nl2br(esc($appointment['notes'] ?? 'No notes provided.')) ?></div>
            </div>
        </div>
    </div>
</div>

<?= view('layouts/footer') ?>
