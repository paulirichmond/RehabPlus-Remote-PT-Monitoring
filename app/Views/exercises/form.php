<?php $isEdit = $record !== null;
$pageTitle = ($isEdit ? 'Edit Record' : 'Add Record') . ' – RehabPlus'; ?>
<?= view('layouts/header') ?>

<div class="mb-4">
    <a href="<?= site_url('patients/' . $patientId) ?>" class="text-muted small text-decoration-none d-inline-flex align-items-center gap-1 mb-1">
        <i class="bi bi-arrow-left"></i><?= esc($patient['name']) ?>
    </a>
    <p class="page-title mt-1"><?= $isEdit ? 'Edit Exercise Record' : 'Add Exercise Record' ?></p>
</div>

<div class="card shadow-sm" style="max-width:560px">
    <div class="card-body p-4">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger border-0">
                <ul class="mb-0 small"><?php foreach ($errors as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <form method="post" action="<?= $isEdit ? site_url('patients/' . $patientId . '/exercises/' . $record['id']) : site_url('patients/' . $patientId . '/exercises') ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label fw-semibold small">Exercise Name</label>
                <input type="text" name="exercise_name" class="form-control" value="<?= esc($record['exercise_name'] ?? old('exercise_name')) ?>" required>
            </div>
            <div class="row g-3 mb-3">
                <div class="col">
                    <label class="form-label fw-semibold small">Sets Prescribed</label>
                    <input type="number" name="sets_prescribed" class="form-control" min="1" value="<?= esc($record['sets_prescribed'] ?? old('sets_prescribed', 3)) ?>" required>
                </div>
                <div class="col">
                    <label class="form-label fw-semibold small">Sets Completed</label>
                    <input type="number" name="sets_completed" class="form-control" min="0" value="<?= esc($record['sets_completed'] ?? old('sets_completed', 0)) ?>" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold small d-flex justify-content-between">
                    Pain Level (0–10)
                    <span class="badge rounded-pill" id="painBadge" style="background:#0e9aaa;">
                        <?= esc($record['pain_level'] ?? 0) ?> / 10
                    </span>
                </label>
                <input type="range" name="pain_level" class="form-range" min="0" max="10" step="1"
                    value="<?= esc($record['pain_level'] ?? old('pain_level', 0)) ?>"
                    oninput="document.getElementById('painBadge').textContent = this.value + ' / 10'">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold small">Date &amp; Time</label>
                <input type="datetime-local" name="recorded_at" class="form-control"
                    value="<?= esc(isset($record['recorded_at']) ? date('Y-m-d\TH:i', strtotime($record['recorded_at'])) : date('Y-m-d\TH:i')) ?>" required>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold small">Notes <span class="text-muted fw-normal">(optional)</span></label>
                <textarea name="notes" class="form-control" rows="2"><?= esc($record['notes'] ?? old('notes')) ?></textarea>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4"><?= $isEdit ? 'Update' : 'Save Record' ?></button>
                <a href="<?= site_url('patients/' . $patientId) ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?= view('layouts/footer') ?>
