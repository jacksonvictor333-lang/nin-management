<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign in | NIN Management System</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
        :root {
            --accent: #16a34a;
            --accent-dark: #15803d;
            --accent-soft: #dcfce7;
            --ink: #0f172a;
            --text: #334155;
            --muted: #94a3b8;
            --line: #e5e7eb;
            --danger: #dc2626;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        html, body { height: 100%; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--ink);
            background: #f8fafc;
        }

        .auth { display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr); min-height: 100vh; }

        /* ================= BRAND PANEL ================= */
        .brand {
            position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: space-between;
            padding: 44px 52px;
            color: #fff;
            background:
                radial-gradient(circle at 85% 10%, rgba(255,255,255,.14), transparent 40%),
                radial-gradient(circle at 0% 100%, rgba(134,239,172,.28), transparent 45%),
                linear-gradient(150deg, #052e16 0%, #14532d 45%, #15803d 100%);
        }

        /* Mapambo ya nyuma: gridi hafifu + duara */
        .brand::before {
            content: ''; position: absolute; inset: 0; pointer-events: none;
            background-image:
                linear-gradient(rgba(255,255,255,.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.05) 1px, transparent 1px);
            background-size: 44px 44px;
            mask-image: radial-gradient(circle at 30% 40%, #000 0%, transparent 70%);
            -webkit-mask-image: radial-gradient(circle at 30% 40%, #000 0%, transparent 70%);
        }
        .orb { position: absolute; border-radius: 50%; border: 1px solid rgba(255,255,255,.12); pointer-events: none; }
        .orb.one { width: 520px; height: 520px; right: -220px; bottom: -200px; }
        .orb.two { width: 340px; height: 340px; right: -120px; bottom: -110px; border-color: rgba(255,255,255,.18); }

        .brand > *:not(.orb) { position: relative; z-index: 1; }

        .brand { justify-content: center; align-items: center; }
        .emblem {
            width: 168px; height: 168px; border-radius: 44px;
            display: grid; place-items: center; font-size: 84px; color: #bbf7d0;
            background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.18);
            backdrop-filter: blur(6px);
            box-shadow: 0 0 0 18px rgba(255,255,255,.035), 0 0 0 40px rgba(255,255,255,.02), 0 30px 60px -20px rgba(0,0,0,.45);
        }

        .logo-ico {
            width: 46px; height: 46px; border-radius: 13px;
            display: grid; place-items: center; font-size: 23px;
            background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.22);
            backdrop-filter: blur(6px);
        }


        /* ================= FORM PANEL ================= */
        .panel { display: flex; align-items: center; justify-content: center; padding: 40px 24px; }

        .card { width: 100%; max-width: 400px; animation: rise .45s ease both; }
        @keyframes rise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }

        .mobile-logo { display: none; align-items: center; gap: 10px; margin-bottom: 30px; }
        .mobile-logo .logo-ico { background: linear-gradient(135deg, var(--accent), var(--accent-dark)); color: #fff; border: 0; width: 42px; height: 42px; font-size: 21px; }
        .mobile-logo b { font-size: 15px; }

        .card h2 { font-size: 26px; font-weight: 800; letter-spacing: -.5px; margin-bottom: 6px; }
        .card .sub { color: #64748b; font-size: 14.5px; margin-bottom: 28px; }

        .alert {
            display: flex; gap: 10px; align-items: flex-start;
            padding: 12px 14px; border-radius: 12px; margin-bottom: 20px;
            background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;
            font-size: 13.5px; line-height: 1.45;
            animation: shake .4s ease;
        }
        .alert i { font-size: 16px; margin-top: 1px; }
        .alert.ok { background: #f0fdf4; border-color: #bbf7d0; color: #166534; animation: none; }
        @keyframes shake { 0%,100% { transform: none; } 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } }

        .field { margin-bottom: 18px; }
        .field-top { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 7px; }
        .field label { font-size: 13.5px; font-weight: 600; color: var(--text); }

        .control { position: relative; }
        .control > i.lead {
            position: absolute; left: 15px; top: 50%; transform: translateY(-50%);
            color: var(--muted); font-size: 16px; pointer-events: none; transition: color .15s;
        }
        .control input {
            width: 100%; height: 50px;
            padding: 0 46px 0 44px;
            border: 1.5px solid var(--line); border-radius: 13px;
            background: #fff; color: var(--ink);
            font: inherit; font-size: 15px;
            outline: none; transition: border-color .15s, box-shadow .15s;
        }
        .control input::placeholder { color: #cbd5e1; }
        .control input:hover { border-color: #cbd5e1; }
        .control input:focus { border-color: var(--accent); box-shadow: 0 0 0 4px rgba(22,163,74,.13); }
        .control:focus-within > i.lead { color: var(--accent); }
        .control input.invalid { border-color: var(--danger); background: #fffafa; }

        .toggle-pw {
            position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
            width: 36px; height: 36px; border-radius: 10px; border: 0; background: none;
            color: var(--muted); cursor: pointer; font-size: 17px; display: grid; place-items: center;
        }
        .toggle-pw:hover { background: #f1f5f9; color: var(--text); }

        .caps {
            display: none; align-items: center; gap: 6px;
            margin-top: 7px; font-size: 12.5px; font-weight: 600; color: #b45309;
        }
        .caps.show { display: flex; }

        .row { display: flex; align-items: center; justify-content: space-between; gap: 8px 14px; flex-wrap: wrap; margin: 4px 0 24px; }
        .check { display: flex; align-items: center; gap: 9px; font-size: 13.5px; color: var(--text); cursor: pointer; user-select: none; }
        .check input { width: 17px; height: 17px; accent-color: var(--accent); cursor: pointer; }
        .help-link { font-size: 13px; color: var(--accent-dark); font-weight: 600; text-decoration: none; }
        .help-link:hover { text-decoration: underline; }

        .submit {
            width: 100%; height: 50px; border: 0; border-radius: 13px;
            display: flex; align-items: center; justify-content: center; gap: 9px;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            color: #fff; font: inherit; font-size: 15px; font-weight: 700; cursor: pointer;
            box-shadow: 0 12px 24px -12px rgba(22,163,74,.85);
            transition: transform .15s, box-shadow .15s, filter .15s;
        }
        .submit:hover { transform: translateY(-1px); box-shadow: 0 16px 28px -12px rgba(22,163,74,.9); }
        .submit:active { transform: none; }
        .submit:disabled { cursor: wait; filter: saturate(.7); transform: none; }
        .spinner { width: 17px; height: 17px; border-radius: 50%; border: 2.5px solid rgba(255,255,255,.35); border-top-color: #fff; animation: spin .7s linear infinite; display: none; }
        .submit.loading .spinner { display: block; }
        .submit.loading .arrow { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .secure {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            margin-top: 26px; padding-top: 22px; border-top: 1px solid var(--line);
            font-size: 12.5px; color: var(--muted); text-align: center;
        }
        .secure i { color: var(--accent); }

        /* ================= RESPONSIVE ================= */
        @media (max-width: 960px) {
            .auth { grid-template-columns: minmax(0, 1fr); }
            .brand { display: none; }
            .mobile-logo { display: flex; }
            .panel { align-items: flex-start; padding-top: 56px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
        }
        .alert.warn { background: #fffbeb; border-color: #fde68a; color: #92400e; }
    .status-link { display: flex; align-items: center; gap: 10px; margin-top: 18px; padding: 12px 14px; border-radius: 12px; border: 1px dashed #cbd5e1; color: #334155; text-decoration: none; font-size: 13.5px; }
    .status-link:hover { border-color: #16a34a; color: #15803d; background: #f0fdf4; }
    .status-link span { flex: 1; }
    .status-link b { color: #0f172a; }
</style>
</head>
<body>

<main class="auth">

    {{-- ================= BRAND PANEL ================= --}}
    <section class="brand" aria-hidden="true">
        <span class="orb one"></span>
        <span class="orb two"></span>

        <div class="emblem"><i class="bi bi-fingerprint"></i></div>
    </section>

    {{-- ================= FORM PANEL ================= --}}
    <section class="panel">
        <div class="card">

            <div class="mobile-logo">
                <span class="logo-ico"><i class="bi bi-fingerprint"></i></span>
                <b>NIN MANAGEMENT</b>
            </div>

            <h2>Welcome back</h2>
            <p class="sub">Sign in with your staff account to continue.</p>

            @if ($errors->any())
                <div class="alert" role="alert">
                    <i class="bi bi-exclamation-octagon-fill"></i>
                    <span>{{ $errors->first() }}</span>
                </div>
            @elseif (session('status'))
                <div class="alert ok" role="status">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>{{ session('status') }}</span>
                </div>
            @elseif (request()->boolean('expired'))
                <div class="alert warn" role="status">
                    <i class="bi bi-hourglass-bottom"></i>
                    <span>You were signed out because the system was not used for {{ (int) config('session.lifetime') }} minutes. Sign in again to continue.</span>
                </div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}" id="loginForm" novalidate>
                @csrf

                <div class="field">
                    <div class="field-top"><label for="email">Email address</label></div>
                    <div class="control">
                        <i class="bi bi-envelope lead"></i>
                        <input id="email" type="email" name="email" value="{{ old('email') }}"
                               class="{{ $errors->has('email') ? 'invalid' : '' }}"
                               placeholder="you@example.com" autocomplete="username" required autofocus>
                    </div>
                </div>

                <div class="field">
                    <div class="field-top"><label for="password">Password</label></div>
                    <div class="control">
                        <i class="bi bi-lock lead"></i>
                        <input id="password" type="password" name="password"
                               class="{{ $errors->has('password') ? 'invalid' : '' }}"
                               placeholder="Enter your password" autocomplete="current-password" required>
                        <button type="button" class="toggle-pw" id="togglePw" aria-label="Show password" title="Show password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="caps" id="capsWarn" role="status"><i class="bi bi-capslock-fill"></i> Caps Lock is on</div>
                </div>

                <div class="row">
                    <label class="check">
                        <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                        Keep me signed in
                    </label>
                    <a class="help-link" href="{{ route('password.request') }}">Forgot password?</a>
                </div>

                <button type="submit" class="submit" id="submitBtn">
                    <span class="spinner" aria-hidden="true"></span>
                    <span class="label">Sign in</span>
                    <i class="bi bi-arrow-right arrow"></i>
                </button>
            </form>

            <div class="secure">
                <i class="bi bi-shield-lock-fill"></i>
                <span>Access is monitored. Sign-ins are recorded in the activity log.</span>
            </div>

            <a class="status-link" href="{{ route('status.show') }}">
                <i class="bi bi-search"></i>
                <span><b>Applicant?</b> Check the status of your ID application</span>
                <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </section>
</main>

<script>
(function () {
    const form = document.getElementById('loginForm');
    const email = document.getElementById('email');
    const pw = document.getElementById('password');
    const toggle = document.getElementById('togglePw');
    const caps = document.getElementById('capsWarn');
    const btn = document.getElementById('submitBtn');

    // Kama email ipo tayari (baada ya kosa), weka mshale kwenye password.
    if (email.value) pw.focus();

    // Onyesha / ficha password
    toggle.addEventListener('click', function () {
        const show = pw.type === 'password';
        pw.type = show ? 'text' : 'password';
        this.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
        this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        this.title = show ? 'Hide password' : 'Show password';
        pw.focus();
    });

    // Onyo la Caps Lock
    ['keyup', 'keydown'].forEach(function (ev) {
        pw.addEventListener(ev, function (e) {
            if (e.getModifierState) caps.classList.toggle('show', e.getModifierState('CapsLock'));
        });
    });
    pw.addEventListener('blur', () => caps.classList.remove('show'));

    // Ondoa alama nyekundu mtumiaji akianza kuandika
    [email, pw].forEach((el) => el.addEventListener('input', () => el.classList.remove('invalid')));

    // Ukaguzi mdogo + hali ya "inaingia…"
    form.addEventListener('submit', function (e) {
        let bad = null;
        if (!email.value.trim() || !email.checkValidity()) bad = email;
        else if (!pw.value) bad = pw;

        if (bad) {
            e.preventDefault();
            bad.classList.add('invalid');
            bad.focus();
            return;
        }

        btn.disabled = true;
        btn.classList.add('loading');
        btn.querySelector('.label').textContent = 'Signing in…';
    });
})();
</script>

</body>
</html>
