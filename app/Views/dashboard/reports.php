<?php $pageTitle = 'Reports & PDF Export – RehabPlus'; ?>
<?= view('layouts/header') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <p class="page-title mb-1">Reports &amp; PDF Export</p>
        <p class="page-subtitle mb-0">Useful for clinic documentation.</p>
    </div>
</div>

<div class="row g-4">
    <?php foreach ($reportTypes as $reportName => $reportType): ?>
        <div class="col-md-6 col-xl-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center mb-3" style="width:42px;height:42px;">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <h6 class="mb-2"><?= esc($reportName) ?></h6>
                        <small class="text-muted">Generate a PDF or CSV export for this report.</small>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <a href="<?= site_url('reports/export?type=' . urlencode($reportType)) ?>" class="btn btn-outline-primary btn-sm flex-fill">CSV</a>
                        <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" onclick="window.print()">PDF</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?= view('layouts/footer') ?>
