<?php $isEdit = $user !== null;
$pageTitle = ($isEdit ? 'Edit User' : 'Add User') . ' – RehabPlus'; ?>
<?= view('layouts/header') ?>

<div class="mb-4">
    <a href="<?= site_url('users') ?>" class="text-muted small text-decoration-none d-inline-flex align-items-center gap-1 mb-1">
        <i class="bi bi-arrow-left"></i>Back to Users
    </a>
    <p class="page-title mt-1"><?= $isEdit ? 'Edit User' : 'Add User' ?></p>
</div>

<div class="card shadow-sm" style="max-width:520px">
    <div class="card-body p-4">
        <?php if (session()->getFlashdata('errors')): ?>
            <div class="alert alert-danger border-0">
                <ul class="mb-0 small"><?php foreach (session()->getFlashdata('errors') as $e): ?><li><?= esc($e) ?></li><?php endforeach ?></ul>
            </div>
        <?php endif ?>

        <form method="post" action="<?= $isEdit ? site_url('users/' . $user['id']) : site_url('users') ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label fw-semibold small">Full Name</label>
                <input type="text" name="name" class="form-control" value="<?= esc($user['name'] ?? old('name')) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold small">Email</label>
                <input type="email" name="email" class="form-control" value="<?= esc($user['email'] ?? old('email')) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold small">
                    Password <?= $isEdit ? '<span class="text-muted fw-normal">(leave blank to keep)</span>' : '' ?>
                </label>
                <input type="password" name="password" class="form-control" <?= $isEdit ? '' : 'required' ?>>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold small">Role</label>
                <?php if ($isEdit && ($user['role'] ?? '') === 'patient'): ?>
                    <input type="text" class="form-control" value="Patient" readonly>
                    <div class="form-text">Patient portal roles cannot be changed.</div>
                <?php else: ?>
                    <select name="role" class="form-select" required>
                        <option value="staff"      <?= ($user['role'] ?? '') === 'staff'      ? 'selected' : '' ?>>Staff</option>
                        <option value="therapist"  <?= ($user['role'] ?? '') === 'therapist'  ? 'selected' : '' ?>>Therapist</option>
                        <option value="manager"    <?= ($user['role'] ?? '') === 'manager'    ? 'selected' : '' ?>>Manager</option>
                        <option value="superadmin" <?= ($user['role'] ?? '') === 'superadmin' ? 'selected' : '' ?>>Super Admin</option>
                    </select>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4"><?= $isEdit ? 'Update' : 'Save User' ?></button>
                <a href="<?= site_url('users') ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?= view('layouts/footer') ?>
