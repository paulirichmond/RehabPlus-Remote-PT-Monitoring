<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RehabPlus</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <script>
        if (localStorage.getItem('rehabplus-theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>

    <style>
        :root{
            --admin-bg:#eef2f7;
            --admin-surface:#ffffff;
            --admin-text:#0f172a;
            --admin-muted:#64748b;
            --admin-border:#e5e7eb;
        }

        html.dark-mode{
            color-scheme:dark;
        }

        body{
            background:var(--admin-bg);
            color:var(--admin-text);
            overflow-x:hidden;
            font-family:'Segoe UI',sans-serif;
        }

        .sidebar{
            width:280px;
            height:100vh;
            background:var(--admin-surface);
            border-right:1px solid var(--admin-border);
            position:fixed;
            left:0;
            top:0;
            padding:20px 18px 14px;
            overflow:hidden;
        }

        .main-content{
            margin-left:280px;
            min-height:100vh;
        }

        .topbar{
            height:72px;
            background:var(--admin-surface);
            border-bottom:1px solid var(--admin-border);
        }

        .nav-section{
            font-size:12px;
            font-weight:800;
            letter-spacing:0.12em;
            text-transform:uppercase;
            color:#64748b;
            margin:18px 8px 10px;
        }

        .menu-link{
            display:flex;
            align-items:center;
            gap:12px;
            padding:12px 14px;
            border-radius:14px;
            color:#334155;
            text-decoration:none;
            font-weight:600;
            margin-bottom:6px;
            font-size:0.92rem;
            line-height:1.2;
            transition:all 0.2s ease;
        }

        .menu-link.active{
            background:#dff7f2;
            color:#0f766e;
        }

        .menu-link:hover{
            background:#f1f5f9;
        }

        .admin-theme-toggle{
            width:40px;
            height:40px;
            border:1px solid var(--admin-border);
            border-radius:50%;
            background:var(--admin-surface);
            color:var(--admin-text);
            display:inline-flex;
            align-items:center;
            justify-content:center;
        }

        html.dark-mode body{
            --admin-bg:#0f172a;
            --admin-surface:#1e293b;
            --admin-text:#e2e8f0;
            --admin-muted:#94a3b8;
            --admin-border:#334155;
            --bs-body-bg:#0f172a;
            --bs-body-color:#e2e8f0;
            --bs-card-bg:#1e293b;
            --bs-tertiary-bg:#1e293b;
            --bs-secondary-bg:#1e293b;
            --bs-table-bg:transparent;
            --bs-table-color:#cbd5e1;
            --bs-border-color:#334155;
        }

        html.dark-mode .menu-link{
            color:#cbd5e1;
        }

        html.dark-mode .menu-link.active{
            background:#134e4a;
            color:#99f6e4;
        }

        html.dark-mode .menu-link:hover{
            background:#273449;
        }

        html.dark-mode .text-muted,
        html.dark-mode .text-secondary{
            color:var(--admin-muted) !important;
        }

        html.dark-mode .card,
        html.dark-mode .card-header,
        html.dark-mode .table-light,
        html.dark-mode .bg-white{
            background-color:var(--admin-surface) !important;
            color:var(--admin-text) !important;
        }

        html.dark-mode #staff-roles,
        html.dark-mode #patient-accounts,
        html.dark-mode #staff-roles > *,
        html.dark-mode #patient-accounts > *{
            background:var(--admin-surface) !important;
            background-color:var(--admin-surface) !important;
            color:var(--admin-text);
        }

        html.dark-mode body #staff-roles .input-group-text,
        html.dark-mode body #staff-roles .form-control,
        html.dark-mode body #patient-accounts .form-control,
        html.dark-mode body #patient-accounts .form-select{
            background:#0f172a !important;
            color:var(--admin-text) !important;
            border-color:var(--admin-border) !important;
        }

        html.dark-mode body #staff-roles thead,
        html.dark-mode body #patient-accounts thead,
        html.dark-mode body #staff-roles thead th,
        html.dark-mode body #patient-accounts thead th{
            background:#172235 !important;
            color:#cbd5e1 !important;
            border-color:var(--admin-border) !important;
        }

        html.dark-mode .table,
        html.dark-mode .table td,
        html.dark-mode .table th{
            color:#cbd5e1;
            border-color:var(--admin-border);
        }

        html.dark-mode .table-responsive,
        html.dark-mode .table-hover > tbody > tr:hover > *,
        html.dark-mode .table-light > *,
        html.dark-mode .table > :not(caption) > * > *{
            background-color:transparent !important;
        }

        html.dark-mode .form-control,
        html.dark-mode .form-select,
        html.dark-mode .input-group-text,
        html.dark-mode textarea{
            background:#0f172a !important;
            color:#ffffff !important;
            border-color:var(--admin-border);
        }

        html.dark-mode .form-control::placeholder{
            color:#ffffff !important;
            opacity:.75;
        }

        html.dark-mode .form-select option{
            background:#0f172a;
            color:#ffffff;
        }

        html.dark-mode .btn-outline-secondary{
            color:#cbd5e1;
            border-color:#64748b;
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">

    <h1 class="fw-bold mb-4">
        <span style="color:#14b8a6;">❤</span>
        RehabPlus
    </h1>

    <div class="nav-section">Overview</div>

    <a href="<?= site_url('dashboard') ?>" class="menu-link <?= uri_string() === '' || uri_string() === 'dashboard' ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i>
        Dashboard
    </a>

    <a href="<?= site_url('reports-analytics') ?>" class="menu-link <?= in_array(uri_string(), ['analytics', 'reports', 'reports-analytics'], true) ? 'active' : '' ?>">
        <i class="bi bi-graph-up-arrow"></i>
        Reports & Analytics
    </a>

    <div class="nav-section mt-4">Management</div>

    <a href="<?= site_url('patients') ?>" class="menu-link <?= str_starts_with(uri_string(),'patients') ? 'active' : '' ?>">
        <i class="bi bi-people-fill"></i>
        Patients
    </a>

    <a href="<?= site_url('appointments') ?>" class="menu-link <?= str_starts_with(uri_string(),'appointments') ? 'active' : '' ?>">
        <i class="bi bi-calendar-check"></i>
        Appointments
    </a>

    <a href="<?= site_url('billing') ?>" class="menu-link <?= uri_string() === 'billing' ? 'active' : '' ?>">
        <i class="bi bi-credit-card"></i>
        Billing and Payment Records
    </a>

    <a href="<?= site_url('inventory') ?>" class="menu-link <?= uri_string() === 'inventory' ? 'active' : '' ?>">
        <i class="bi bi-box-seam"></i>
        Inventory and Supplies
    </a>

    <a href="<?= site_url('schedule') ?>" class="menu-link <?= uri_string() === 'schedule' ? 'active' : '' ?>">
        <i class="bi bi-calendar-week"></i>
        Staff Schedule
    </a>

    <a href="<?= site_url('notes') ?>" class="menu-link <?= uri_string() === 'notes' ? 'active' : '' ?>">
        <i class="bi bi-journal-text"></i>
        Therapy Notes & Care Plans
    </a>

    <a href="<?= site_url('assessments') ?>" class="menu-link <?= uri_string() === 'assessments' ? 'active' : '' ?>">
        <i class="bi bi-clipboard2-pulse"></i>
        Assessments & Goals
    </a>

    <?php if (session()->get('user_role') === 'superadmin'): ?>
        <div class="nav-section mt-4">Admin</div>

        <a href="<?= site_url('users') ?>" class="menu-link <?= str_starts_with(uri_string(),'users') ? 'active' : '' ?>">
            <i class="bi bi-person-gear"></i>
            Users
        </a>
    <?php endif ?>

</div>


<!-- MAIN CONTENT -->
<div class="main-content">

    <!-- TOPBAR -->
    <div class="topbar d-flex justify-content-end align-items-center px-5">

        <div class="d-flex align-items-center gap-4">

            <div class="fw-semibold text-muted">
                <?= date('F d, Y') ?>
            </div>

            <button type="button" class="admin-theme-toggle" id="adminThemeToggle"
                    title="Toggle dark mode" aria-label="Toggle dark mode">
                <i class="bi bi-moon-fill"></i>
            </button>

            <div class="d-flex align-items-center gap-3 border rounded-pill px-3 py-2">

                <div class="rounded-circle bg-info text-white d-flex align-items-center justify-content-center"
                     style="width:45px;height:45px;font-weight:700;">

                    S

                </div>

                <div>
                    <div class="fw-bold">
                        Super Admin
                    </div>

                    <small class="text-muted">
                        Superadmin
                    </small>
                </div>

            </div>

            <a href="<?= site_url('logout') ?>"
               class="btn btn-outline-secondary rounded-pill px-4">

                <i class="bi bi-box-arrow-right me-2"></i>
                Logout

            </a>

        </div>

    </div>


    <!-- PAGE CONTENT -->
    <div class="p-4">
        <?= $this->renderSection('content') ?>
    </div>

</div>

<script>
    const adminThemeToggle = document.getElementById('adminThemeToggle');
    const adminDark = localStorage.getItem('rehabplus-theme') === 'dark';
    document.body.classList.toggle('dark-mode', adminDark);
    if (adminThemeToggle) {
        adminThemeToggle.innerHTML = adminDark
            ? '<i class="bi bi-sun-fill"></i>'
            : '<i class="bi bi-moon-fill"></i>';
        adminThemeToggle.addEventListener('click', () => {
            const dark = !document.body.classList.contains('dark-mode');
            document.body.classList.toggle('dark-mode', dark);
            document.documentElement.classList.toggle('dark-mode', dark);
            localStorage.setItem('rehabplus-theme', dark ? 'dark' : 'light');
            adminThemeToggle.innerHTML = dark
                ? '<i class="bi bi-sun-fill"></i>'
                : '<i class="bi bi-moon-fill"></i>';
        });
    }
</script>

</body>
</html>