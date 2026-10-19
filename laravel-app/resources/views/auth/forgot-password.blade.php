<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RehabPlus Forgot Password</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    @vite(['resources/css/rehabplus-login.css', 'resources/js/rehabplus-login.js'])
</head>
<body>
    {{-- $step: 'email' (ask for the address), 'code' (enter the 6-digit code), 'password' (set a new one) --}}
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

        <section class="right-panel" aria-labelledby="reset-title">
            <div class="login-box">
                <div class="top-logo" aria-hidden="true">
                    <i class="bi {{ $step === 'password' ? 'bi-shield-lock-fill' : 'bi-key-fill' }}"></i>
                </div>

                <h1 class="login-title" id="reset-title">
                    @if ($step === 'email')
                        Forgot password?
                    @elseif ($step === 'code')
                        Check your email
                    @else
                        New password
                    @endif
                </h1>

                <p class="login-subtitle">
                    @if ($step === 'email')
                        Enter your account email and we'll send you a 6-digit code
                    @elseif ($step === 'code')
                        Enter the 6-digit code sent to <strong>{{ $email }}</strong>
                    @else
                        Choose a new password for <strong>{{ $email }}</strong>
                    @endif
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

                @if ($step === 'email')
                    <form action="{{ route('password.email') }}" method="post">
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
                                       autocomplete="email"
                                       required
                                       autofocus>
                            </div>
                        </div>

                        <button type="submit" class="btn-login">
                            <i class="bi bi-send me-2"></i>
                            Send Code
                        </button>
                    </form>
                @elseif ($step === 'code')
                    <form action="{{ route('password.verify.submit') }}" method="post">
                        @csrf

                        <div>
                            <label class="form-label" for="code">
                                Verification Code
                            </label>

                            <div class="input-group">
                                <span class="input-group-text" aria-hidden="true">
                                    <i class="bi bi-shield-check"></i>
                                </span>

                                <input type="text"
                                       id="code"
                                       name="code"
                                       class="form-control"
                                       placeholder="000000"
                                       inputmode="numeric"
                                       pattern="\d{6}"
                                       maxlength="6"
                                       autocomplete="one-time-code"
                                       style="letter-spacing: 0.5em; font-weight: 700;"
                                       required
                                       autofocus>
                            </div>
                        </div>

                        <button type="submit" class="btn-login">
                            <i class="bi bi-check2-circle me-2"></i>
                            Verify Code
                        </button>
                    </form>

                    <form action="{{ route('password.resend') }}" method="post" class="remember-row mt-3 mb-0">
                        @csrf

                        <span class="small text-muted">Didn't get it? Check your spam folder.</span>

                        @if ($resendIn > 0)
                            <span class="small text-muted">Resend in {{ $resendIn }}s</span>
                        @else
                            <button type="submit" class="btn btn-link p-0 fw-semibold text-decoration-none" style="color: #14b8a6;">
                                Resend code
                            </button>
                        @endif
                    </form>
                @else
                    <form action="{{ route('password.update') }}" method="post">
                        @csrf

                        <div>
                            <label class="form-label" for="passwordInput">
                                New Password
                            </label>

                            <div class="input-group">
                                <span class="input-group-text" aria-hidden="true">
                                    <i class="bi bi-lock"></i>
                                </span>

                                <input type="password"
                                       id="passwordInput"
                                       name="password"
                                       class="form-control"
                                       placeholder="At least {{ \App\Services\Firebase\FirebaseAdmin::MIN_PASSWORD }} characters"
                                       minlength="{{ \App\Services\Firebase\FirebaseAdmin::MIN_PASSWORD }}"
                                       autocomplete="new-password"
                                       required
                                       autofocus>

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

                        <div>
                            <label class="form-label" for="password_confirmation">
                                Confirm New Password
                            </label>

                            <div class="input-group">
                                <span class="input-group-text" aria-hidden="true">
                                    <i class="bi bi-lock-fill"></i>
                                </span>

                                <input type="password"
                                       id="password_confirmation"
                                       name="password_confirmation"
                                       class="form-control"
                                       placeholder="Type it again"
                                       autocomplete="new-password"
                                       required>
                            </div>
                        </div>

                        <button type="submit" class="btn-login">
                            <i class="bi bi-check-lg me-2"></i>
                            Change Password
                        </button>
                    </form>
                @endif

                <div class="remember-row justify-content-center mt-3">
                    <a href="{{ route('login.page') }}">
                        <i class="bi bi-arrow-left me-1"></i>
                        Back to sign in
                    </a>
                </div>

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
