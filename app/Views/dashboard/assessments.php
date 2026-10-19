<?php $pageTitle = 'Assessments & Goals – RehabPlus'; ?>
<?= view('layouts/header') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <p class="page-title mb-1">Assessments &amp; Goals</p>
        <p class="page-subtitle mb-0">Track patient evaluation progress, scores, and treatment goals.</p>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0">Add assessment</h5>
    </div>
    <div class="card-body">
        <form method="post" action="<?= site_url('assessments/save') ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Patient</label>
                    <input type="text" name="patient" class="form-control" placeholder="Patient name" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Therapist</label>
                    <input type="text" name="therapist" class="form-control" placeholder="Therapist name" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Type</label>
                    <input type="text" name="type" class="form-control" placeholder="Mobility" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Score</label>
                    <input type="text" name="score" class="form-control" placeholder="82%" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Date</label>
                    <input type="date" name="date" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">Goal</label>
                    <textarea name="goal" class="form-control" rows="3" placeholder="Describe the treatment goal..." required></textarea>
                </div>
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary">Save assessment</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">Average score</span>
                    <i class="bi bi-bar-chart text-primary"></i>
                </div>
                <h4 class="mb-0"><?= esc($averageScore) ?></h4>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">Goal tracking</span>
                    <i class="bi bi-bullseye text-success"></i>
                </div>
                <h4 class="mb-0"><?= esc($goalTracking) ?></h4>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">Active patients</span>
                    <i class="bi bi-people text-info"></i>
                </div>
                <h4 class="mb-0"><?= esc($activePatients) ?></h4>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">Latest assessments</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Patient</th>
                        <th>Therapist</th>
                        <th>Type</th>
                        <th>Score</th>
                        <th>Goal</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assessments as $entry): ?>
                        <tr>
                            <td><?= esc($entry['patient']) ?></td>
                            <td><?= esc($entry['therapist']) ?></td>
                            <td><?= esc($entry['type']) ?></td>
                            <td><?= esc($entry['score']) ?></td>
                            <td><?= esc($entry['goal']) ?></td>
                            <td><?= esc($entry['date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= view('layouts/footer') ?>
