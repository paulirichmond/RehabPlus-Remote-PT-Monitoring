<?php $pageTitle = 'Reports & Analytics – RehabPlus'; ?>
<?= view('layouts/header') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <p class="page-title mb-1">Reports & Analytics</p>
        <p class="page-subtitle mb-0">Clinic performance overview and patient activity summary.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= site_url('reports/export?type=patient-summary') ?>" class="btn btn-outline-primary">
            <i class="bi bi-file-earmark-arrow-down"></i> Export CSV
        </a>
        <a href="<?= site_url('reports/export?type=patient-summary') ?>" class="btn btn-primary" target="_blank">
            <i class="bi bi-file-earmark-pdf"></i> Export PDF
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100 border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Total Registered Patients</div>
                    <h3 class="mb-0 mt-2"><?= esc($totalRegisteredPatients) ?></h3>
                </div>
                <i class="bi bi-people-fill fs-2 text-primary"></i>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100 border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Active Patients</div>
                    <h3 class="mb-0 mt-2"><?= esc($activePatients) ?></h3>
                </div>
                <i class="bi bi-person-check-fill fs-2 text-success"></i>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100 border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">New Patients This Month</div>
                    <h3 class="mb-0 mt-2"><?= esc($newPatientsThisMonth) ?></h3>
                </div>
                <i class="bi bi-person-plus-fill fs-2 text-info"></i>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100 border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Total Appointments</div>
                    <h3 class="mb-0 mt-2"><?= esc($totalAppointments) ?></h3>
                </div>
                <i class="bi bi-calendar-event fs-2 text-warning"></i>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100 border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Completed Appointments</div>
                    <h3 class="mb-0 mt-2"><?= esc($completedAppointments) ?></h3>
                </div>
                <i class="bi bi-check-circle-fill fs-2 text-success"></i>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100 border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Pending Appointments</div>
                    <h3 class="mb-0 mt-2"><?= esc($pendingAppointments) ?></h3>
                </div>
                <i class="bi bi-hourglass-split fs-2 text-secondary"></i>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100 border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Cancelled Appointments</div>
                    <h3 class="mb-0 mt-2"><?= esc($cancelledAppointments) ?></h3>
                </div>
                <i class="bi bi-x-circle-fill fs-2 text-danger"></i>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100 border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Total Revenue</div>
                    <h3 class="mb-0 mt-2"><?= esc($totalRevenue) ?></h3>
                </div>
                <i class="bi bi-cash-stack fs-2 text-primary"></i>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100 border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Outstanding Payments</div>
                    <h3 class="mb-0 mt-2"><?= esc($outstandingPayments) ?></h3>
                </div>
                <i class="bi bi-wallet2 fs-2 text-warning"></i>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100 border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Treatment Completion Rate</div>
                    <h3 class="mb-0 mt-2"><?= esc($treatmentCompletionRate) ?></h3>
                </div>
                <i class="bi bi-bar-chart-line-fill fs-2 text-success"></i>
            </div>
        </div>
    </div>
</div>

<?= view('layouts/footer') ?>
