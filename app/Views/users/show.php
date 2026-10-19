<?php $pageTitle = esc($user['name']) . ' - RehabPlus'; ?>
<?= view('layouts/header') ?>

<div class="mb-4">
    <a href="<?= site_url('users') ?>" class="text-muted small text-decoration-none d-inline-flex align-items-center gap-1 mb-1">
        <i class="bi bi-arrow-left"></i>Back to Staff Roles
    </a>
    <p class="page-title mt-1">Account Details</p>
</div>

<div class="card shadow-sm" style="max-width:560px">
    <div class="card-body p-4">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="rounded-circle text-white fw-bold d-flex align-items-center justify-content-center"
                 style="width:64px;height:64px;font-size:24px;background:linear-gradient(135deg,#14b8a6,#06b6d4);">
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
            <div>
                <h1 class="h4 mb-1"><?= esc($user['name']) ?></h1>
                <span class="badge bg-info rounded-pill"><?= esc(ucfirst($user['role'])) ?></span>
            </div>
        </div>

        <dl class="row mb-4">
            <dt class="col-sm-4 text-muted">Email</dt>
            <dd class="col-sm-8"><?= esc($user['email']) ?></dd>
            <dt class="col-sm-4 text-muted">Account Status</dt>
            <dd class="col-sm-8"><?= (int) $user['is_active'] === 1 ? 'Active' : 'Inactive' ?></dd>
        </dl>

        <div class="d-flex gap-2">
            <?php if ($user['role'] !== 'patient'): ?>
                <a href="<?= site_url('users/' . $user['id'] . '/edit') ?>" class="btn btn-primary">Edit</a>
            <?php endif; ?>
            <form method="post" action="<?= site_url('users/' . $user['id'] . '/delete') ?>" onsubmit="return confirm('Delete this staff account?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger">Delete</button>
            </form>
            <a href="<?= site_url('users') ?>" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>
</div>

<?= view('layouts/footer') ?>
