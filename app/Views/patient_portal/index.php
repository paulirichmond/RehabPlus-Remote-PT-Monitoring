<?php $pageTitle = 'Patient Portal - RehabPlus'; ?>
<?= view('layouts/header') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <p class="page-title mb-1">Welcome, <?= esc($patient['name']) ?></p>
        <p class="page-subtitle mb-0">Your rehabilitation progress and appointments</p>
    </div>
    <form method="post" action="<?= site_url('logout') ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline-secondary">Log out</button>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm p-3 h-100">
            <div class="text-muted small">Condition</div>
            <div class="fs-5 fw-semibold mt-1"><?= esc($patient['condition']) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm p-3 h-100">
            <div class="text-muted small">Exercise Compliance</div>
            <div class="fs-2 fw-bold text-success"><?= esc($compliance) ?>%</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm p-3 h-100">
            <div class="text-muted small">Average Pain</div>
            <div class="fs-2 fw-bold text-danger"><?= esc($avgPain) ?> <span class="fs-6 text-muted">/ 10</span></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Medical Summary</div>
            <div class="card-body">
                <?= !empty($patient['medical_summary']) ? nl2br(esc($patient['medical_summary'])) : '<span class="text-muted">No summary has been added yet.</span>' ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Exercise / Therapy Plan</div>
            <div class="card-body">
                <?= !empty($patient['therapy_plan']) ? nl2br(esc($patient['therapy_plan'])) : '<span class="text-muted">No therapy plan has been added yet.</span>' ?>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mt-4">
    <div class="card-header bg-white fw-semibold">Upcoming Appointments</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th>Date</th><th>Time</th><th>Therapist</th><th>Session</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (empty($appointments)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No appointments found.</td></tr>
            <?php else: ?>
                <?php foreach ($appointments as $appointment): ?>
                    <tr>
                        <td><?= esc(date('M d, Y', strtotime($appointment['date']))) ?></td>
                        <td><?= esc(date('g:i A', strtotime($appointment['time']))) ?></td>
                        <td><?= esc($appointment['therapist']) ?></td>
                        <td><?= esc($appointment['session']) ?></td>
                        <td><span class="badge bg-info"><?= esc($appointment['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= view('layouts/footer') ?>
