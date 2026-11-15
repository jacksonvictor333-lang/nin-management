<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') | NIN Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root { --accent: #16a34a; --accent-dark: #15803d; --ink: #0f172a; --text: #334155; --muted: #94a3b8; --line: #e5e7eb; --danger: #dc2626; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh; display: grid; place-items: center; padding: 24px 16px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; color: var(--ink);
            background:
                radial-gradient(circle at 0% 0%, rgba(187,247,208,.6), transparent 40%),
                radial-gradient(circle at 100% 100%, rgba(254,240,138,.3), transparent 40%),
                #f8fafc;
        }
        .box { width: 100%; max-width: 420px; background: #fff; border: 1px solid var(--line); border-radius: 22px; padding: 30px 28px; box-shadow: 0 30px 60px -30px rgba(15,23,42,.35); }
        .logo { width: 52px; height: 52px; border-radius: 15px; display: grid; place-items: center; font-size: 25px; color: #fff; background: linear-gradient(135deg, var(--accent), var(--accent-dark)); margin-bottom: 18px; box-shadow: 0 10px 22px -10px rgba(22,163,74,.8); }
        h1 { font-size: 22px; letter-spacing: -.3px; }
        .sub { margin-top: 6px; font-size: 14px; color: #64748b; line-height: 1.55; }
        form { margin-top: 22px; display: grid; gap: 16px; }
        label { display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px; }
        .control { position: relative; }
        .control i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); }
        input[type=email], input[type=password], input[type=text] {
            width: 100%; height: 48px; padding: 0 14px 0 42px; border: 1.5px solid var(--line); border-radius: 12px;
            font: inherit; font-size: 15px; color: var(--ink); background: #fff; transition: border-color .15s, box-shadow .15s;
        }
        input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 4px rgba(22,163,74,.12); }
        input.is-invalid { border-color: var(--danger); }
        input.code { text-align: center; letter-spacing: 8px; font-size: 22px; font-weight: 700; padding: 0 14px; font-family: ui-monospace, Consolas, monospace; }
        .err { display: block; margin-top: 6px; font-size: 12.5px; color: var(--danger); }
        .btn { height: 48px; border: 0; border-radius: 12px; font: inherit; font-size: 15px; font-weight: 700; color: #fff; cursor: pointer; background: linear-gradient(135deg, var(--accent), var(--accent-dark)); box-shadow: 0 10px 22px -10px rgba(22,163,74,.8); display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn:disabled { opacity: .6; cursor: not-allowed; }
        .alert { margin-top: 18px; padding: 12px 14px; border-radius: 12px; font-size: 13.5px; display: flex; gap: 10px; line-height: 1.5; }
        .alert.ok { background: #dcfce7; color: #166534; }
        .alert.bad { background: #fee2e2; color: #991b1b; }
        .links { margin-top: 18px; text-align: center; font-size: 13.5px; color: #64748b; }
        .links a { color: var(--accent-dark); font-weight: 600; text-decoration: none; }
        .links a:hover { text-decoration: underline; }
        .toggle { background: none; border: 0; color: var(--accent-dark); font: inherit; font-weight: 600; cursor: pointer; font-size: 13.5px; }
    </style>
</head>
<body>
<main class="box">
    <div class="logo"><i class="bi @yield('icon', 'bi-fingerprint')"></i></div>
    @yield('content')
</main>
</body>
</html>
