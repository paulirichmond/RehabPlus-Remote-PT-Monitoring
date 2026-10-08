<?php

$pageTitle = 'Patients – RehabPlus';

?>

<?= view('layouts/header') ?>


<!-- SUCCESS MESSAGE -->

<?php if (session()->getFlashdata('success')): ?>

    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm">

        <i class="bi bi-check-circle-fill me-2"></i>

        <?= esc(session()->getFlashdata('success')) ?>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
        ></button>

    </div>

<?php endif; ?>


<!-- ERROR MESSAGE -->

<?php if (session()->getFlashdata('error')): ?>

    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm">

        <i class="bi bi-exclamation-circle-fill me-2"></i>

        <?= esc(session()->getFlashdata('error')) ?>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
        ></button>

    </div>

<?php endif; ?>


<!-- PAGE HEADER -->

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <p class="page-title">

            <i
                class="bi bi-people me-2"
                style="color:#0e9aaa;"
            ></i>

            Patients

        </p>

        <p class="page-subtitle mb-0">
            Manage and track all registered patients
        </p>

    </div>


    <a
        href="<?= site_url('patients/create') ?>"
        class="btn btn-primary btn-sm d-flex align-items-center gap-1"
    >

        <i class="bi bi-plus-lg"></i>

        Add Patient

    </a>

</div>


<!-- PATIENT TABLE -->

<div class="card shadow-sm">

    <div class="card-body border-bottom">
        <form method="get" action="<?= site_url('patients') ?>" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label for="patient-search" class="form-label small fw-semibold">Search patients</label>
                <input id="patient-search" type="search" name="search" class="form-control"
                       value="<?= esc($search ?? '') ?>" placeholder="Name or condition">
            </div>
            <div class="col-md-4">
                <label for="condition-filter" class="form-label small fw-semibold">Condition</label>
                <select id="condition-filter" name="condition" class="form-select">
                    <option value="">All conditions</option>
                    <?php foreach ($conditions ?? [] as $condition): ?>
                        <option value="<?= esc($condition) ?>" <?= ($selectedCondition ?? '') === $condition ? 'selected' : '' ?>>
                            <?= esc($condition) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                <a href="<?= site_url('patients') ?>" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-hover mb-0">

                <thead class="table-light">

                    <tr>

                        <th>
                            Patient
                        </th>

                        <th>
                            Condition
                        </th>

                        <th>
                            Added
                        </th>

                        <th class="text-end">
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php if (empty($data)): ?>

                        <tr>

                            <td
                                colspan="4"
                                class="text-center text-muted py-5"
                            >

                                <i
                                    class="bi bi-people fs-3 d-block mb-2 opacity-50"
                                ></i>

                                No patients found.

                            </td>

                        </tr>

                    <?php else: ?>


                        <?php foreach ($data as $patient): ?>

                            <tr>


                                <!-- PATIENT -->

                                <td>

                                    <div
                                        class="d-flex align-items-center gap-3"
                                    >


                                        <?php if (!empty($patient['avatar'])): ?>

                                            <img
                                                src="<?= base_url(
                                                    'uploads/avatars/' .
                                                    $patient['avatar']
                                                ) ?>"
                                                class="rounded-circle"
                                                width="38"
                                                height="38"
                                                style="object-fit:cover;"
                                            >

                                        <?php else: ?>

                                            <span
                                                class="rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-bold"
                                                style="
                                                    width:38px;
                                                    height:38px;
                                                    font-size:.8rem;
                                                    background:#0e9aaa;
                                                    flex-shrink:0;
                                                "
                                            >

                                                <?= strtoupper(
                                                    substr(
                                                        $patient['name'],
                                                        0,
                                                        1
                                                    )
                                                ) ?>

                                            </span>

                                        <?php endif; ?>


                                        <a
                                            href="<?= site_url(
                                                'patients/' .
                                                $patient['id']
                                            ) ?>"
                                            class="fw-semibold text-decoration-none text-dark"
                                        >

                                            <?= esc($patient['name']) ?>

                                        </a>

                                    </div>

                                </td>


                                <!-- CONDITION -->

                                <td class="text-muted small align-middle">

                                    <?= esc($patient['condition']) ?>

                                </td>


                                <!-- DATE -->

                                <td class="text-muted small align-middle">

                                    <?= !empty($patient['created_at'])
                                        ? date(
                                            'M j, Y g:i A',
                                            strtotime($patient['created_at'])
                                        )
                                        : '—'
                                    ?>

                                </td>


                                <!-- ACTIONS -->

                                <td class="text-end align-middle">


                                    <!-- VIEW -->

                                    <a
                                        href="<?= site_url(
                                            'patients/' .
                                            $patient['id']
                                        ) ?>"
                                        class="btn btn-sm btn-outline-secondary me-1"
                                    >
                                        View
                                    </a>


                                    <!-- EDIT -->

                                    <a
                                        href="<?= site_url(
                                            'patients/' .
                                            $patient['id'] .
                                            '/edit'
                                        ) ?>"
                                        class="btn btn-sm btn-outline-primary me-1"
                                    >
                                        Edit
                                    </a>


                                    <!-- DELETE -->

                                    <form
                                        method="post"
                                        action="<?= site_url(
                                            'patients/' .
                                            $patient['id'] .
                                            '/delete'
                                        ) ?>"
                                        class="d-inline"
                                        onsubmit="return confirm('Delete this patient and all their records?')"
                                    >

                                        <?= csrf_field() ?>

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-danger"
                                        >
                                            Delete
                                        </button>

                                    </form>


                                </td>

                            </tr>

                        <?php endforeach; ?>


                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<?= view('layouts/footer') ?>