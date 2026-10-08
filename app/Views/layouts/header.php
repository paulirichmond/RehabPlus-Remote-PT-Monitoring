<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title><?= $pageTitle ?? 'RehabPlus Dashboard' ?></title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

        <!-- Chart.js -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
          rel="stylesheet">

    <style>

        html.dark-mode{
            color-scheme:dark;
        }

        :root{

            --primary:#14b8a6;
            --primary-dark:#0f766e;
            --primary-light:#ccfbf1;

            --bg:#f4f7fb;
            --sidebar:#ffffff;
            --card:#ffffff;

            --text:#0f172a;
            --muted:#64748b;

            --border:#e2e8f0;

            --shadow:
                0 12px 35px rgba(15,23,42,.06);

            --sidebar-w:260px;
        }

        *{
            font-family:'Inter',sans-serif;
        }

        body{

            margin:0;

            background:var(--bg);

            color:var(--text);

            overflow-x:hidden;
            min-height:100vh;
        }

        html.dark-mode body{
            --bg:#0f172a;
            --sidebar:#111827;
            --card:#1e293b;
            --text:#e2e8f0;
            --muted:#94a3b8;
            --border:#334155;
            --shadow:0 12px 35px rgba(0,0,0,.22);
            --bs-body-bg:#0f172a;
            --bs-body-color:#e2e8f0;
            --bs-card-bg:#1e293b;
            --bs-tertiary-bg:#1e293b;
            --bs-secondary-bg:#1e293b;
            --bs-table-bg:transparent;
            --bs-table-color:#cbd5e1;
            --bs-border-color:#334155;
        }

        html.dark-mode body .navbar,
        html.dark-mode body .sidebar{
            background:var(--sidebar) !important;
        }

        html.dark-mode body .card,
        html.dark-mode body .card-header,
        html.dark-mode body .table-light,
        html.dark-mode body .bg-white{
            background-color:var(--card) !important;
            color:var(--text) !important;
        }

        html.dark-mode body .sidebar .nav-link{
            color:#e2e8f0;
        }

        html.dark-mode body .sidebar .nav-link:hover{
            background:#1f2937;
            color:#5eead4;
        }

        html.dark-mode body .nav-section{
            color:#94a3b8 !important;
            opacity:1;
        }

        html.dark-mode body .sidebar .nav-link.active{
            background:#134e4a;
            color:#a7f3d0;
        }

        html.dark-mode body .page-title,
        html.dark-mode body h1,
        html.dark-mode body h2,
        html.dark-mode body h3,
        html.dark-mode body h4,
        html.dark-mode body h5,
        html.dark-mode body h6,
        html.dark-mode body .fw-bold,
        html.dark-mode body .fw-semibold,
        html.dark-mode body .text-dark{
            color:var(--text) !important;
        }

        html.dark-mode body .table,
        html.dark-mode body .table td,
        html.dark-mode body .table th,
        html.dark-mode body .table thead th,
        html.dark-mode body .table thead,
        html.dark-mode body .table tbody,
        html.dark-mode body .table-responsive{
            color:#cbd5e1 !important;
            border-color:var(--border) !important;
            background-color:transparent !important;
        }

        html.dark-mode body #staff-roles,
        html.dark-mode body #patient-accounts,
        html.dark-mode body #staff-roles .table,
        html.dark-mode body #patient-accounts .table,
        html.dark-mode body #staff-roles .input-group-text,
        html.dark-mode body #patient-accounts .input-group-text,
        html.dark-mode body #staff-roles .form-control,
        html.dark-mode body #patient-accounts .form-control,
        html.dark-mode body #staff-roles .form-select,
        html.dark-mode body #patient-accounts .form-select,
        html.dark-mode body .card,
        html.dark-mode body .card-header{
            background-color:var(--card) !important;
            color:var(--text) !important;
            border-color:var(--border) !important;
        }

        html.dark-mode body .table thead th{
            background:#172235 !important;
            color:#cbd5e1 !important;
        }

        html.dark-mode body .input-group-text,
        html.dark-mode body .form-control,
        html.dark-mode body .form-select,
        html.dark-mode body textarea{
            background:#0f172a !important;
            color:#ffffff !important;
            border-color:var(--border) !important;
        }

        html.dark-mode body .text-muted,
        html.dark-mode body .text-secondary,
        html.dark-mode body .small,
        html.dark-mode body small{
            color:var(--muted) !important;
        }

        html.dark-mode body .badge,
        html.dark-mode body .btn-outline-secondary,
        html.dark-mode body .btn-outline-primary,
        html.dark-mode body .btn-outline-success,
        html.dark-mode body .btn-outline-danger{
            background-color:transparent !important;
        }

        html.dark-mode body .card-body,
        html.dark-mode body #patientsTable,
        html.dark-mode body #patientsTable thead,
        html.dark-mode body #patientsTable tbody,
        html.dark-mode body #patientsTable tr,
        html.dark-mode body #patientsTable td,
        html.dark-mode body #patientsTable th{
            background-color:transparent !important;
        }

        html.dark-mode body .table tbody tr:hover{
            background:#273449;
        }

        html.dark-mode body .table-responsive,
        html.dark-mode body .table-hover > tbody > tr:hover > *,
        html.dark-mode body .table-light > *,
        html.dark-mode body .table > :not(caption) > * > *{
            background-color:transparent !important;
        }

        html.dark-mode body .form-control,
        html.dark-mode body .form-select,
        html.dark-mode body .input-group-text,
        html.dark-mode body textarea{
            background:#0f172a !important;
            color:#ffffff !important;
            border-color:var(--border);
        }

        html.dark-mode body .form-control::placeholder{
            color:#ffffff !important;
            opacity:.75;
        }

        html.dark-mode body .form-select option{
            background:#0f172a;
            color:#ffffff;
        }

        html.dark-mode body .text-dark{
            color:var(--text) !important;
        }

        html.dark-mode body .text-muted,
        html.dark-mode body .text-secondary{
            color:var(--muted) !important;
        }

        html.dark-mode body .btn-outline-secondary{
            color:#cbd5e1;
            border-color:#64748b;
        }

        html.dark-mode body .user-chip{
            background:#1e293b;
        }

        .theme-toggle{
            width:40px;
            height:40px;
            border:1px solid var(--border);
            border-radius:50%;
            background:var(--card);
            color:var(--text);
            display:inline-flex;
            align-items:center;
            justify-content:center;
        }

        /* NAVBAR */

        .navbar{

            height:74px;

            background:white;

            border-bottom:1px solid var(--border);

            padding:0 28px;

            position:sticky;
            top:0;
            z-index:999;

            box-shadow:
                0 4px 18px rgba(15,23,42,.04);
        }

        .navbar-brand{

            font-size:1.3rem;
            font-weight:800;
            line-height:1.2;
            letter-spacing:-.02em;
            color:var(--text) !important;
            white-space:nowrap;
            display:inline-flex;
            align-items:center;
            gap:.45rem;
            margin-right:auto;
        }

        .navbar-brand i{

            color:var(--primary);
            font-size:1.2rem;
        }

        .top-date{

            color:var(--muted);

            font-weight:600;
        }

        /* USER CHIP */

        .user-chip{

            background:#f8fafc;

            border:1px solid var(--border);

            border-radius:50px;

            padding:.45rem .9rem .45rem .45rem;

            display:flex;
            align-items:center;
            gap:.7rem;
        }

        .user-avatar{

            width:40px;
            height:40px;

            border-radius:50%;

            background:
                linear-gradient(
                    135deg,
                    #14b8a6,
                    #2dd4bf
                );

            display:flex;
            align-items:center;
            justify-content:center;

            color:white;

            font-weight:700;
        }

        /* SIDEBAR */

        .sidebar{

            width:290px;
            height:100vh;
            background:white;
            border-right:1px solid var(--border);
            padding:20px 18px 14px;
            position:fixed;
            left:0;
            top:0;
            overflow:hidden;
        }

        .nav-section{

            color:#64748b;

            font-size:12px;
            font-weight:800;
            letter-spacing:.08em;
            text-transform:uppercase;
            margin:18px 8px 12px;
            padding:4px 0;
            display:block;
            line-height:1.3;
            white-space:nowrap;
            width:100%;
        }

        .sidebar .nav-link{

            color:#334155;

            border-radius:14px;

            padding:12px 14px;

            display:flex;
            align-items:center;
            gap:12px;

            margin-bottom:6px;

            transition:.2s ease;

            font-weight:600;
            font-size:0.92rem;
            line-height:1.2;
        }

        .sidebar .nav-link i{

            font-size:1.05rem;
        }

        .sidebar .nav-link:hover{

            background:#f0fdfa;

            color:var(--primary-dark);

            transform:translateX(2px);
        }

        .sidebar .nav-link.active{

            background:#dff7f2;
            color:#0f766e;
        }

        /* MAIN */

        .main-content{

            flex:1;

            margin-left:280px;
            min-height:100vh;
            padding:2rem;
            overflow-y:auto;
        }

        /* PAGE TITLE */

        .page-title{

            font-size:2rem;

            font-weight:800;

            margin-bottom:.25rem;
        }

        .page-subtitle{

            color:var(--muted);

            font-size:1rem;
        }

        /* BUTTON */

        .btn-primary{

            background:
                linear-gradient(
                    135deg,
                    #14b8a6,
                    #2dd4bf
                );

            border:none;

            border-radius:16px;

            padding:.9rem 1.3rem;

            font-weight:700;

            box-shadow:
                0 12px 24px rgba(20,184,166,.18);
        }

        .btn-primary:hover{

            opacity:.92;
        }

        /* CARDS */

        .card{

            background:var(--card);

            border:1px solid var(--border);

            border-radius:28px;

            box-shadow:var(--shadow);

            overflow:hidden;
        }

        .card-header{

            background:white !important;

            border-bottom:1px solid var(--border);

            padding:1.2rem 1.5rem;

            font-size:1.1rem;

            font-weight:700;

            color:var(--text);
        }

        /* STATS */

        .stat-card{

            padding:1.8rem;

            position:relative;

            min-height:160px;
        }

        .stat-label{

            color:var(--muted);

            font-size:1rem;

            font-weight:600;
        }

        .stat-value{

            font-size:3.2rem;

            font-weight:800;

            margin-top:.5rem;
        }

        .stat-icon{

            position:absolute;

            right:24px;
            top:50%;

            transform:translateY(-50%);

            font-size:3rem;

            opacity:.9;
        }

        /* TABLE */

        .table{

            color:#334155;

            margin-bottom:0;
        }

        .table thead th{

            color:#64748b;

            font-size:.82rem;

            text-transform:uppercase;

            border-color:var(--border);

            letter-spacing:.06em;
        }

        .table td{

            border-color:var(--border);

            vertical-align:middle;
        }

        .table tbody tr:hover{

            background:#f8fafc;
        }

        /* SEARCH */

        .search-box{

            background:white;

            border:1px solid var(--border);

            border-radius:16px;

            overflow:hidden;
        }

        .search-box .form-control{

            border:none;

            box-shadow:none;
        }

        /* CHART CANVAS */

        canvas{

            max-height:360px !important;
        }

        /* MOBILE */

        @media(max-width:768px){

            .sidebar{
                display:none !important;
            }

            .main-content{
                padding:1rem;
            }

            .navbar{
                padding:0 14px;
            }

            .page-title{
                font-size:1.5rem;
            }

        }

    </style>

    <script>
        if (localStorage.getItem('rehabplus-theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>

</head>

<body>

<!-- NAVBAR -->

<nav class="navbar d-flex justify-content-between align-items-center">

    <a class="navbar-brand d-flex align-items-center gap-2"
       href="<?= site_url('dashboard') ?>">

        <i class="bi bi-heart-pulse-fill"></i>

        RehabPlus

    </a>

    <div class="d-flex align-items-center gap-3">

        <span class="top-date d-none d-md-inline">

            <?= date('F j, Y') ?>

        </span>

        <button type="button" class="theme-toggle" id="themeToggle"
                title="Toggle dark mode" aria-label="Toggle dark mode">
            <i class="bi bi-moon-fill"></i>
        </button>

        <div class="user-chip">

            <div class="user-avatar">

                <?= strtoupper(substr(session()->get('user_name') ?? 'U',0,1)) ?>

            </div>

            <div>

                <div class="fw-bold">

                    <?= esc(session()->get('user_name') ?? '') ?>

                </div>

                <small class="text-muted">

                    <?= ucfirst(session()->get('user_role') ?? '') ?>

                </small>

            </div>

        </div>

        <a href="<?= site_url('logout') ?>"
           class="btn btn-outline-secondary rounded-pill px-3">

            <i class="bi bi-box-arrow-right me-1"></i>

            Logout

        </a>

    </div>

</nav>

<div class="d-flex">

    <!-- SIDEBAR -->

    <nav class="sidebar d-none d-md-flex flex-column">

        <div class="nav-section">

            Overview

        </div>

        <ul class="nav flex-column">

            <li class="nav-item">

                <a class="nav-link <?= uri_string() === '' || uri_string() === 'dashboard' ? 'active' : '' ?>"
                   href="<?= site_url('dashboard') ?>">

                    <i class="bi bi-speedometer2"></i>

                    Dashboard

                </a>

            </li>

            <li class="nav-item">

                <a class="nav-link <?= in_array(uri_string(), ['analytics', 'reports', 'reports-analytics'], true) ? 'active' : '' ?>"
                   href="<?= site_url('reports-analytics') ?>">

                    <i class="bi bi-graph-up-arrow"></i>

                    Reports & Analytics

                </a>

            </li>

        </ul>

        <div class="nav-section mt-4">

            Management

        </div>

        <ul class="nav flex-column">

            <li class="nav-item">

                <a class="nav-link <?= str_starts_with(uri_string(),'patients') ? 'active' : '' ?>"
                   href="<?= site_url('patients') ?>">

                    <i class="bi bi-people-fill"></i>

                    Patients

                </a>

            </li>

            <li class="nav-item">

                <a class="nav-link"
                   href="<?= site_url('appointments') ?>">

                    <i class="bi bi-calendar-check"></i>

                    Appointments

                </a>

            </li>

            <li class="nav-item">

                <a class="nav-link <?= uri_string() === 'billing' ? 'active' : '' ?>"
                   href="<?= site_url('billing') ?>">

                    <i class="bi bi-credit-card"></i>

                    Billing and Payment Records

                </a>

            </li>

            <li class="nav-item">

                <a class="nav-link <?= uri_string() === 'inventory' ? 'active' : '' ?>"
                   href="<?= site_url('inventory') ?>">

                    <i class="bi bi-box-seam"></i>

                    Inventory and Supplies

                </a>

            </li>

            <li class="nav-item">

                <a class="nav-link <?= uri_string() === 'schedule' ? 'active' : '' ?>"
                   href="<?= site_url('schedule') ?>">

                    <i class="bi bi-calendar-week"></i>

                    Staff Schedule

                </a>

            </li>

            <li class="nav-item">

                <a class="nav-link <?= uri_string() === 'notes' ? 'active' : '' ?>"
                   href="<?= site_url('notes') ?>">

                    <i class="bi bi-journal-text"></i>

                    Therapy Notes & Care Plans

                </a>

            </li>

            <li class="nav-item">

                <a class="nav-link <?= uri_string() === 'assessments' ? 'active' : '' ?>"
                   href="<?= site_url('assessments') ?>">

                    <i class="bi bi-clipboard2-pulse"></i>

                    Assessments & Goals

                </a>

            </li>

        </ul>

        <?php if(session()->get('user_role') === 'superadmin'): ?>

        <div class="nav-section mt-4">

            Admin

        </div>

        <ul class="nav flex-column">

            <li class="nav-item">

                <a class="nav-link <?= str_starts_with(uri_string(),'users') ? 'active' : '' ?>"
                   href="<?= site_url('users') ?>">

                    <i class="bi bi-person-gear"></i>

                    Users

                </a>

            </li>


        </ul>

        <?php endif ?>

    </nav>

    <!-- MAIN -->

    <main class="main-content">