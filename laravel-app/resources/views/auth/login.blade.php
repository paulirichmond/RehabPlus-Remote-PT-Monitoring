@extends('layouts.app')

@section('title', $patientLogin ? 'Patient Portal Login' : 'RehabPlus Login')

@section('content')
    <div style="height: 100vh; display:flex; align-items:center; justify-content:center; padding:18px; background: radial-gradient(circle at top left,#164e6322,transparent 30%), radial-gradient(circle at bottom right,#0f766e22,transparent 30%), linear-gradient(135deg,#020617 0%,#071326 100%);">
        <div style="width:100%; max-width:1650px; height:calc(100vh - 36px); background:#ffffff; border-radius:32px; overflow:hidden; display:flex; box-shadow: 0 35px 100px rgba(0,0,0,.35);">
            <div style="width:52%; position:relative; overflow:hidden; display:flex; align-items:flex-end; background: linear-gradient(rgba(2,6,23,.45), rgba(2,6,23,.78)), url('http://127.0.0.1:8000/assets/images/pt.jpg'); background-size:cover; background-position:center center; background-repeat:no-repeat;">
                <div style="position:absolute; width:700px; height:700px; border-radius:50%; background: radial-gradient(circle, rgba(34,211,238,.22), transparent 70%); top:-220px; right:-120px;"></div>
                <div style="position:relative; z-index:2; padding:48px 52px; width:100%; color:white;">
                    <div style="width:82px; height:82px; border-radius:22px; background:rgba(255,255,255,.10); border:1px solid rgba(255,255,255,.15); backdrop-filter:blur(10px); display:flex; align-items:center; justify-content:center; margin-bottom:28px;">
                        <i class="bi bi-heart-pulse-fill" style="font-size:2.8rem; color:#22d3ee;"></i>
                    </div>
                    <div style="font-size:5rem; font-weight:900; line-height:.95; margin-bottom:20px; letter-spacing:-3px;">
                        Rehab<span style="color:#22d3ee;">Plus</span>
                    </div>
                    <div style="max-width:580px; font-size:1.08rem; line-height:1.7; color:rgba(255,255,255,.92); margin-bottom:34px;">
                        Comprehensive rehabilitation tracking for patient outcomes, appointment flow, and recovery analytics.
                    </div>
                    <ul style="list-style:none; padding:0; margin:0 0 40px 0;">
                        <li style="display:flex; align-items:center; gap:16px; margin-bottom:16px; font-size:1rem; font-weight:600;">
                            <span style="width:48px; height:48px; border-radius:50%; background:#22d3ee; color:#001018; display:flex; align-items:center; justify-content:center; box-shadow:0 10px 30px rgba(34,211,238,.25);"><i class="bi bi-calendar-check"></i></span>
                            Appointment and session tracking
                        </li>
                        <li style="display:flex; align-items:center; gap:16px; margin-bottom:16px; font-size:1rem; font-weight:600;">
                            <span style="width:48px; height:48px; border-radius:50%; background:#22d3ee; color:#001018; display:flex; align-items:center; justify-content:center; box-shadow:0 10px 30px rgba(34,211,238,.25);"><i class="bi bi-graph-up-arrow"></i></span>
                            Recovery analytics and progress monitoring
                        </li>
                        <li style="display:flex; align-items:center; gap:16px; font-size:1rem; font-weight:600;">
                            <span style="width:48px; height:48px; border-radius:50%; background:#22d3ee; color:#001018; display:flex; align-items:center; justify-content:center; box-shadow:0 10px 30px rgba(34,211,238,.25);"><i class="bi bi-shield-check"></i></span>
                            Secure patient care management
                        </li>
                    </ul>
                    <div style="display:inline-flex; align-items:center; gap:12px; padding:14px 24px; border-radius:50px; background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.18); backdrop-filter:blur(14px); font-weight:700; font-size:.90rem; letter-spacing:.03em;">
                        <i class="bi bi-lock-fill" style="color:#22d3ee;"></i> Secure Clinic Access
                    </div>
                </div>
            </div>

            <div style="flex:1; background:#f8fafc; display:flex; align-items:center; justify-content:center; padding:40px 60px; overflow:hidden;">
                <div style="width:100%; max-width:560px;">
                    <div style="width:110px; height:110px; border-radius:50%; background:#ecfeff; margin:0 auto 30px; display:flex; align-items:center; justify-content:center;">
                        <i class="bi bi-heart-pulse-fill" style="font-size:3.4rem; color:#06b6d4;"></i>
                    </div>
                    <div style="font-size:4.3rem; line-height:.95; font-weight:900; text-align:center; color:#0f172a; margin-bottom:14px; letter-spacing:-3px;">
                        {{ $patientLogin ? 'Patient Login' : 'Login' }}
                    </div>
                    <div style="text-align:center; font-size:1.08rem; color:#64748b; margin-bottom:38px;">
                        {{ $patientLogin ? 'Access your rehabilitation portal.' : 'Sign in to continue to your dashboard.' }}
                    </div>

                    @if(session('error'))
                        <div style="color:#b91c1c; margin-bottom:12px; background:#fee2e2; border:1px solid #fecaca; padding:10px 12px; border-radius:10px;">{{ session('error') }}</div>
                    @endif

                    <form method="POST" action="{{ $patientLogin ? route('patient.login.submit') : route('login.submit') }}" style="display:grid; gap: 22px;">
                        @csrf
                        <div>
                            <label style="display:block; font-size:1rem; font-weight:700; color:#0f172a; margin-bottom:12px;">Email</label>
                            <div style="height:72px; border-radius:22px; overflow:hidden; border:2px solid #67e8f9; background:white; transition:.25s; display:flex; align-items:center;">
                                <span style="padding:0 24px; color:#94a3b8; font-size:1.1rem; display:flex; align-items:center;"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="email" value="{{ old('email') }}" required style="border:none; box-shadow:none; font-size:1rem; font-weight:500; color:#0f172a; width:100%; padding-right:16px;">
                            </div>
                        </div>
                        <div>
                            <label style="display:block; font-size:1rem; font-weight:700; color:#0f172a; margin-bottom:12px;">Password</label>
                            <div style="height:72px; border-radius:22px; overflow:hidden; border:2px solid #67e8f9; background:white; transition:.25s; display:flex; align-items:center;">
                                <span style="padding:0 24px; color:#94a3b8; font-size:1.1rem; display:flex; align-items:center;"><i class="bi bi-lock"></i></span>
                                <input type="password" name="password" required style="border:none; box-shadow:none; font-size:1rem; font-weight:500; color:#0f172a; width:100%; padding-right:16px;">
                            </div>
                        </div>
                        <button type="submit" style="width:100%; height:72px; border:none; border-radius:22px; background:linear-gradient(90deg, #0f766e, #14b8a6, #2dd4bf); color:white; font-size:1.18rem; font-weight:800; box-shadow:0 16px 38px rgba(20,184,166,.22); cursor:pointer;">
                            {{ $patientLogin ? 'Patient Sign In' : 'Sign In' }}
                        </button>
                    </form>

                    <div style="border-top:1px solid #e2e8f0; margin:28px 0 18px;"></div>

                    <div style="padding:12px; border-radius:10px; background:#f8fafc; border:1px solid #e2e8f0; font-size:13px; line-height:1.6;">
                        <strong>Demo access:</strong>
                        @if($patientLogin)
                            patient@rehabplus.com / admin123
                        @else
                            admin@rehabplus.com / admin123
                        @endif
                    </div>

                    <p style="margin-top: 18px; font-size: 14px; text-align: center;">
                        @if($patientLogin)
                            <a href="{{ route('login.page') }}">Admin login</a>
                        @else
                            <a href="{{ route('patient.login') }}">Patient login</a>
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
