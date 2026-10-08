<?php

$isEdit = $patient !== null;

$pageTitle = ($isEdit ? 'Edit Patient' : 'Add Patient') . ' – RehabPlus';

?>

<?= view('layouts/header') ?>

<div class="mb-4">

    <a
        href="<?= site_url('patients') ?>"
        class="text-muted small text-decoration-none d-inline-flex align-items-center gap-1 mb-1"
    >
        <i class="bi bi-arrow-left"></i>
        Back to Patients
    </a>

    <p class="page-title mt-1">
        <?= $isEdit ? 'Edit Patient' : 'Add Patient' ?>
    </p>

</div>


<div class="card shadow-sm" style="max-width:520px">

    <div class="card-body p-4">

        <?php if (!empty($errors)): ?>

            <div class="alert alert-danger border-0">

                <ul class="mb-0 small">

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?= esc($error) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <form
            method="post"
            action="<?= $isEdit
                ? site_url('patients/' . $patient['id'])
                : site_url('patients') ?>"
            enctype="multipart/form-data"
        >

            <?= csrf_field() ?>


            <!-- NAME -->

            <div class="mb-3">

                <label class="form-label fw-semibold small">
                    Full Name
                </label>

                <input
                    type="text"
                    name="name"
                    class="form-control"
                    value="<?= esc($patient['name'] ?? old('name')) ?>"
                    placeholder="Enter patient's full name"
                    required
                >

            </div>


            <!-- CONDITION -->

            <div class="mb-3">

                <label class="form-label fw-semibold small">
                    Condition / Diagnosis
                </label>

                <input
                    type="text"
                    name="condition"
                    class="form-control"
                    value="<?= esc($patient['condition'] ?? old('condition')) ?>"
                    placeholder="Enter condition or diagnosis"
                    required
                >

            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold small">Medical Summary</label>
                <textarea name="medical_summary" class="form-control" rows="3" maxlength="1000"
                          placeholder="Relevant medical history, precautions, or goals..."><?= esc($patient['medical_summary'] ?? old('medical_summary')) ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold small">Exercise / Therapy Plan</label>
                <textarea name="therapy_plan" class="form-control" rows="3" maxlength="1000"
                          placeholder="Prescribed exercises, frequency, and therapy goals..."><?= esc($patient['therapy_plan'] ?? old('therapy_plan')) ?></textarea>
            </div>


            <!-- PROFILE PHOTO -->

            <div class="mb-4">

                <label class="form-label fw-semibold small">

                    Profile Photo

                    <span class="text-muted fw-normal">
                        (optional, max 2MB)
                    </span>

                </label>


                <?php if (!empty($patient['avatar'])): ?>

                    <div class="mb-2">

                        <img
                            src="<?= base_url('uploads/avatars/' . $patient['avatar']) ?>"
                            class="rounded-circle"
                            width="56"
                            height="56"
                            style="object-fit:cover;"
                        >

                    </div>

                <?php endif; ?>


                <input
                    type="file"
                    name="avatar"
                    class="form-control"
                    accept="image/*"
                >

            </div>


            <!-- BUTTONS -->

            <div class="d-flex gap-2">

                <button
                    type="submit"
                    class="btn btn-primary px-4"
                >
                    <?= $isEdit ? 'Update Patient' : 'Save Patient' ?>
                </button>


                <a
                    href="<?= site_url('patients') ?>"
                    class="btn btn-outline-secondary"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>


<?= view('layouts/footer') ?>