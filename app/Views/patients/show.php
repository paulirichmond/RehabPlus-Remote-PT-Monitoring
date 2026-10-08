<?php $pageTitle = esc($patient['name']) . ' – RehabPlus'; ?>
<?= view('layouts/header') ?>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm">
        <i class="bi bi-check-circle-fill me-2"></i><?= session()->getFlashdata('success') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <a href="<?= site_url('patients') ?>" class="text-muted small text-decoration-none d-inline-flex align-items-center gap-1 mb-1">
            <i class="bi bi-arrow-left"></i>Back to Patients
        </a>
        <div class="d-flex align-items-center gap-3 mt-1">
            <span class="rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-bold"
                  style="width:46px;height:46px;font-size:1rem;background:#0e9aaa;flex-shrink:0;">
                <?= strtoupper(substr($patient['name'], 0, 1)) ?>
            </span>
            <div>
                <p class="page-title mb-0"><?= esc($patient['name']) ?></p>
                <span class="badge rounded-pill" style="background:#e0f7fa;color:#0b7a88;font-size:.75rem;"><?= esc($patient['condition']) ?></span>
                <div class="text-muted small mt-1">
                    Added <?= !empty($patient['created_at']) ? date('M j, Y g:i A', strtotime($patient['created_at'])) : '—' ?>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 mt-2">
        <a href="<?= site_url('patients/' . $patient['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <a href="<?= site_url('patients/' . $patient['id'] . '/exercises/create') ?>" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Add Record
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-file-medical me-2" style="color:#0e9aaa;"></i>Medical Summary
            </div>
            <div class="card-body">
                <?= !empty($patient['medical_summary']) ? nl2br(esc($patient['medical_summary'])) : '<span class="text-muted">No medical summary recorded.</span>' ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-clipboard2-pulse me-2" style="color:#0e9aaa;"></i>Exercise / Therapy Plan
            </div>
            <div class="card-body">
                <?= !empty($patient['therapy_plan']) ? nl2br(esc($patient['therapy_plan'])) : '<span class="text-muted">No therapy plan recorded.</span>' ?>
            </div>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm p-3 text-center h-100">
            <div class="text-muted small mb-1">Total Sessions</div>
            <div class="fs-2 fw-bold"><?= count($records) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm p-3 text-center h-100">
            <div class="text-muted small mb-1">Compliance Rate</div>
            <div class="fs-2 fw-bold <?= $compliance >= 80 ? 'text-success' : ($compliance >= 50 ? 'text-warning' : 'text-danger') ?>"><?= $compliance ?>%</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm p-3 text-center h-100">
            <div class="text-muted small mb-1">Avg. Pain Level</div>
            <div class="fs-2 fw-bold <?= $avgPain <= 3 ? 'text-success' : ($avgPain <= 6 ? 'text-warning' : 'text-danger') ?>"><?= $avgPain ?> <span class="fs-6 fw-normal text-muted">/ 10</span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <?php $rs = round($compliance - ($avgPain * 5), 1); ?>
        <div class="card shadow-sm p-3 text-center h-100">
            <div class="text-muted small mb-1">Recovery Score</div>
            <div class="fs-2 fw-bold <?= $rs >= 60 ? 'text-success' : ($rs >= 30 ? 'text-warning' : 'text-danger') ?>"><?= $rs ?></div>
        </div>
    </div>
</div>

<!-- Patient Progress Report -->
<?php if (!empty($trend)): ?>
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-graph-up me-2" style="color:#0e9aaa;"></i>Patient Progress Report
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">Pain level and exercise compliance over the latest recorded days.</p>
        <canvas id="painChart" height="80"></canvas>
    </div>
</div>
<?php endif; ?>

<!-- Exercise Records -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-check me-2" style="color:#0e9aaa;"></i>Exercise Records</span>
        <a href="<?= site_url('patients/' . $patient['id'] . '/exercises/create') ?>" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Add
        </a>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>Exercise</th><th>Sets (Done / Rx)</th><th>Pain</th><th>Notes</th><th>Date</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
            <?php if (empty($records)): ?>
                <tr><td colspan="6" class="text-center text-muted py-5">
                    <i class="bi bi-clipboard fs-3 d-block mb-2 opacity-50"></i>No records yet.
                </td></tr>
            <?php else: ?>
                <?php foreach ($records as $r):
                    $pain = (int)$r['pain_level'];
                    $painClass = $pain <= 3 ? 'badge-pain-low' : ($pain <= 6 ? 'badge-pain-mid' : 'badge-pain-high');
                ?>
                <tr>
                    <td class="fw-semibold"><?= esc($r['exercise_name']) ?></td>
                    <td><span class="fw-semibold"><?= $r['sets_completed'] ?></span> / <?= $r['sets_prescribed'] ?></td>
                    <td><span class="badge <?= $painClass ?>"><?= $pain ?> / 10</span></td>
                    <td class="text-muted small"><?= esc($r['notes'] ?? '—') ?></td>
                    <td class="text-muted small"><?= date('M j, Y g:i A', strtotime($r['recorded_at'])) ?></td>
                    <td class="text-end">
                        <a href="<?= site_url('patients/' . $patient['id'] . '/exercises/' . $r['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary me-1">Edit</a>
                        <form method="post" action="<?= site_url('patients/' . $patient['id'] . '/exercises/' . $r['id'] . '/delete') ?>"
                              class="d-inline" onsubmit="return confirm('Delete this record?')">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($trend)): ?>
<script>
const ctx = document.getElementById('painChart');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($trend, 'day')) ?>,
        datasets: [{
            label: 'Avg Pain Level',
            data: <?= json_encode(array_column($trend, 'avg_pain')) ?>,
            borderColor: '#0e9aaa',
            backgroundColor: 'rgba(14,154,170,0.08)',
            tension: 0.4,
            fill: true,
            pointRadius: 4,
            pointBackgroundColor: '#0e9aaa',
            yAxisID: 'pain',
        }, {
            label: 'Compliance %',
            data: <?= json_encode(array_column($trend, 'compliance_rate')) ?>,
            borderColor: '#22c55e',
            backgroundColor: 'rgba(34,197,94,0.08)',
            tension: 0.4,
            fill: false,
            pointRadius: 4,
            pointBackgroundColor: '#22c55e',
            yAxisID: 'compliance',
        }]
    },
    options: {
        scales: {
            pain: { type: 'linear', position: 'left', min: 0, max: 10, title: { display: true, text: 'Pain' } },
            compliance: { type: 'linear', position: 'right', min: 0, max: 100, title: { display: true, text: 'Compliance %' }, grid: { drawOnChartArea: false } }
        },
        datasets: {
            line: { spanGaps: true }
        },
        plugins: { legend: { display: true } }
    }
});
</script>
<?php endif; ?>

<?= view('layouts/footer') ?>
