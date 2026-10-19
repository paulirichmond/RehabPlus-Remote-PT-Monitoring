<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Out – RehabPlus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f0f4f8; }
        .card-box { max-width: 420px; }
        .brand-bar { background: #1a3c5e; }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">

<div class="brand-bar text-white text-center py-3 fw-bold fs-5">
    <i class="bi bi-activity me-2"></i>RehabPlus
</div>

<div class="d-flex flex-grow-1 align-items-center justify-content-center py-5">
    <div class="card-box w-100 px-3">
        <div class="card shadow-sm border-0 text-center">
            <div class="card-body p-4">
                <div class="mb-3">
                    <i class="bi bi-box-arrow-right text-danger" style="font-size:2.5rem;"></i>
                </div>
                <h5 class="fw-semibold mb-1">Sign Out</h5>
                <p class="text-muted small mb-4">Are you sure you want to sign out of RehabPlus?</p>

                <form action="<?= site_url('logout') ?>" method="post">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger w-100 mb-2">
                        <i class="bi bi-box-arrow-right me-1"></i>Yes, Sign Out
                    </button>
                </form>

                <a href="<?= site_url('dashboard') ?>" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-arrow-left me-1"></i>Cancel
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
