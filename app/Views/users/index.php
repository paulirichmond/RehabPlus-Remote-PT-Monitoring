<?php $pageTitle = 'Users – RehabPlus'; ?>
<?= view('layouts/header') ?>

<div class="container-fluid py-4 px-4">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>
            <h1 class="fw-bold mb-1 page-title" style="font-size:32px;">
                Roles & Patient Accounts
            </h1>

            <p class="text-muted fs-5">
                Manage clinic staff and rehabilitation patient portal accounts
            </p>
        </div>

        <a href="<?= site_url('users/create') ?>"
           class="btn text-white px-4 py-2 rounded-4 shadow-sm"
           style="background:linear-gradient(135deg,#14b8a6,#2dd4bf); font-size:16px; font-weight:600;">

            <i class="bi bi-person-plus-fill me-2"></i>
            Add Staff Account

        </a>

    </div>


    <!-- STATS -->
    <div class="row g-4 mb-4">

        <div class="col-md-4">

            <div class="card border-0 rounded-5 shadow-sm p-4">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <h6 class="text-muted mb-2">
                            Staff Accounts
                        </h6>

                        <h1 class="fw-bold" style="font-size:48px;">
                            <?= count(array_filter($users, fn($u) => $u['role'] != 'patient')) ?>
                        </h1>

                    </div>

                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                         style="width:70px;height:70px;background:#dcfce7;">

                        <i class="bi bi-person-badge-fill"
                           style="font-size:32px;color:#15803d;"></i>

                    </div>

                </div>

            </div>

        </div>


        <div class="col-md-4">

            <div class="card border-0 rounded-5 shadow-sm p-4">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <h6 class="text-muted mb-2">
                            Patient Accounts
                        </h6>

                        <h1 class="fw-bold" style="font-size:48px;">
                            <?= count(array_filter($users, fn($u) => $u['role'] == 'patient')) ?>
                        </h1>

                    </div>

                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                         style="width:70px;height:70px;background:#dbeafe;">

                        <i class="bi bi-people-fill"
                           style="font-size:32px;color:#2563eb;"></i>

                    </div>

                </div>

            </div>

        </div>


        <div class="col-md-4">

            <div class="card border-0 rounded-5 shadow-sm p-4">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <h6 class="text-muted mb-2">
                            Active Accounts
                        </h6>

                        <h1 class="fw-bold" style="font-size:48px;">
                            <?= count(array_filter($users, fn($u) => (int) $u['is_active'] === 1)) ?>
                        </h1>

                    </div>

                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                         style="width:70px;height:70px;background:#dbeafe;">

                        <i class="bi bi-check-circle-fill"
                           style="font-size:32px;color:#2563eb;"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>



    <!-- STAFF ROLES -->
    <div id="staff-roles" class="card border-0 rounded-5 shadow-sm overflow-hidden mb-4">

        <div class="p-4 border-bottom d-flex justify-content-between align-items-center">

            <h2 class="fw-bold mb-0" style="font-size:24px;">
                Staff Roles
            </h2>

            <div class="input-group" style="max-width:320px;">

                <span class="input-group-text bg-white border-end-0 rounded-start-4">
                    <i class="bi bi-search"></i>
                </span>

                <input type="text"
                       class="form-control border-start-0 rounded-end-4 py-2"
                       placeholder="Search staff...">

            </div>

        </div>


        <div class="table-responsive">

            <table class="table align-middle mb-0">

                <thead style="background:#f8fafc;">

                    <tr>
                        <th class="px-4 py-3 text-muted">ACCOUNT</th>
                        <th class="py-3 text-muted">EMAIL</th>
                        <th class="py-3 text-muted">ROLE</th>
                        <th class="py-3 text-muted text-end pe-5">ACTIONS</th>
                    </tr>

                </thead>

                <tbody>

                <?php $staffUsers = array_filter($users, fn($user) => $user['role'] !== 'patient'); ?>

                <?php if (empty($staffUsers)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">No staff accounts yet.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach($staffUsers as $user): ?>

                    <tr>

                        <td class="px-4 py-3">

                            <div class="d-flex align-items-center gap-3">

                                <div class="rounded-circle text-white fw-bold d-flex align-items-center justify-content-center"
                                     style="width:55px;height:55px;font-size:20px;background:linear-gradient(135deg,#14b8a6,#06b6d4);">

                                    <?= strtoupper(substr($user['name'],0,1)) ?>

                                </div>

                                <div>

                                    <div class="fw-bold" style="font-size:18px;">
                                        <?= esc($user['name']) ?>
                                    </div>

                                    <small class="text-muted">

                                        <?php if($user['role'] == 'superadmin'): ?>
                                            System Administrator
                                        <?php elseif($user['role'] == 'manager'): ?>
                                            Clinic Manager
                                        <?php elseif($user['role'] == 'staff'): ?>
                                            Rehab Staff Personnel
                                        <?php elseif($user['role'] == 'therapist'): ?>
                                            Physical Therapist
                                        <?php endif; ?>

                                    </small>

                                </div>

                            </div>

                        </td>

                        <td style="font-size:16px;">
                            <?= esc($user['email']) ?>
                        </td>

                        <td>

                            <?php
                                $badge = 'bg-info';

                                if($user['role'] == 'superadmin'){
                                    $badge = 'bg-danger';
                                }

                                if($user['role'] == 'manager'){
                                    $badge = 'bg-warning text-dark';
                                }
                            ?>

                            <span class="badge <?= $badge ?> rounded-pill px-3 py-2"
                                  style="font-size:14px;">

                                <?= ucfirst($user['role']) ?>

                            </span>

                        </td>

                        <td class="text-end pe-5">

                            <a href="<?= site_url('users/'.$user['id']) ?>"
                               class="btn btn-outline-secondary rounded-pill px-3 py-2">

                                View

                            </a>

                            <a href="<?= site_url('users/'.$user['id'].'/edit') ?>"
                               class="btn btn-outline-primary rounded-pill px-3 py-2">

                                Edit

                            </a>

                            <form method="post"
                                  action="<?= site_url('users/'.$user['id'].'/delete') ?>"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete this staff account?')">

                                <?= csrf_field() ?>

                                <button type="submit"
                                        class="btn btn-outline-danger rounded-pill px-3 py-2">

                                    Delete

                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>



    <!-- PATIENT ACCOUNTS -->
    <div id="patient-accounts" class="card border-0 rounded-5 shadow-sm overflow-hidden">

        <div class="p-4 border-bottom">

            <h2 class="fw-bold mb-2" style="font-size:24px;">
                Patient Portal Accounts
            </h2>

            <p class="text-muted mb-0">
                Give rehabilitation patients access to RehabPlus monitoring tools
            </p>

        </div>


        <!-- CREATE PATIENT -->
        <div class="p-4 border-bottom">

            <!-- FIXED FORM -->
            <form action="<?= site_url('users/create-patient') ?>" method="post">

                <?= csrf_field() ?>

                <input type="hidden" name="role" value="patient">

                <div class="row g-3">

                    <div class="col-md-4">

                        <label class="form-label fw-semibold">
                            Patient Full Name
                        </label>

                        <input type="text"
                               name="name"
                               class="form-control rounded-4 py-2"
                               placeholder="Enter patient name"
                               required>

                    </div>

                    <div class="col-md-4">

                        <label class="form-label fw-semibold">
                            Email Address
                        </label>

                        <input type="email"
                               name="email"
                               class="form-control rounded-4 py-2"
                               placeholder="patient@email.com"
                               required>

                    </div>

                    <div class="col-md-4">

                        <label class="form-label fw-semibold">
                            Temporary Password
                        </label>

                        <input type="password"
                               name="password"
                               class="form-control rounded-4 py-2"
                               placeholder="Create password"
                               required>

                    </div>

                </div>

                <button type="submit"
                        class="btn btn-success rounded-4 px-4 py-2 mt-4 fw-semibold">

                    <i class="bi bi-person-plus-fill me-2"></i>
                    Create Patient Account

                </button>

            </form>

        </div>



        <!-- PATIENT TABLE -->
        <div class="table-responsive">

            <table class="table align-middle mb-0">

                <thead style="background:#f8fafc;">

                    <tr>
                        <th class="px-4 py-3 text-muted">PATIENT</th>
                        <th class="py-3 text-muted">EMAIL</th>
                        <th class="py-3 text-muted">ACCOUNT TYPE</th>
                        <th class="py-3 text-muted text-end pe-5">ACTIONS</th>
                    </tr>

                </thead>

                <tbody>

                <?php $patientUsers = array_filter($users, fn($user) => $user['role'] === 'patient'); ?>

                <?php if (empty($patientUsers)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">No patient portal accounts yet.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach($patientUsers as $user): ?>

                    <?php if($user['role'] == 'patient'): ?>

                    <tr>

                        <td class="px-4 py-3">

                            <div class="d-flex align-items-center gap-3">

                                <div class="rounded-circle text-white fw-bold d-flex align-items-center justify-content-center"
                                     style="width:55px;height:55px;font-size:20px;background:#22c55e;">

                                    <?= strtoupper(substr($user['name'],0,1)) ?>

                                </div>

                                <div>

                                    <div class="fw-bold" style="font-size:18px;">
                                        <?= esc($user['name']) ?>
                                    </div>

                                    <small class="text-muted">
                                        Rehabilitation Patient
                                    </small>

                                </div>

                            </div>

                        </td>

                        <td style="font-size:16px;">
                            <?= esc($user['email']) ?>
                        </td>

                        <td>

                            <span class="badge bg-success rounded-pill px-3 py-2"
                                  style="font-size:14px;">

                                Patient

                            </span>

                        </td>

                        <td class="text-end pe-5">

                                     <a href="<?= site_url('users/'.$user['id']) ?>"
                               class="btn btn-outline-success rounded-pill px-3 py-2">

                                View

                            </a>

                        </td>

                    </tr>

                    <?php endif; ?>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?= view('layouts/footer') ?>