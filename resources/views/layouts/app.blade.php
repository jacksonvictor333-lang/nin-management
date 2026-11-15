
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'NIN Management System')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
        :root {
            --primary: #15803d;
            --primary-dark: #14532d;
            --primary-light: #16a34a;
            --primary-soft: #dcfce7;
            --primary-soft-2: #ecfdf5;

            --ink: #0f172a;
            --text: #334155;
            --muted: #64748b;
            --muted-2: #94a3b8;

            --border: #e5e7eb;
            --bg: #f8fafc;

            --danger: #dc2626;

            --radius: 14px;
            --sidebar-w: 264px;

            --shadow-sm: 0 1px 2px rgba(15, 23, 42, .04);
            --shadow-md: 0 8px 24px -8px rgba(15, 23, 42, .12);
            --shadow-lg: 0 20px 45px -15px rgba(21, 128, 61, .25);
        }

        * {
            box-sizing: border-box;
        }

        html {
            margin: 0;
            padding: 0;
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        a {
            -webkit-tap-highlight-color: transparent;
        }

        /* ================= SIDEBAR ================= */

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: var(--sidebar-w);
            background: #fff;
            border-right: 1px solid var(--border);
            z-index: 60;

            display: flex;
            flex-direction: column;

            overflow-y: auto;
            transition: transform .3s ease;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;

            height: 82px;
            padding: 0 20px;

            border-bottom: 1px solid var(--border);

            text-decoration: none;
            color: var(--ink);

            flex-shrink: 0;
        }

        .brand-ico {
            width: 42px;
            height: 42px;
            min-width: 42px;

            border-radius: 11px;

            background: linear-gradient(
                135deg,
                var(--primary-light),
                var(--primary)
            );

            color: #fff;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 21px;

            box-shadow: 0 6px 14px -4px rgba(22, 163, 74, .5);
        }

        .sidebar-brand b {
            display: block;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: .2px;
            line-height: 1.3;
        }

        .sidebar-brand small {
            font-size: 12px;
            color: var(--muted);
        }

        .menu-label {
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: .9px;
            color: var(--muted-2);
            padding: 22px 22px 10px;
            text-transform: uppercase;
        }

        .menu {
            list-style: none;
            margin: 0;
            padding: 0 14px;

            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .menu a,
        .menu button {
            width: 100%;

            display: flex;
            align-items: center;
            gap: 14px;

            padding: 13px 14px;

            border-radius: 12px;

            color: var(--text);

            font-family: inherit;
            font-size: 14px;
            font-weight: 500;

            text-decoration: none;

            background: transparent;
            border: 0;

            cursor: pointer;
            text-align: left;

            transition: background .15s ease, color .15s ease, transform .15s ease;
        }

        .menu a i,
        .menu button i {
            font-size: 18px;
            width: 22px;
            text-align: center;
        }

        .menu a:hover,
        .menu button:hover {
            background: var(--bg);
            color: var(--ink);
            transform: translateX(2px);
        }

        .menu a.active {
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-weight: 700;
        }

        .menu .count {
            margin-left: auto;
            background: var(--primary-soft);
            color: var(--primary-dark);

            font-size: 12px;
            font-weight: 700;

            padding: 3px 9px;
            border-radius: 999px;
        }

        .menu a.active .count {
            background: #fff;
        }

        .menu .logout:hover {
            background: #fef2f2;
            color: var(--danger);
        }

        .sidebar-foot {
            margin: auto 14px 18px;
            padding: 15px;

            border: 1px solid var(--border);
            background: var(--primary-soft-2);

            border-radius: 12px;

            font-size: 12.5px;
            color: var(--primary-dark);
        }

        .sidebar-foot b {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .sidebar-foot span {
            color: var(--muted);
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #22c55e;

            box-shadow: 0 0 0 3px rgba(34, 197, 94, .25);
        }

        .overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .45);
            z-index: 55;
        }

        .overlay.show {
            display: block;
        }

        /* ================= MAIN SHELL ================= */

        .shell {
            margin-left: var(--sidebar-w);
            width: calc(100% - var(--sidebar-w));
            min-height: 100vh;
        }

        /* ================= TOPBAR ================= */

        .topbar {
            position: sticky;
            top: 0;
            z-index: 40;

            height: 82px;

            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;

            padding: 0 32px;

            background: #fff;
            border-bottom: 1px solid var(--border);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .topbar-left small {
            display: block;
            color: var(--muted);
            font-size: 13px;
        }

        .topbar-left h1 {
            margin: 3px 0 0;
            font-size: 19px;
            font-weight: 700;
            color: var(--ink);
        }

        .burger {
            display: none;

            width: 42px;
            height: 42px;

            border: 1px solid var(--border);
            background: #fff;

            border-radius: 10px;
            font-size: 20px;

            cursor: pointer;
            color: var(--ink);
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .navbar-count {
            display: flex;
            align-items: center;
            gap: 7px;

            background: var(--primary-soft-2);
            color: var(--primary-dark);
            border: 1px solid var(--primary-soft);

            padding: 9px 15px;
            border-radius: 999px;

            font-size: 12.5px;
            font-weight: 700;
        }

        .user {
            display: flex;
            align-items: center;
            gap: 12px;

            padding-left: 18px;
            border-left: 1px solid var(--border);
        }

        .avatar {
            width: 40px;
            height: 40px;
            min-width: 40px;

            border-radius: 50%;

            background: linear-gradient(
                135deg,
                var(--primary-light),
                var(--primary)
            );

            color: #fff;

            display: flex;
            align-items: center;
            justify-content: center;

            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
        }

        .user b {
            display: block;
            font-size: 14px;
            color: var(--ink);
            line-height: 1.3;
        }

        .user small {
            font-size: 12px;
            color: var(--muted);
        }

        /* ================= PAGE CONTAINER ================= */

        .container {
            width: 100%;
            max-width: none;
            margin: 0;

            padding: 30px 32px 60px;
        }

        .page-head {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;

            margin-bottom: 22px;
            flex-wrap: wrap;
        }

        .page-head h2 {
            margin: 0;
            font-size: 23px;
            font-weight: 800;
            color: var(--ink);
        }

        .page-head p {
            margin: 5px 0 0;
            font-size: 13.5px;
            color: var(--muted);
        }

        /* ================= STAT CARDS ================= */

        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 16px;

            min-width: 0;

            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);

            box-shadow: var(--shadow-sm);

            padding: 23px 22px;

            text-decoration: none;
            color: inherit;

            transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary-light);
        }

        .stat-ico {
            width: 52px;
            height: 52px;
            min-width: 52px;

            border-radius: 13px;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 21px;

            color: var(--primary-dark);
            background: var(--primary-soft);
        }

        .stat-card.male .stat-ico {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .stat-card.female .stat-ico {
            background: #fce7f3;
            color: #be185d;
        }

        .stat-card.updated .stat-ico {
            background: #fef3c7;
            color: #b45309;
        }

        .stat-info {
            min-width: 0;
        }

        .stat-info .stat-value {
            font-size: 27px;
            font-weight: 800;
            color: var(--ink);
            line-height: 1.2;
        }

        .stat-info .stat-label {
            font-size: 12.5px;
            color: var(--muted);
            font-weight: 600;
            margin-top: 5px;
        }

        /* ================= CARDS ================= */

        .card {
            width: 100%;

            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);

            box-shadow: var(--shadow-md);

            padding: 28px;
            margin-bottom: 22px;
        }

        .card-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;

            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .card-head h3 {
            margin: 0 0 6px;
            font-size: 18px;
            font-weight: 800;
            color: var(--ink);
        }

        .card-head p {
            margin: 0;
            font-size: 13px;
            color: var(--muted);
            max-width: 600px;
            line-height: 1.6;
        }

        /* ================= BUTTONS ================= */

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;

            padding: 12px 19px;

            background: linear-gradient(
                135deg,
                var(--primary-light),
                var(--primary)
            );

            color: #fff;
            text-decoration: none;

            border: none;
            border-radius: 10px;

            font-family: inherit;
            font-size: 13.5px;
            font-weight: 600;

            cursor: pointer;

            box-shadow: 0 6px 16px -6px rgba(21, 128, 61, .55);

            transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
        }

        .btn:hover {
            filter: brightness(1.04);
            transform: translateY(-1px);
            box-shadow: 0 10px 20px -6px rgba(21, 128, 61, .6);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-outline {
            background: #fff;
            color: var(--primary-dark);
            border: 1px solid var(--border);
            box-shadow: none;
        }

        .btn-outline:hover {
            background: var(--primary-soft-2);
            border-color: var(--primary-light);
        }

        /* ================= FORMS ================= */

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }

        .form-group {
            min-width: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;

            font-weight: 600;
            font-size: 13px;
            color: var(--ink);
        }

        .form-group label .req {
            color: var(--danger);
            margin-left: 2px;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 13px 14px;

            border: 1.5px solid var(--border);
            border-radius: 10px;

            font-family: inherit;
            font-size: 14px;
            color: var(--ink);
            background: #fff;

            transition: border-color .15s ease, box-shadow .15s ease;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 4px var(--primary-soft);
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        /* ================= TABLE ================= */

        .table-wrap {
            width: 100%;
            overflow-x: auto;

            border: 1px solid var(--border);
            border-radius: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 560px;
        }

        th,
        td {
            text-align: left;
            padding: 15px 17px;

            font-size: 13.5px;
            border-bottom: 1px solid var(--border);

            white-space: nowrap;
        }

        thead th {
            background: var(--primary-soft-2);
            color: var(--primary-dark);

            font-weight: 700;
            text-transform: uppercase;

            font-size: 11.5px;
            letter-spacing: .4px;
        }

        tbody tr {
            transition: background .12s ease;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .nin-pill {
            display: inline-block;

            font-family: 'SFMono-Regular', Consolas, monospace;
            font-weight: 700;
            font-size: 12.5px;
            letter-spacing: .5px;

            color: var(--primary-dark);
            background: var(--primary-soft);

            padding: 6px 10px;
            border-radius: 7px;
        }

        .gender-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;

            padding: 5px 11px;
            border-radius: 999px;

            font-size: 11.5px;
            font-weight: 700;
        }

        .gender-badge.male {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .gender-badge.female {
            background: #fce7f3;
            color: #be185d;
        }

        .row-index {
            color: var(--muted-2);
            font-weight: 600;
        }

        .person-cell {
            display: flex;
            align-items: center;
            gap: 10px;

            font-weight: 600;
            color: var(--ink);
        }

        .person-cell .avatar {
            width: 36px;
            height: 36px;
            min-width: 36px;
            font-size: 12.5px;
        }

        .view-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;

            color: var(--primary-dark);

            font-weight: 700;
            font-size: 12.5px;
            text-decoration: none;

            padding: 7px 12px;
            border-radius: 8px;

            background: var(--primary-soft);

            transition: background .15s ease;
        }

        .view-link:hover {
            background: #bbf7d0;
        }

        .nin-display {
            color: var(--primary);
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 2px;
            overflow-wrap: anywhere;
        }

        /* ================= ALERTS ================= */

        .success,
        .alert-error {
            display: flex;
            align-items: center;
            gap: 10px;

            padding: 14px 18px;
            border-radius: 12px;

            margin-bottom: 20px;

            font-size: 13.5px;
            font-weight: 600;
        }

        .success {
            background: var(--primary-soft);
            color: var(--primary-dark);
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fef2f2;
            color: var(--danger);
            border: 1px solid #fecaca;
        }

        .error {
            color: var(--danger);
            font-size: 12.5px;
            margin-top: 6px;
            font-weight: 500;
        }

        /* ================= EMPTY STATE ================= */

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--muted);
        }

        .empty-state i {
            font-size: 34px;
            color: var(--muted-2);
            margin-bottom: 10px;
            display: block;
        }

        /* ================= PAGINATION ================= */

        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 7px;
            margin-top: 22px;
        }

        .pagination a,
        .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-width: 36px;
            height: 36px;

            padding: 0 11px;

            border: 1px solid var(--border);
            border-radius: 8px;

            background: #fff;
            color: var(--text);

            text-decoration: none;
            font-size: 13px;
        }

        .pagination .active,
        .pagination span[aria-current="page"] {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }

        /* ================= RESPONSIVE ================= */

        @media (max-width: 1200px) {
            .container {
                padding-left: 24px;
                padding-right: 24px;
            }

            .stats-row {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 900px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
                box-shadow: 0 0 40px rgba(15, 23, 42, .25);
            }

            .shell {
                margin-left: 0;
                width: 100%;
            }

            .burger {
                display: block;
            }

            .topbar {
                padding: 0 18px;
            }

            .user > div:last-child {
                display: none;
            }

            .user {
                padding-left: 12px;
            }
        }

        @media (max-width: 650px) {
            .container {
                padding: 22px 14px 40px;
            }

            .card {
                padding: 18px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .page-head {
                flex-direction: column;
                align-items: flex-start;
            }

            .navbar-count {
                display: none;
            }

            .topbar-left h1 {
                font-size: 17px;
            }

            .stats-row {
                grid-template-columns: 1fr;
                gap: 13px;
            }

            .stat-card {
                padding: 18px;
            }
        }
        /* ===== Menu (compact) + bar nyembamba ya kijani ===== */
        .menu-scroll { flex: 1; overflow-y: auto; padding-bottom: 10px; }
        .menu-label { padding: 16px 22px 6px !important; font-size: 10.5px !important; }
        .menu { gap: 1px !important; }
        .menu a, .menu button {
            position: relative;
            padding: 8px 12px !important;
            gap: 11px !important;
            border-radius: 9px !important;
            font-size: 13.5px !important;
        }
        .menu a i, .menu button i { font-size: 16px !important; width: 18px !important; }
        .menu a:hover, .menu button:hover { transform: none !important; }
        .menu a.active {
            background: #f0fdf4 !important;
            color: var(--primary-dark) !important;
            font-weight: 650 !important;
        }
        .menu a.active::before {
            content: '';
            position: absolute; left: -14px; top: 7px; bottom: 7px;
            width: 3px; border-radius: 0 3px 3px 0;
            background: var(--primary);
        }
        .menu .count { padding: 1px 8px !important; font-size: 11px !important; }
        .sidebar-foot { margin: 8px 14px 14px !important; padding: 11px 13px !important; }

        /* ===== UI ya pamoja kwa kurasa mpya ===== */
        .ui-page { display: flex; flex-direction: column; gap: 18px; }
        .ui-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
        .ui-head h2 { margin: 0; font-size: 22px; font-weight: 800; color: var(--ink); letter-spacing: -.3px; }
        .ui-head p { margin: 4px 0 0; font-size: 13.5px; color: var(--muted); }
        .ui-head-actions { display: flex; gap: 8px; flex-wrap: wrap; }

        .ui-card { background: #fff; border: 1px solid var(--border); border-radius: 18px; padding: 20px 22px; min-width: 0; }
        .ui-card.flush { padding: 0; overflow: hidden; }
        .ui-card-head { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
        .ui-card.flush .ui-card-head { padding: 18px 22px 0; }
        .ui-card-head .ico { width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; background: var(--primary-soft); color: var(--primary); font-size: 17px; flex-shrink: 0; }
        .ui-card-head h3 { margin: 0; font-size: 15px; color: var(--ink); }
        .ui-card-head p { margin: 2px 0 0; font-size: 12.5px; color: var(--muted); }
        .ui-card-head .end { margin-left: auto; }

        .ui-grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .ui-grid-3 { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: 18px; align-items: start; }

        .ui-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; }
        .ui-stat { background: #fff; border: 1px solid var(--border); border-radius: 16px; padding: 16px 18px; display: flex; align-items: center; gap: 14px; }
        .ui-stat .ico { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; font-size: 19px; background: #f1f5f9; color: var(--text); flex-shrink: 0; }
        .ui-stat .ico.g { background: #dcfce7; color: #15803d; }
        .ui-stat .ico.r { background: #fee2e2; color: #b91c1c; }
        .ui-stat .ico.a { background: #fef3c7; color: #b45309; }
        .ui-stat .ico.b { background: #dbeafe; color: #1d4ed8; }
        .ui-stat small { display: block; font-size: 12.5px; color: var(--muted); }
        .ui-stat b { font-size: 24px; color: var(--ink); font-weight: 800; letter-spacing: -.4px; }

        .ui-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 7px;
            height: 40px; padding: 0 16px; border-radius: 11px;
            font: inherit; font-size: 13.5px; font-weight: 700; cursor: pointer; text-decoration: none; white-space: nowrap;
            border: 1px solid var(--border); background: #fff; color: var(--text); transition: all .15s;
        }
        .ui-btn:hover { border-color: #cbd5e1; background: #f8fafc; }
        .ui-btn.primary { background: var(--primary-light); border-color: var(--primary-light); color: #fff; box-shadow: 0 8px 18px -10px rgba(22,163,74,.8); }
        .ui-btn.primary:hover { background: var(--primary); }
        .ui-btn.danger { color: #b91c1c; }
        .ui-btn.danger:hover { background: #fef2f2; border-color: #fecaca; }
        .ui-btn.sm { height: 32px; padding: 0 11px; font-size: 12.5px; border-radius: 9px; }
        .ui-btn.icon { width: 34px; padding: 0; height: 34px; }
        .ui-btn:disabled { opacity: .5; cursor: not-allowed; }

        .ui-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
        .ui-field label { font-size: 13px; font-weight: 600; color: var(--text); }
        .ui-input, .ui-select {
            width: 100%; height: 42px; padding: 0 13px; border-radius: 11px;
            border: 1.5px solid var(--border); background: #f8fafc; color: var(--ink);
            font: inherit; font-size: 14px; outline: none; transition: border-color .15s, box-shadow .15s;
        }
        .ui-input:focus, .ui-select:focus { border-color: var(--primary-light); background: #fff; box-shadow: 0 0 0 4px rgba(22,163,74,.12); }
        .ui-input.is-invalid, .ui-select.is-invalid { border-color: #dc2626; background: #fef2f2; }
        .ui-err { font-size: 12px; color: #dc2626; }
        .ui-help { font-size: 12px; color: var(--muted); }
        .ui-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 16px; }
        .ui-form-grid .full { grid-column: 1 / -1; }

        .ui-toolbar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; background: #fff; border: 1px solid var(--border); border-radius: 16px; padding: 12px; }
        .ui-search { position: relative; flex: 1; min-width: 220px; }
        .ui-search i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--muted-2); }
        .ui-search .ui-input { padding-left: 38px; }

        .ui-seg { display: inline-flex; background: #f1f5f9; border-radius: 11px; padding: 3px; gap: 2px; flex-wrap: wrap; }
        .ui-seg a { padding: 7px 12px; border-radius: 9px; font-size: 13px; font-weight: 600; color: var(--muted); text-decoration: none; white-space: nowrap; }
        .ui-seg a.on { background: #fff; color: var(--ink); box-shadow: 0 1px 3px rgba(15,23,42,.12); }

        .ui-table-wrap { overflow-x: auto; }
        .ui-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        .ui-table th { text-align: left; font-size: 11.5px; text-transform: uppercase; letter-spacing: .6px; color: var(--muted-2); font-weight: 700; padding: 11px 18px; background: #f8fafc; border-bottom: 1px solid var(--border); white-space: nowrap; }
        .ui-table td { padding: 12px 18px; border-bottom: 1px solid #f1f5f9; color: var(--text); vertical-align: middle; }
        .ui-table tbody tr:hover { background: #fafafa; }
        .ui-table tr:last-child td { border-bottom: 0; }
        .ui-table .num { text-align: right; font-variant-numeric: tabular-nums; }
        .ui-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12.5px; color: var(--ink); letter-spacing: .2px; }
        .ui-who { display: flex; align-items: center; gap: 11px; }
        .ui-who b { display: block; color: var(--ink); font-size: 13.5px; }
        .ui-who small { color: var(--muted); font-size: 12px; }
        .ui-link { color: var(--primary); font-weight: 700; text-decoration: none; }
        .ui-link:hover { text-decoration: underline; }

        .ui-pill { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 999px; background: #f1f5f9; color: var(--text); white-space: nowrap; }
        .ui-pill.ok { background: #dcfce7; color: #166534; }
        .ui-pill.bad { background: #fee2e2; color: #991b1b; }
        .ui-pill.warn { background: #fef3c7; color: #92400e; }
        .ui-pill.info { background: #dbeafe; color: #1e40af; }
        .ui-pill.violet { background: #ede9fe; color: #5b21b6; }

        .ui-empty { text-align: center; padding: 42px 16px; color: var(--muted); font-size: 14px; }
        .ui-empty i { display: block; font-size: 34px; color: #cbd5e1; margin-bottom: 8px; }

        .ui-pager { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; font-size: 13px; color: var(--muted); }
        .ui-pager nav { display: flex; gap: 6px; }
        .ui-pager a, .ui-pager span.pg { min-width: 34px; height: 34px; padding: 0 10px; border-radius: 9px; display: inline-grid; place-items: center; border: 1px solid var(--border); background: #fff; color: var(--text); text-decoration: none; font-weight: 600; }
        .ui-pager a:hover { border-color: var(--primary-light); color: var(--primary); }
        .ui-pager span.pg.on { background: var(--primary-light); border-color: var(--primary-light); color: #fff; }
        .ui-pager span.pg.off { opacity: .4; }

        dialog.ui-dialog { border: 0; border-radius: 20px; padding: 0; width: min(520px, calc(100vw - 32px)); box-shadow: 0 30px 60px -20px rgba(0,0,0,.45); }
        dialog.ui-dialog::backdrop { background: rgba(15,23,42,.5); backdrop-filter: blur(2px); }
        .ui-dialog-head { display: flex; align-items: center; gap: 12px; padding: 18px 22px; border-bottom: 1px solid var(--border); }
        .ui-dialog-head h3 { margin: 0; font-size: 16px; color: var(--ink); }
        .ui-dialog-head button { margin-left: auto; }
        .ui-dialog-body { padding: 20px 22px; }
        .ui-dialog-foot { display: flex; justify-content: flex-end; gap: 10px; padding: 14px 22px; border-top: 1px solid var(--border); background: #fcfcfd; }

        @media (max-width: 1000px) {
            .ui-grid-2, .ui-grid-3 { grid-template-columns: 1fr; }
        }
        @media (max-width: 600px) {
            .ui-form-grid { grid-template-columns: 1fr; }
        }
    </style>

    @stack('styles')
</head>

<body>

@php
    $totalPersons = \App\Models\Person::count();

    $link = function (string $name, $params = []) {
        return \Illuminate\Support\Facades\Route::has($name)
            ? route($name, $params)
            : '#';
    };

    $authUser = auth()->user();

    $userName = $authUser->name ?? 'Administrator';
    $userRole = $authUser->role ?? 'Administrator';

    $initial = mb_substr($userName, 0, 1);
@endphp

{{-- ================= SIDEBAR ================= --}}

<aside class="sidebar" id="sidebar">

    <a href="{{ $link('dashboard') }}" class="sidebar-brand">

        <span class="brand-ico">
            <i class="bi bi-fingerprint"></i>
        </span>

        <span>
            <b>NIN MANAGEMENT</b>
            <small>National Identity Records</small>
        </span>

    </a>

    @php
        $role = $authUser->role ?? 'Administrator';
        $isAdmin = $role === 'Administrator';
        $canEdit = in_array($role, ['Administrator', 'Registrar'], true);

        $sections = [
            'Main Menu' => [
                ['dashboard', 'bi-house-door', 'Dashboard', 'dashboard', true],
                ['people.index', 'bi-people', 'Registered Persons', ['people.index', 'people.show', 'people.edit'], true, number_format($totalPersons)],
                ['people.create', 'bi-person-plus', 'Register New Person', 'people.create', $canEdit],
                ['search', 'bi-search', 'Search NIN', 'search', true],
                ['verifications.index', 'bi-shield-check', 'NIN Verifications', 'verifications.*', true],
                ['data-quality.index', 'bi-clipboard2-pulse', 'Data Quality', 'data-quality.*', true],
            ],
            'Reports' => [
                ['reports.index', 'bi-file-earmark-bar-graph', 'Reports & Export', 'reports.*', true],
                ['activity.index', 'bi-clock-history', 'Activity Log', 'activity.*', true],
            ],
            'Administration' => [
                ['users.index', 'bi-person-gear', 'Users', 'users.*', $isAdmin],
                ['geo.index', 'bi-geo-alt', 'Locations', 'geo.*', true],
                ['archive.index', 'bi-archive', 'Archive', 'archive.*', $isAdmin],
                ['backups.index', 'bi-database-down', 'Backups', 'backups.*', $isAdmin],
                ['settings.api', 'bi-key', 'API Settings', 'settings.*', $isAdmin],
            ],
            'Account' => [
                ['account.show', 'bi-person-circle', 'My Account', 'account.*', true],
            ],
        ];
    @endphp

    <nav class="menu-scroll">
        @foreach ($sections as $label => $items)
            @php $visible = array_filter($items, fn ($i) => $i[4]); @endphp
            @if ($visible)
                <div class="menu-label">{{ $label }}</div>
                <ul class="menu">
                    @foreach ($visible as $item)
                        @php [$route, $icon, $text, $pattern] = $item; $active = request()->routeIs(...(array) $pattern); @endphp
                        <li>
                            <a href="{{ $link($route) }}" class="{{ $active ? 'active' : '' }}" @if ($route === 'search') id="menuSearch" @endif>
                                <i class="bi {{ $icon }}"></i>
                                <span>{{ $text }}</span>
                                @isset($item[5])<span class="count">{{ $item[5] }}</span>@endisset
                            </a>
                        </li>
                    @endforeach
                    @if ($label === 'Account')
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="logout"><i class="bi bi-box-arrow-right"></i><span>Logout</span></button>
                            </form>
                        </li>
                    @endif
                </ul>
            @endif
        @endforeach
    </nav>

    <div class="sidebar-foot">

        <b>
            <span class="dot"></span>
            System Online
        </b>

        <span>All services running</span>

    </div>

</aside>

<div class="overlay" id="overlay"></div>

{{-- ================= MAIN CONTENT ================= --}}

<div class="shell">

    {{-- TOPBAR --}}

    <header class="topbar">

        <div class="topbar-left">

            <button class="burger"
                    id="burger"
                    type="button"
                    aria-label="Open menu">

                <i class="bi bi-list"></i>

            </button>

            <div>

                <small>Welcome back 👋</small>

                <h1>@yield('page_title', 'Dashboard')</h1>

            </div>

        </div>

        <div class="topbar-right">

            <div class="navbar-count">

                <i class="bi bi-people-fill"></i>

                {{ number_format($totalPersons) }} Registered

            </div>

            <a href="{{ $link('account.show') }}" class="user" style="text-decoration:none;color:inherit">

                <div class="avatar">
                    {{ $initial }}
                </div>

                <div>
                    <b>{{ $userName }}</b>
                    <small>{{ $userRole }}</small>
                </div>

            </a>

        </div>

    </header>

    {{-- PAGE CONTENT --}}

    <main class="container">

        @if(session('success'))

            <div class="success">

                <i class="bi bi-check-circle-fill"></i>

                <span>{{ session('success') }}</span>

            </div>

        @endif

        @if(session('error'))

            <div class="alert-error">

                <i class="bi bi-exclamation-triangle-fill"></i>

                <span>{{ session('error') }}</span>

            </div>

        @endif

        @if($errors->any())

            <div class="alert-error">

                <i class="bi bi-exclamation-triangle-fill"></i>

                <div>
                    Please check the form and correct the errors.
                </div>

            </div>

        @endif

        @yield('content')

    </main>

</div>

{{-- ================= JAVASCRIPT ================= --}}

<script>
(function () {

    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const burger = document.getElementById('burger');

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('show');
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
    }

    if (burger) {
        burger.addEventListener('click', function () {

            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }

        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    // Close sidebar after selecting a menu link on mobile
    document.querySelectorAll('.menu a').forEach(function (link) {

        link.addEventListener('click', function () {

            if (window.innerWidth <= 900) {
                closeSidebar();
            }

        });

    });

    // Search NIN shortcut
    const menuSearch = document.getElementById('menuSearch');

    if (menuSearch) {

        menuSearch.addEventListener('click', function (e) {

            const searchBox =
                document.getElementById('search') ||
                document.getElementById('dashboardSearch');

            if (searchBox) {

                e.preventDefault();

                closeSidebar();

                searchBox.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });

                setTimeout(function () {
                    searchBox.focus();
                }, 350);

            }

        });

    }

    // Handle direct #search navigation
    if (window.location.hash === '#search') {

        setTimeout(function () {

            const searchBox =
                document.getElementById('search') ||
                document.getElementById('dashboardSearch');

            if (searchBox) {
                searchBox.focus();
                searchBox.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }

        }, 300);

    }

    // Close sidebar when screen becomes desktop
    window.addEventListener('resize', function () {

        if (window.innerWidth > 900) {
            closeSidebar();
        }

    });

})();
</script>

@stack('scripts')

</body>
</html>
