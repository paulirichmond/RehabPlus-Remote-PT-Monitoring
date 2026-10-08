<?php $pageTitle = 'Therapy Notes & Care Plans – RehabPlus'; ?>
<?= view('layouts/header') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <p class="page-title mb-1">Therapy Notes &amp; Care Plans</p>
        <p class="page-subtitle mb-0">Track patient progress, therapist notes, and treatment plans.</p>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0">Add therapy note</h5>
    </div>
    <div class="card-body">
        <form method="post" action="<?= site_url('notes/save') ?>">
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
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Topic</label>
                    <input type="text" name="topic" class="form-control" placeholder="Mobility progress" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Date</label>
                    <input type="date" name="date" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">Care plan / note</label>
                    <textarea name="note" class="form-control" rows="4" placeholder="Write the patient progress note here..." required></textarea>
                </div>
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary">Save note</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">Recent entries</span>
                    <i class="bi bi-journal-check text-primary"></i>
                </div>
                <h4 class="mb-0"><?= esc($recentCount) ?></h4>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">Today notes</span>
                    <i class="bi bi-calendar-event text-success"></i>
                </div>
                <h4 class="mb-0"><?= esc($todayNotes) ?></h4>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">Latest care plan notes</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Patient</th>
                        <th>Therapist</th>
                        <th>Topic</th>
                        <th>Note</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notes as $entry): ?>
                        <tr>
                            <td><?= esc($entry['patient']) ?></td>
                            <td><?= esc($entry['therapist']) ?></td>
                            <td><?= esc($entry['topic']) ?></td>
                            <td><?= esc($entry['note']) ?></td>
                            <td><?= esc($entry['date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= view('layouts/footer') ?>
