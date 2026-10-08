<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>RehabPlus <?= !empty($patientLogin) ? 'Patient' : 'Admin' ?> Login</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
          rel="stylesheet">

    <style>

        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
            font-family:'Inter',sans-serif;
        }

        body{

            height:100vh;

            overflow:hidden;

            background:
                radial-gradient(circle at top left,#164e6322,transparent 30%),
                radial-gradient(circle at bottom right,#0f766e22,transparent 30%),
                linear-gradient(135deg,#020617 0%,#071326 100%);

            display:flex;
            align-items:center;
            justify-content:center;

            padding:18px;
        }

        /* MAIN WRAPPER */

        .login-wrapper{

            width:100%;

            max-width:1650px;

            height:calc(100vh - 36px);

            background:#ffffff;

            border-radius:32px;

            overflow:hidden;

            display:flex;

            box-shadow:
                0 35px 100px rgba(0,0,0,.35);
        }

        /* LEFT PANEL */

        .left-panel{

            width:52%;

            position:relative;

            overflow:hidden;

            display:flex;
            align-items:flex-end;

            background:
                linear-gradient(
                    rgba(2,6,23,.45),
                    rgba(2,6,23,.78)
                ),
                url('<?= base_url('assets/images/pt.jpg') ?>');

            background-size:cover;

            background-position:center center;

            background-repeat:no-repeat;
        }

        /* GLOW */

        .left-panel::before{

            content:'';

            position:absolute;

            width:700px;
            height:700px;

            border-radius:50%;

            background:
                radial-gradient(
                    circle,
                    rgba(34,211,238,.22),
                    transparent 70%
                );

            top:-220px;
            right:-120px;
        }

        .left-panel::after{

            content:'';

            position:absolute;

            inset:0;

            background:
                linear-gradient(
                    180deg,
                    rgba(0,0,0,.08),
                    rgba(0,0,0,.52)
                );
        }

        /* LEFT CONTENT */

        .left-content{

            position:relative;

            z-index:2;

            padding:48px 52px;

            width:100%;

            color:white;
        }

        /* ICON */

        .brand-icon{

            width:82px;
            height:82px;

            border-radius:22px;

            background:rgba(255,255,255,.10);

            border:1px solid rgba(255,255,255,.15);

            backdrop-filter:blur(10px);

            display:flex;
            align-items:center;
            justify-content:center;

            margin-bottom:28px;
        }

        .brand-icon i{

            font-size:2.8rem;

            color:#22d3ee;
        }

        /* TITLE */

        .brand-title{

            font-size:5rem;

            font-weight:900;

            line-height:.95;

            margin-bottom:20px;

            letter-spacing:-3px;
        }

        .brand-title span{

            color:#22d3ee;
        }

        /* DESCRIPTION */

        .brand-desc{

            max-width:580px;

            font-size:1.08rem;

            line-height:1.7;

            color:rgba(255,255,255,.92);

            margin-bottom:34px;
        }

        /* FEATURES */

        .feature-list{

            list-style:none;

            padding:0;

            margin:0 0 40px 0;
        }

        .feature-list li{

            display:flex;
            align-items:center;

            gap:16px;

            margin-bottom:16px;

            font-size:1rem;

            font-weight:600;
        }

        .feature-icon{

            width:48px;
            height:48px;

            border-radius:50%;

            background:#22d3ee;

            color:#001018;

            display:flex;
            align-items:center;
            justify-content:center;

            font-size:1.05rem;

            flex-shrink:0;

            box-shadow:
                0 10px 30px rgba(34,211,238,.25);
        }

        /* BADGE */

        .secure-badge{

            display:inline-flex;
            align-items:center;

            gap:12px;

            padding:14px 24px;

            border-radius:50px;

            background:rgba(255,255,255,.08);

            border:1px solid rgba(255,255,255,.18);

            backdrop-filter:blur(14px);

            font-weight:700;

            font-size:.90rem;

            letter-spacing:.03em;
        }

        /* RIGHT PANEL */

        .right-panel{

            flex:1;

            background:#f8fafc;

            display:flex;
            align-items:center;
            justify-content:center;

            padding:40px 60px;

            overflow:hidden;
        }

        /* LOGIN BOX */

        .login-box{

            width:100%;

            max-width:560px;
        }

        /* LOGO */

        .top-logo{

            width:110px;
            height:110px;

            border-radius:50%;

            background:#ecfeff;

            margin:0 auto 30px;

            display:flex;
            align-items:center;
            justify-content:center;
        }

        .top-logo i{

            font-size:3.4rem;

            color:#06b6d4;
        }

        /* LOGIN TITLE */

        .login-title{

            font-size:4.3rem;

            line-height:.95;

            font-weight:900;

            text-align:center;

            color:#0f172a;

            margin-bottom:14px;

            letter-spacing:-3px;
        }

        .login-subtitle{

            text-align:center;

            font-size:1.08rem;

            color:#64748b;

            margin-bottom:38px;
        }

        /* LABEL */

        .form-label{

            font-size:1rem;

            font-weight:700;

            color:#0f172a;

            margin-bottom:12px;
        }

        /* INPUT GROUP */

        .input-group{

            height:72px;

            border-radius:22px;

            overflow:hidden;

            border:2px solid #67e8f9;

            background:white;

            margin-bottom:26px;

            transition:.25s;
        }

        .input-group:focus-within{

            transform:translateY(-2px);

            box-shadow:
                0 14px 34px rgba(34,211,238,.18);
        }

        .input-group-text{

            border:none !important;

            background:white !important;

            padding:0 24px;

            color:#94a3b8;

            font-size:1.1rem;
        }

        .form-control{

            border:none !important;

            box-shadow:none !important;

            font-size:1rem;

            font-weight:500;

            color:#0f172a;
        }

        .form-control::placeholder{

            color:#94a3b8;
        }

        /* REMEMBER */

        .remember-row{

            display:flex;
            justify-content:space-between;
            align-items:center;

            margin-top:-4px;
            margin-bottom:28px;
        }

        .remember-row label{

            color:#64748b;

            font-size:.96rem;
        }

        .remember-row a{

            color:#14b8a6;

            text-decoration:none;

            font-weight:700;
        }

        /* BUTTON */

        .btn-login{

            width:100%;

            height:72px;

            border:none;

            border-radius:22px;

            background:
                linear-gradient(
                    90deg,
                    #0f766e,
                    #14b8a6,
                    #2dd4bf
                );

            color:white;

            font-size:1.18rem;

            font-weight:800;

            transition:.25s;

            box-shadow:
                0 16px 38px rgba(20,184,166,.22);
        }

        .btn-login:hover{

            transform:translateY(-3px);

            box-shadow:
                0 22px 48px rgba(20,184,166,.32);
        }

        /* FOOTER */

        .footer-line{

            border-top:1px solid #e2e8f0;

            margin:28px 0 18px;
        }

        .footer-note{

            text-align:center;

            color:#94a3b8;

            font-size:.95rem;
        }

        /* ALERT */

        .alert{

            border:none;

            border-radius:18px;

            padding:14px 18px;

            margin-bottom:20px;
        }

        /* MOBILE */

        @media(max-width:992px){

            body{

                overflow:auto;

                padding:0;
            }

            .login-wrapper{

                flex-direction:column;

                height:auto;

                border-radius:0;
            }

            .left-panel{

                width:100%;

                min-height:420px;
            }

            .right-panel{

                width:100%;

                padding:40px 24px;
            }

            .brand-title{
                font-size:3.6rem;
            }

            .login-title{
                font-size:3rem;
            }

            .left-content{
                padding:38px;
            }

        }

    </style>

</head>

<body>

<div class="login-wrapper">

    <!-- LEFT -->

    <div class="left-panel">

        <div class="left-content">

            <div class="brand-icon">

                <i class="bi bi-heart-pulse-fill"></i>

            </div>

            <h1 class="brand-title">

                Rehab<span>Plus</span>

            </h1>

            <p class="brand-desc">

                Smart rehabilitation management system for physical
                therapists and healthcare professionals.

            </p>

            <ul class="feature-list">

                <li>

                    <div class="feature-icon">

                        <i class="bi bi-graph-up-arrow"></i>

                    </div>

                    Patient recovery analytics

                </li>

                <li>

                    <div class="feature-icon">

                        <i class="bi bi-clipboard2-pulse"></i>

                    </div>

                    Exercise compliance monitoring

                </li>

                <li>

                    <div class="feature-icon">

                        <i class="bi bi-heart-pulse"></i>

                    </div>

                    Pain level & recovery tracking

                </li>

            </ul>

            <div class="secure-badge">

                <i class="bi bi-shield-lock-fill"></i>

                SECURE PHYSICAL THERAPY PORTAL

            </div>

        </div>

    </div>

    <!-- RIGHT -->

    <div class="right-panel">

        <div class="login-box">

            <div class="top-logo">

                <i class="bi bi-heart-pulse-fill"></i>

            </div>

            <h1 class="login-title">

                Welcome back

            </h1>

            <p class="login-subtitle">

                Sign in to your account to continue

            </p>

            <?php if (session()->getFlashdata('success')): ?>

                <div class="alert alert-success">

                    <?= esc(session()->getFlashdata('success')) ?>

                </div>

            <?php endif ?>

            <?php if (session()->getFlashdata('error')): ?>

                <div class="alert alert-danger">

                    <?= esc(session()->getFlashdata('error')) ?>

                </div>

            <?php endif ?>

            <form action="<?= site_url(!empty($patientLogin) ? 'patient-login' : 'login') ?>"
                  method="post">

                <?= csrf_field() ?>

                <!-- EMAIL -->

                <div>

                    <label class="form-label">

                        Email Address

                    </label>

                    <div class="input-group">

                        <span class="input-group-text">

                            <i class="bi bi-envelope"></i>

                        </span>

                        <input type="email"
                               name="email"
                               class="form-control"
                               placeholder="Enter your email"
                               value="<?= esc(old('email')) ?>"
                               required>

                    </div>

                </div>

                <!-- PASSWORD -->

                <div>

                    <label class="form-label">

                        Password

                    </label>

                    <div class="input-group">

                        <span class="input-group-text">

                            <i class="bi bi-lock"></i>

                        </span>

                        <input type="password"
                               name="password"
                               id="passwordInput"
                               class="form-control"
                               placeholder="••••••••"
                               required>

                        <span class="input-group-text"
                              onclick="togglePassword()"
                              style="cursor:pointer;">

                            <i class="bi bi-eye"
                               id="eyeIcon"></i>

                        </span>

                    </div>

                </div>

                <!-- REMEMBER -->

                <div class="remember-row">

                    <div class="form-check">

                        <input class="form-check-input"
                               type="checkbox"
                               id="remember">

                        <label class="form-check-label"
                               for="remember">

                            Remember me

                        </label>

                    </div>

                    <a href="#">

                        Forgot password?

                    </a>

                </div>

                <!-- BUTTON -->

                <button type="submit"
                        class="btn-login">

                    <i class="bi bi-box-arrow-in-right me-2"></i>

                    Sign In

                </button>

            </form>

            <div class="footer-line"></div>

            <p class="footer-note">

                <i class="bi bi-shield-check me-1"></i>

                Secured access — authorised personnel only

            </p>

            <p class="text-center mt-3 mb-0 small">
                <?php if (!empty($patientLogin)): ?>
                    Staff member? <a href="<?= site_url('login') ?>">Admin login</a>
                <?php else: ?>
                    Patient? <a href="<?= site_url('patient-login') ?>">Patient portal login</a>
                <?php endif; ?>
            </p>

        </div>

    </div>

</div>

<script>

function togglePassword(){

    const input = document.getElementById('passwordInput');

    const icon = document.getElementById('eyeIcon');

    if(input.type === 'password'){

        input.type = 'text';

        icon.classList.replace('bi-eye','bi-eye-slash');

    }else{

        input.type = 'password';

        icon.classList.replace('bi-eye-slash','bi-eye');

    }

}

</script>

</body>
</html>