<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تسجيل الدخول — لوحة التحكم</title>

    {{-- Bootstrap 5 RTL --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">

    {{-- Font Awesome 6 --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    {{-- Google Fonts: Tajawal (Arabic) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">

    <style>
        /* ── Base ─────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Tajawal', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            background: #0f0c29;
        }

        /* ── Animated Background ──────────────────────────── */
        .bg-scene {
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            z-index: 0;
        }

        .bg-dots {
            position: absolute;
            inset: 0;
            background-image: radial-gradient(circle, rgba(255,255,255,0.07) 1px, transparent 1px);
            background-size: 38px 38px;
        }

        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(90px);
            opacity: 0.30;
            animation: floatBlob 9s ease-in-out infinite;
        }

        .blob-1 {
            width: 520px; height: 520px;
            background: radial-gradient(circle, #4f46e5, #7c3aed);
            top: -180px; left: -180px;
            animation-delay: 0s;
        }

        .blob-2 {
            width: 420px; height: 420px;
            background: radial-gradient(circle, #06b6d4, #3b82f6);
            bottom: -130px; right: -110px;
            animation-delay: -3.5s;
        }

        .blob-3 {
            width: 280px; height: 280px;
            background: radial-gradient(circle, #a855f7, #ec4899);
            top: 45%; left: 55%;
            animation-delay: -6s;
        }

        @keyframes floatBlob {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33%       { transform: translate(28px, -38px) scale(1.06); }
            66%       { transform: translate(-18px, 22px) scale(0.96); }
        }

        /* ── Card ─────────────────────────────────────────── */
        .login-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 440px;
            padding: 20px;
            animation: cardIn 0.65s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(32px) scale(0.96); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .login-card {
            background: rgba(255, 255, 255, 0.055);
            backdrop-filter: blur(28px);
            -webkit-backdrop-filter: blur(28px);
            border: 1px solid rgba(255, 255, 255, 0.11);
            border-radius: 24px;
            padding: 50px 44px 42px;
            box-shadow:
                0 10px 40px rgba(0,0,0,0.45),
                inset 0 1px 0 rgba(255,255,255,0.07);
            position: relative;
            overflow: hidden;
        }

        /* Shimmer edge highlight */
        .login-card::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 24px;
            padding: 1px;
            background: linear-gradient(135deg,
                rgba(255,255,255,0.18) 0%,
                rgba(255,255,255,0.02) 45%,
                rgba(99,102,241,0.30) 100%);
            -webkit-mask:
                linear-gradient(#fff 0 0) content-box,
                linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
        }

        /* ── Brand ────────────────────────────────────────── */
        .brand-section {
            text-align: center;
            margin-bottom: 38px;
        }

        .brand-icon-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            border-radius: 20px;
            font-size: 1.85rem;
            color: #fff;
            margin-bottom: 18px;
            box-shadow: 0 8px 28px rgba(79,70,229,0.50);
            animation: iconGlow 3s ease-in-out infinite;
        }

        @keyframes iconGlow {
            0%, 100% { box-shadow: 0 8px 28px rgba(79,70,229,0.50); }
            50%       { box-shadow: 0 8px 44px rgba(124,58,237,0.70); }
        }

        .brand-title {
            font-size: 1.55rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 7px;
            letter-spacing: 0.2px;
        }

        .brand-subtitle {
            font-size: 0.88rem;
            color: rgba(255,255,255,0.45);
            font-weight: 400;
        }

        /* ── Form Label ───────────────────────────────────── */
        .field-label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: rgba(255,255,255,0.65);
            margin-bottom: 8px;
            letter-spacing: 0.2px;
        }

        /* ── Input ────────────────────────────────────────── */
        .field-wrap {
            position: relative;
            margin-bottom: 20px;
        }

        .field-input {
            width: 100%;
            height: 52px;
            background: rgba(255,255,255,0.07);
            border: 1.5px solid rgba(255,255,255,0.11);
            border-radius: 13px;
            padding: 0 48px 0 16px;
            color: #fff;
            font-size: 0.95rem;
            font-family: 'Tajawal', sans-serif;
            font-weight: 500;
            transition: all 0.22s ease;
            outline: none;
        }

        .field-input::placeholder {
            color: rgba(255,255,255,0.22);
            font-size: 0.875rem;
            font-weight: 400;
        }

        .field-input:focus {
            background: rgba(255,255,255,0.10);
            border-color: rgba(99,102,241,0.85);
            box-shadow: 0 0 0 4px rgba(79,70,229,0.18);
        }

        .field-input.has-error {
            border-color: rgba(239,68,68,0.75);
            box-shadow: 0 0 0 4px rgba(239,68,68,0.14);
        }

        .field-icon {
            position: absolute;
            top: 50%;
            right: 16px;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.30);
            font-size: 0.875rem;
            pointer-events: none;
            transition: color 0.22s;
        }

        .field-input:focus ~ .field-icon { color: #818cf8; }

        /* password has toggle button on left */
        .field-input.with-toggle { padding-left: 44px; }

        .pw-toggle {
            position: absolute;
            top: 50%;
            left: 14px;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: rgba(255,255,255,0.30);
            font-size: 0.875rem;
            padding: 4px 5px;
            transition: color 0.2s;
        }

        .pw-toggle:hover { color: rgba(255,255,255,0.65); }

        /* ── Error Text ───────────────────────────────────── */
        .error-text {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: -12px;
            margin-bottom: 16px;
            font-size: 0.775rem;
            color: #fca5a5;
            animation: errIn 0.25s ease both;
        }

        @keyframes errIn {
            from { opacity: 0; transform: translateY(-5px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Submit Button ────────────────────────────────── */
        .btn-submit {
            width: 100%;
            height: 52px;
            border: none;
            border-radius: 13px;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: #fff;
            font-size: 1.02rem;
            font-weight: 700;
            font-family: 'Tajawal', sans-serif;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            letter-spacing: 0.4px;
            position: relative;
            overflow: hidden;
            transition: transform 0.22s ease, box-shadow 0.22s ease;
            box-shadow: 0 5px 22px rgba(79,70,229,0.48);
            margin-top: 10px;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, #6366f1, #a855f7);
            opacity: 0;
            transition: opacity 0.22s;
        }

        .btn-submit:hover::before { opacity: 1; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 32px rgba(79,70,229,0.55); }
        .btn-submit:active { transform: translateY(0); box-shadow: 0 3px 12px rgba(79,70,229,0.40); }

        .btn-submit span { position: relative; z-index: 1; }
        .btn-submit i    { position: relative; z-index: 1; }

        /* ── Bottom decoration ────────────────────────────── */
        .card-footer-note {
            text-align: center;
            margin-top: 30px;
            padding-top: 22px;
            border-top: 1px solid rgba(255,255,255,0.07);
        }

        .card-footer-note p {
            font-size: 0.775rem;
            color: rgba(255,255,255,0.28);
        }

        /* ── Shake on error ───────────────────────────────── */
        .shake {
            animation: shake 0.42s cubic-bezier(.36,.07,.19,.97) both;
        }

        @keyframes shake {
            10%, 90%  { transform: translateX(-4px); }
            20%, 80%  { transform: translateX(5px); }
            30%, 50%, 70% { transform: translateX(-6px); }
            40%, 60%  { transform: translateX(6px); }
        }

        /* ── Responsive ───────────────────────────────────── */
        @media (max-width: 480px) {
            .login-card { padding: 38px 26px 34px; border-radius: 20px; }
            .brand-icon-wrap { width: 62px; height: 62px; font-size: 1.6rem; }
            .brand-title { font-size: 1.3rem; }
        }
    </style>
</head>
<body>

{{-- Animated Background --}}
<div class="bg-scene">
    <div class="bg-dots"></div>
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>
</div>

{{-- Login Card --}}
<div class="login-wrapper">
    <div class="login-card" id="loginCard">

        {{-- Brand --}}
        <div class="brand-section">
            <div class="brand-icon-wrap">
                <i class="fas fa-shield-halved"></i>
            </div>
            <h1 class="brand-title">لوحة التحكم</h1>
            <p class="brand-subtitle">أدخل بياناتك للوصول إلى النظام</p>
        </div>

        {{-- Form --}}
        <form action="{{ route('admin.login') }}" method="POST" novalidate>
            @csrf

            {{-- Username --}}
            <div>
                <label class="field-label" for="username">اسم المستخدم</label>
                <div class="field-wrap">
                    <input type="text"
                           id="username"
                           name="username"
                           class="field-input {{ $errors->has('username') ? 'has-error' : '' }}"
                           placeholder="أدخل اسم المستخدم"
                           value="{{ old('username') }}"
                           autocomplete="username"
                           autofocus>
                    <i class="fas fa-user field-icon"></i>
                </div>
                @error('username')
                    <div class="error-text">
                        <i class="fas fa-circle-exclamation"></i>
                        {{ $message }}
                    </div>
                @enderror
            </div>

            {{-- Password --}}
            <div>
                <label class="field-label" for="password">كلمة المرور</label>
                <div class="field-wrap">
                    <input type="password"
                           id="password"
                           name="password"
                           class="field-input with-toggle {{ $errors->has('password') ? 'has-error' : '' }}"
                           placeholder="أدخل كلمة المرور"
                           autocomplete="current-password">
                    <i class="fas fa-lock field-icon"></i>
                    <button type="button" class="pw-toggle" id="pwToggle" title="إظهار / إخفاء كلمة المرور">
                        <i class="fas fa-eye" id="pwToggleIcon"></i>
                    </button>
                </div>
                @error('password')
                    <div class="error-text">
                        <i class="fas fa-circle-exclamation"></i>
                        {{ $message }}
                    </div>
                @enderror
            </div>

            {{-- Submit --}}
            <button type="submit" class="btn-submit">
                <i class="fas fa-right-to-bracket"></i>
                <span>تسجيل الدخول</span>
            </button>

        </form>

        {{-- Footer --}}
        <div class="card-footer-note">
            <p>&copy; {{ date('Y') }} — جميع الحقوق محفوظة</p>
        </div>

    </div>
</div>

<script>
    // Toggle password visibility
    const pwInput  = document.getElementById('password');
    const pwToggle = document.getElementById('pwToggle');
    const pwIcon   = document.getElementById('pwToggleIcon');

    pwToggle.addEventListener('click', function () {
        const show  = pwInput.type === 'password';
        pwInput.type = show ? 'text' : 'password';
        pwIcon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
    });

    // Shake card when there are validation errors
    @if($errors->any())
    (function () {
        const card = document.getElementById('loginCard');
        card.classList.add('shake');
        card.addEventListener('animationend', function () {
            card.classList.remove('shake');
        }, { once: true });
    })();
    @endif
</script>

</body>
</html>
