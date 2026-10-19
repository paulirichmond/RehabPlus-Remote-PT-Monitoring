<?php $pageTitle = 'Dashboard – RehabPlus'; ?>
<?= view('layouts/header') ?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h2 class="page-title mb-1">
            RehabPlus Dashboard
        </h2>

        <p class="page-subtitle mb-0">
            Physical Therapy Recovery Monitoring
        </p>

    </div>

    <a href="<?= site_url('patients/create') ?>"
       class="btn btn-primary d-flex align-items-center gap-2">

        <i class="bi bi-plus-lg"></i>

        Add Patient

    </a>

</div>

<!-- STAT CARDS -->

<div class="row g-4 mb-4">

    <div class="col-md-4">

        <div class="card p-4">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <div class="text-secondary mb-2">
                        Total Patients
                    </div>

                    <div class="display-5 fw-bold text-info">
                        <?= $totalPatients ?>
                    </div>

                </div>

                <i class="bi bi-people-fill fs-1 text-info"></i>

            </div>

        </div>

    </div>

    <div class="col-md-4">

        <div class="card p-4">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <div class="text-secondary mb-2">
                        Avg Compliance
                    </div>

                    <div class="display-5 fw-bold text-success">
                        <?= $avgCompliance ?>%
                    </div>

                </div>

                <i class="bi bi-clipboard2-check-fill fs-1 text-success"></i>

            </div>

        </div>

    </div>

    <div class="col-md-4">

        <div class="card p-4">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <div class="text-secondary mb-2">
                        Avg Pain Level
                    </div>

                    <div class="display-5 fw-bold text-danger">
                        <?= $avgPain ?>
                    </div>

                </div>

                <i class="bi bi-heart-pulse-fill fs-1 text-danger"></i>

            </div>

        </div>

    </div>

</div>

<!-- CHARTS -->

<div class="row g-4 mb-4">

    <div class="col-lg-8">

        <div class="card">

            <div class="card-header">
                Recovery Analytics
            </div>

            <div class="card-body">

                <canvas id="recoveryChart"></canvas>

                <?php if (empty($hasRecoveryData)): ?>
                    <div class="alert alert-info mt-3 mb-0">
                        Add an exercise record from a patient profile to show recovery progress and pain analytics.
                    </div>
                <?php endif; ?>

            </div>

        </div>

    </div>

    <div class="col-lg-4">

        <div class="card">

            <div class="card-header">
                Patient Conditions
            </div>

            <div class="card-body">

                <canvas id="conditionChart"></canvas>

            </div>

        </div>

    </div>

</div>

<!-- PATIENT TABLE -->

<div class="card">

    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">

        <span>
            Patient Recovery Overview
        </span>

        <div class="input-group" style="width:250px;">

            <span class="input-group-text">
                <i class="bi bi-search"></i>
            </span>

            <input type="text"
                   id="patientSearch"
                   class="form-control"
                   placeholder="Search patient...">

        </div>

    </div>

    <div class="card-body p-0">

        <table class="table table-hover align-middle mb-0"
               id="patientsTable">

            <thead>

                <tr>

                    <th>Patient</th>
                    <th>Condition</th>
                    <th>Sessions</th>
                    <th>Compliance</th>
                    <th>Pain</th>
                    <th>Recovery</th>
                    <th></th>

                </tr>

            </thead>

            <tbody>

            <?php foreach ($patientStats as $p): ?>

                <tr>

                    <td>

                        <div class="d-flex align-items-center gap-2">

                            <div class="rounded-circle bg-info text-dark fw-bold d-flex align-items-center justify-content-center"
                                 style="width:38px;height:38px;">

                                <?= strtoupper(substr($p['name'],0,1)) ?>

                            </div>

                            <?= esc($p['name']) ?>

                        </div>

                    </td>

                    <td><?= esc($p['condition']) ?></td>

                    <td><?= $p['total_sessions'] ?></td>

                    <td>

                        <div class="progress"
                             style="height:8px;">

                            <div class="progress-bar bg-info"
                                 style="width:<?= min($p['compliance_rate'],100) ?>%">

                            </div>

                        </div>

                        <small>
                            <?= $p['compliance_rate'] ?>%
                        </small>

                    </td>

                    <td>

                        <span class="badge bg-warning text-dark">

                            <?= $p['avg_pain'] ?>/10

                        </span>

                    </td>

                    <td>

                        <span class="text-success fw-semibold">

                            <?= $p['recovery_score'] ?>%

                        </span>

                    </td>

                    <td>

                        <a href="<?= site_url('patients/' . $p['id']) ?>"
                           class="btn btn-sm btn-outline-secondary">

                            View

                        </a>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<script>

// SEARCH

const searchInput = document.getElementById('patientSearch');

const rows = document.querySelectorAll('#patientsTable tbody tr');

searchInput.addEventListener('keyup', function(){

    const search = this.value.toLowerCase();

    rows.forEach(row => {

        const text = row.innerText.toLowerCase();

        row.style.display =
            text.includes(search)
            ? ''
            : 'none';

    });

});

// LINE CHART

new Chart(document.getElementById('recoveryChart'), {

    type:'line',

    data:{
        labels:<?= json_encode($recoveryLabels ?? []) ?>,

        datasets:[{
            label:'Recovery Progress',
            data:<?= json_encode($recoveryValues ?? []) ?>,

            borderColor:'#14b8a6',

            backgroundColor:'rgba(20,184,166,.15)',

            fill:true,

            tension:.4
        }]
    }

});

// DOUGHNUT

new Chart(document.getElementById('conditionChart'), {

    type:'doughnut',

    data:{
        labels:<?= json_encode(array_keys($conditionCounts ?? [])) ?>,

        datasets:[{
            data:<?= json_encode(array_values($conditionCounts ?? [])) ?>,

            backgroundColor:[
                '#14b8a6',
                '#0ea5e9',
                '#8b5cf6',
                '#f59e0b'
            ]
        }]
    }

});

</script>

<?= view('layouts/footer') ?>