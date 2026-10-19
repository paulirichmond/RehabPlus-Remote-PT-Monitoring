<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RehabPlus Login</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    @vite(['resources/css/rehabplus-login.css', 'resources/js/rehabplus-login.js'])
</head>
<body>
    <main class="login-wrapper">
        <section class="left-panel" aria-labelledby="brand-title">
            <div class="left-content">
                <div class="brand-icon" aria-hidden="true">
                    <i class="bi bi-heart-pulse-fill"></i>
                </div>

                <h1 class="brand-title" id="brand-title">
                    Rehab<span>Plus</span>
                </h1>

                <p class="brand-desc">
                    Smart rehabilitation management system for physical
                    therapists and healthcare professionals.
                </p>

                <ul class="feature-list">
                    <li>
                        <div class="feature-icon" aria-hidden="true">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                        Patient recovery analytics
                    </li>
                    <li>
                        <div class="feature-icon" aria-hidden="true">
                            <i class="bi bi-clipboard2-pulse"></i>
                        </div>
                        Exercise compliance monitoring
                    </li>
                    <li>
                        <div class="feature-icon" aria-hidden="true">
                            <i class="bi bi-heart-pulse"></i>
                        </div>
                        Pain level &amp; recovery tracking
                    </li>
                </ul>

                <div class="secure-badge">
                    <i class="bi bi-shield-lock-fill"></i>
                    SECURE PHYSICAL THERAPY PORTAL
                </div>
            </div>
        </section>

        <section class="right-panel" aria-labelledby="login-title">
            <div class="login-box">
                <div class="top-logo" aria-hidden="true">
                    <i class="bi bi-heart-pulse-fill"></i>
                </div>

                <h1 class="login-title" id="login-title">
                    Welcome back
                </h1>

                <p class="login-subtitle">
                    Sign in to your account to continue
                </p>

                @if (session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger">
                        {{ session('error') }}
                    </div>
                @elseif ($errors->any())
                    <div class="alert alert-danger">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form action="{{ route('login.submit') }}" method="post">
                    @csrf

                    <div>
                        <label class="form-label" for="email">
                            Email Address
                        </label>

                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">
                                <i class="bi bi-envelope"></i>
                            </span>

                            <input type="email"
                                   id="email"
                                   name="email"
                                   class="form-control"
                                   placeholder="Enter your email"
                                   value="{{ old('email') }}"
                                   required>
                        </div>
                    </div>

                    <div>
                        <label class="form-label" for="passwordInput">
                            Password
                        </label>

                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">
                                <i class="bi bi-lock"></i>
                            </span>

                            <input type="password"
                                   id="passwordInput"
                                   name="password"
                                   class="form-control"
                                   placeholder="••••••••"
                                   required>

                            <span class="input-group-text"
                                  id="passwordToggle"
                                  style="cursor: pointer;"
                                  aria-label="Show password"
                                  role="button"
                                  tabindex="0">
                                <i class="bi bi-eye" id="eyeIcon" aria-hidden="true"></i>
                            </span>
                        </div>
                    </div>

                    <div class="remember-row">
                        <div class="form-check">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="remember">

                            <label class="form-check-label" for="remember">
                                Remember me
                            </label>
                        </div>

                        <a href="{{ route('password.request') }}">
                            Forgot password?
                        </a>
                    </div>

                    <button type="submit" class="btn-login">
                        <i class="bi bi-box-arrow-in-right me-2"></i>
                        Sign In
                    </button>
                </form>

                <div class="footer-line"></div>

                <p class="footer-note">
                    <i class="bi bi-shield-check me-1"></i>
                    Secured access — authorised personnel only
                </p>
            </div>
        </section>
    </main>
</body>
</html>
