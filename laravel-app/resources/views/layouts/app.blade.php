<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'RehabPlus')</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --primary:#14b8a6;
            --primary-dark:#0f766e;
            --primary-light:#ccfbf1;
            --bg:#f4f7fb;
            --sidebar:#ffffff;
            --card:#ffffff;
            --text:#0f172a;
            --muted:#64748b;
            --border:#e2e8f0;
            --shadow:0 12px 35px rgba(15,23,42,.06);
            --sidebar-w:260px;
        }

        * { box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }
        a { color: var(--primary-dark); text-decoration: none; }
        a:hover { text-decoration: none; }
        h1, h2, h3, h4 { margin: 0 0 12px; color: var(--text); }
        p { margin: 0 0 12px; color: var(--text); }
        .navbar {
            height: 74px;
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 0 28px;
            position: sticky;
            top: 0;
            z-index: 999;
            box-shadow: 0 4px 18px rgba(15,23,42,.04);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .navbar-brand {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text) !important;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .navbar-brand i {
            color: var(--primary);
            font-size: 1.7rem;
        }
        .top-date {
            color: var(--muted);
            font-weight: 600;
        }
        .user-chip {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 50px;
            padding: .45rem .9rem .45rem .45rem;
            display: flex;
            align-items: center;
            gap: .7rem;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #14b8a6, #2dd4bf);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
        }
        .theme-toggle {
            width: 40px;
            height: 40px;
            border: 1px solid var(--border);
            border-radius: 50%;
            background: var(--card);
            color: var(--text);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .content-shell {
            display: flex;
            min-height: calc(100vh - 74px);
        }
        .sidebar {
            width: var(--sidebar-w);
            min-height: calc(100vh - 74px);
            background: white;
            border-right: 1px solid var(--border);
            padding: 1.5rem 1rem;
            position: sticky;
            top: 74px;
        }
        .nav-section {
            color: #94a3b8;
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            padding: .6rem .9rem;
        }
        .sidebar .nav-link {
            color: #334155;
            border-radius: 18px;
            padding: .95rem 1rem;
            display: flex;
            align-items: center;
            gap: .9rem;
            margin-bottom: .35rem;
            transition: .2s ease;
            font-weight: 600;
        }
        .sidebar .nav-link:hover {
            background: #f0fdfa;
            color: var(--primary-dark);
            transform: translateX(2px);
        }
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, #14b8a6, #2dd4bf);
            color: white;
            box-shadow: 0 10px 24px rgba(20,184,166,.20);
        }
        .main-content {
            flex: 1;
            padding: 2rem;
        }
        .page-title {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: .25rem;
        }
        .page-subtitle {
            color: var(--muted);
            font-size: 1rem;
        }
        .btn-primary, .btn, button {
            background: linear-gradient(135deg, #14b8a6, #2dd4bf);
            border: none;
            border-radius: 16px;
            padding: .9rem 1.3rem;
            font-weight: 700;
            color: white;
            box-shadow: 0 12px 24px rgba(20,184,166,.18);
            cursor: pointer;
        }
        .btn-secondary {
            background: #e2e8f0;
            color: var(--text);
            box-shadow: none;
        }
        .panel, .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 28px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .card-header {
            background: white !important;
            border-bottom: 1px solid var(--border);
            padding: 1.2rem 1.5rem;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat {
            padding: 1.8rem;
            position: relative;
            min-height: 160px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 28px;
            box-shadow: var(--shadow);
        }
        .stat-label {
            color: var(--muted);
            font-size: 1rem;
            font-weight: 600;
        }
        .stat-value {
            font-size: 3.2rem;
            font-weight: 800;
            margin-top: .5rem;
        }
        .table-wrap {
            overflow: hidden;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: white;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 14px; border-bottom: 1px solid var(--border); text-align: left; }
        th {
            color: #64748b;
            font-size: .82rem;
            text-transform: uppercase;
            border-color: var(--border);
            letter-spacing: .06em;
        }
        .badge {
            display: inline-flex;
            padding: 6px 10px;
            border-radius: 999px;
            background: var(--primary-light);
            color: var(--primary-dark);
            font-size: 12px;
            font-weight: 700;
        }
        .status { background: #dcfce7; color: #166534; border-radius: 999px; padding: 4px 8px; font-size: 12px; font-weight: 700; }
        input, select, textarea {
            width: 100%;
            padding: 10px 12px;
            border-radius: 16px;
            border: 1px solid var(--border);
            background: white;
            color: var(--text);
            box-sizing: border-box;
        }
        textarea { min-height: 110px; resize: vertical; }
        @media (max-width: 800px) {
            .sidebar { display: none !important; }
            .main-content { padding: 1rem; }
            .navbar { padding: 0 14px; }
            .stats { grid-template-columns: 1fr; }
        }
    </style>
    <script>
        if (localStorage.getItem('rehabplus-theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>
</head>
<body>
    <nav class="navbar">
        <a class="navbar-brand" href="{{ route('dashboard') }}">
            <i class="bi bi-heart-pulse-fill"></i>
            RehabPlus
        </a>

        <div style="display:flex; align-items:center; gap:16px;">
            <span class="top-date d-none d-md-inline">{{ date('F j, Y') }}</span>
            <button type="button" class="theme-toggle" title="Toggle dark mode" aria-label="Toggle dark mode">
                <i class="bi bi-moon-fill"></i>
            </button>
            <div class="user-chip">
                <div class="user-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</div>
                <div>
                    <div style="font-weight:700;">{{ auth()->user()->name ?? 'User' }}</div>
                    <small style="color: var(--muted);">{{ ucfirst(auth()->user()->role ?? '') }}</small>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="inline">@csrf<button type="submit" class="btn-secondary" style="border-radius: 50px; padding: .8rem 1rem;">Logout</button></form>
        </div>
    </nav>

    <div class="content-shell">
        <nav class="sidebar">
            <div class="nav-section">Main</div>
            <div class="nav flex-column" style="display:flex; flex-direction:column;">
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a class="nav-link {{ request()->routeIs('patients.*') || request()->is('patients*') ? 'active' : '' }}" href="{{ route('patients.index') }}">
                    <i class="bi bi-people-fill"></i> Patients
                </a>
                <a class="nav-link {{ request()->routeIs('appointments.*') || request()->is('appointments*') ? 'active' : '' }}" href="{{ route('appointments.index') }}">
                    <i class="bi bi-calendar-check"></i> Appointments
                </a>
                <a class="nav-link {{ request()->routeIs('analytics') ? 'active' : '' }}" href="{{ route('analytics') }}">
                    <i class="bi bi-graph-up-arrow"></i> Recovery Analytics
                </a>
            </div>

            @if(auth()->user() && auth()->user()->role === 'superadmin')
                <div class="nav-section" style="margin-top: 1.5rem;">Management</div>
                <div class="nav flex-column" style="display:flex; flex-direction:column;">
                    <a class="nav-link {{ request()->routeIs('patient-statistics') ? 'active' : '' }}" href="{{ route('patient-statistics') }}">
                        <i class="bi bi-activity"></i> Patient Statistics
                    </a>
                    <a class="nav-link {{ request()->routeIs('users.*') || request()->is('users*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                        <i class="bi bi-person-gear"></i> Users
                    </a>
                </div>
            @endif
        </nav>

        <main class="main-content">
            @yield('content')
        </main>
    </div>
</body>
</html>
