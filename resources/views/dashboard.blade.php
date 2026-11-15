@extends('layouts.app')

@section('title', 'Dashboard')

@section('page_title', 'Dashboard')

@php
    $malePct   = $total > 0 ? round($male / $total * 100) : 0;
    $femalePct = $total > 0 ? round($female / $total * 100) : 0;
    $otherCount = max($total - $male - $female, 0);

    $delta = $thisMonth - $lastMonth;
    $deltaPct = $lastMonth > 0 ? round($delta / $lastMonth * 100) : null;

    // Kipimo safi cha mhimili wa Y kwa chati ya miezi.
    $monthMax = max($monthly->max('count'), 1);
    $step = $monthMax <= 4 ? 1 : (int) ceil($monthMax / 4);
    $magnitude = 10 ** max(strlen((string) $step) - 1, 0);
    $step = (int) (ceil($step / $magnitude) * $magnitude);
    $yMax = $step * (int) ceil($monthMax / $step);
    $yTicks = range($yMax, 0, -$step);

    $regionMax = max($topRegions->max() ?? 0, 1);
    $ageMax = max($ageGroups->max(), 1);

    $sparkMax = $monthMax;
    $sparkPoints = $monthly->values()->map(function ($m, $i) use ($sparkMax) {
        return round($i * (120 / 11), 1) . ',' . round(30 - ($m['count'] / $sparkMax) * 26, 1);
    })->implode(' ');

    $firstName = auth()->check() ? explode(' ', auth()->user()->name)[0] : 'User';
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
@endphp

@push('styles')
<style>
    .viz-root {
        --surface-1: #ffffff;
        --text-primary: #0f172a;
        --text-secondary: #475569;
        --text-muted: #94a3b8;
        --grid: #eef2f6;
        --accent: #16a34a;
        --accent-soft: #dcfce7;
        --series-1: #2a78d6;
        --series-2: #eb6834;
        --neutral: #cbd5e1;
        --good: #15803d;
        --bad: #dc2626;
    }

    .dash { display: flex; flex-direction: column; gap: 22px; }

    /* ---------- HERO ---------- */
    .hero {
        position: relative;
        overflow: hidden;
        border-radius: 20px;
        padding: 14px 22px;
        color: #fff;
        background:
            radial-gradient(circle at 85% -20%, rgba(255,255,255,.18), transparent 45%),
            radial-gradient(circle at 10% 120%, rgba(134,239,172,.25), transparent 40%),
            linear-gradient(120deg, #14532d 0%, #15803d 55%, #16a34a 100%);
        display: grid;
        grid-template-columns: 1.2fr 1fr;
        gap: 20px;
        align-items: center;
        border-radius: 16px;
        box-shadow: 0 14px 30px -20px rgba(21, 128, 61, .55);
    }

    .hero-date {
        display: inline-flex; align-items: center; gap: 7px;
        font-size: 12.5px; font-weight: 600;
        background: rgba(255,255,255,.14);
        border: 1px solid rgba(255,255,255,.2);
        padding: 2px 9px; border-radius: 999px;
        font-size: 11.5px;
    }

    .hero-top { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 4px; }
    .hero h2 { margin: 0; font-size: 19px; font-weight: 800; letter-spacing: -.2px; }
    .hero p { margin: 0 0 10px; opacity: .85; font-size: 13px; line-height: 1.4; }

    .hero-actions { display: flex; gap: 10px; flex-wrap: wrap; }

    .btn-hero {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 6px 12px; border-radius: 9px; font-size: 13px !important;
        font-weight: 700; font-size: 14px; text-decoration: none;
        transition: transform .15s, background .15s;
    }
    .btn-hero:hover { transform: translateY(-1px); }
    .btn-hero.light { background: #fff; color: #15803d; }
    .btn-hero.ghost { color: #fff; border: 1px solid rgba(255,255,255,.35); background: rgba(255,255,255,.08); }
    .btn-hero.ghost:hover { background: rgba(255,255,255,.16); }

    /* Lookup */
    .lookup {
        background: rgba(255,255,255,.1);
        border: 1px solid rgba(255,255,255,.2);
        backdrop-filter: blur(6px);
        border-radius: 12px;
        padding: 10px 12px;
    }
    .lookup label { display: flex; justify-content: space-between; font-size: 12.5px; font-weight: 700; margin-bottom: 6px; }
    .lookup-row { display: flex; gap: 8px; }
    .lookup input {
        flex: 1; min-width: 0;
        border: 0; border-radius: 11px;
        padding: 8px 12px;
        font: inherit; font-size: 14px; letter-spacing: .5px;
        color: var(--text-primary);
        outline: none;
    }
    .lookup input:focus { box-shadow: 0 0 0 3px rgba(134,239,172,.6); }
    .lookup button {
        border: 0; border-radius: 11px; padding: 0 16px;
        background: #052e16; color: #fff; font-weight: 700; cursor: pointer;
        display: inline-flex; align-items: center; gap: 6px;
    }
    .lookup button:disabled { opacity: .6; cursor: wait; }
    .lookup-hint { font-size: 11.5px; font-weight: 500; opacity: .8; }

    /* Dropdown ya matokeo: inaelea juu ya ukurasa, bar haiongezeki urefu */
    .hero { overflow: visible; }
    .lookup { position: relative; }
    .lookup-result {
        position: absolute; left: 0; right: 0; top: calc(100% + 6px); z-index: 50;
        background: #fff; color: var(--text-primary);
        border: 1px solid #e5e7eb; border-radius: 14px;
        box-shadow: 0 22px 44px -18px rgba(15,23,42,.35);
        padding: 6px; display: none; max-height: 360px; overflow-y: auto;
    }
    .lookup-result.show { display: block; }
    .lookup-msg { padding: 12px; font-size: 13px; color: var(--text-secondary); display: flex; align-items: center; gap: 8px; }
    .lookup-msg.error { color: var(--bad); font-weight: 600; }
    .lookup-item {
        display: flex; align-items: center; gap: 11px;
        padding: 8px 10px; border-radius: 10px; text-decoration: none; color: inherit;
    }
    .lookup-item:hover, .lookup-item.active { background: #f0fdf4; }
    .lookup-avatar {
        width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0;
        background: var(--accent-soft); color: var(--accent);
        display: grid; place-items: center; font-weight: 800; font-size: 13px; overflow: hidden;
    }
    .lookup-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .lookup-item .info { min-width: 0; flex: 1; }
    .lookup-item b { display: block; font-size: 13.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .lookup-item b mark { background: #fef08a; color: inherit; border-radius: 3px; padding: 0 1px; }
    .lookup-item small { display: block; color: var(--text-secondary); font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .lookup-item .nin-sm { font-family: ui-monospace, Menlo, monospace; font-size: 11.5px; color: var(--text-muted); }
    .lookup-item .go { color: var(--text-muted); font-size: 13px; }
    .lookup-item.active .go { color: var(--accent); }
    .lookup-badge { font-size: 10.5px; font-weight: 700; padding: 1px 7px; border-radius: 999px; background: #dcfce7; color: #166534; margin-left: 6px; vertical-align: middle; }
    .lookup-more {
        display: block; text-align: center; padding: 9px; margin-top: 4px; border-top: 1px solid #f1f5f9;
        font-size: 12.5px; font-weight: 700; color: var(--accent); text-decoration: none;
    }
    .lookup-more:hover { background: #f8fafc; border-radius: 0 0 10px 10px; }

    /* ---------- KPI TILES ---------- */
    .kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }

    .kpi {
        background: var(--surface-1);
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        padding: 18px;
        display: flex; flex-direction: column; gap: 10px;
        transition: box-shadow .2s, transform .2s;
    }
    .kpi:hover { box-shadow: 0 12px 28px -14px rgba(15,23,42,.18); transform: translateY(-2px); }

    .kpi-top { display: flex; align-items: center; justify-content: space-between; }
    .kpi-label { font-size: 13px; font-weight: 600; color: var(--text-secondary); }
    .kpi-ico {
        width: 36px; height: 36px; border-radius: 10px;
        display: grid; place-items: center; font-size: 17px;
        background: #f1f5f9; color: var(--text-secondary);
    }
    .kpi-ico.accent { background: var(--accent-soft); color: var(--accent); }

    .kpi-value { font-size: 30px; font-weight: 800; color: var(--text-primary); letter-spacing: -.6px; line-height: 1; }
    .kpi-foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; min-height: 30px; }
    .kpi-sub { font-size: 12.5px; color: var(--text-muted); }

    .delta {
        display: inline-flex; align-items: center; gap: 3px;
        font-size: 12px; font-weight: 700; padding: 3px 8px; border-radius: 999px;
        background: #f1f5f9; color: var(--text-secondary);
    }
    .delta.up { background: #dcfce7; color: var(--good); }
    .delta.down { background: #fee2e2; color: var(--bad); }

    .spark polyline { fill: none; stroke: var(--neutral); stroke-width: 2; stroke-linejoin: round; stroke-linecap: round; }
    .spark circle { fill: var(--accent); stroke: var(--surface-1); stroke-width: 2; }

    .mini-meter { height: 8px; border-radius: 999px; background: var(--grid); display: flex; gap: 2px; overflow: hidden; width: 100%; }
    .mini-meter span { display: block; height: 100%; }

    /* ---------- CARDS ---------- */
    .grid-2 { display: grid; grid-template-columns: 1.65fr 1fr; gap: 18px; }
    .grid-even { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }

    .card {
        background: var(--surface-1);
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        padding: 20px 22px;
        min-width: 0;
        margin-bottom: 0;
        box-shadow: none;
    }
    .card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 18px; }
    .card-head h3 { margin: 0; font-size: 15.5px; font-weight: 750; color: var(--text-primary); }
    .card-head p { margin: 3px 0 0; font-size: 12.5px; color: var(--text-muted); }
    .card-link { font-size: 13px; font-weight: 700; color: var(--accent); text-decoration: none; white-space: nowrap; }
    .card-link:hover { text-decoration: underline; }

    /* Column chart (miezi) */
    .colchart { display: grid; grid-template-columns: 34px 1fr; height: 240px; }
    .colchart-y { position: relative; }
    .colchart-y span {
        position: absolute; right: 8px; transform: translateY(-50%);
        font-size: 11.5px; color: var(--text-muted); font-variant-numeric: tabular-nums;
    }
    .colchart-plot { position: relative; display: flex; flex-direction: column; }
    .colchart-area { position: relative; flex: 1; border-bottom: 1px solid #cbd5e1; }
    .colchart-grid { position: absolute; left: 0; right: 0; height: 1px; background: var(--grid); }
    .colchart-cols { position: absolute; inset: 0; display: flex; }
    .col {
        flex: 1; display: flex; align-items: flex-end; justify-content: center;
        cursor: default; position: relative;
    }
    .col:hover { background: rgba(22,163,74,.05); }
    .col-bar {
        width: min(24px, 62%);
        background: var(--accent);
        border-radius: 4px 4px 0 0;
        min-height: 0;
        transition: filter .15s;
    }
    .col-bar.muted { background: #86efac; }
    .col:hover .col-bar { filter: brightness(.92); }
    .col-val {
        position: absolute; font-size: 11.5px; font-weight: 700; color: var(--text-primary);
        transform: translateY(-18px);
    }
    .colchart-x { display: flex; padding-top: 8px; }
    .colchart-x span { flex: 1; text-align: center; font-size: 11.5px; color: var(--text-muted); }
    .colchart-x span.current { color: var(--text-primary); font-weight: 700; }

    /* Gender segmented bar */
    .seg { display: flex; gap: 2px; height: 14px; border-radius: 999px; overflow: hidden; background: var(--grid); margin: 6px 0 16px; }
    .seg span { display: block; height: 100%; }
    .legend { display: flex; flex-direction: column; gap: 12px; }
    .legend-row { display: flex; align-items: center; gap: 10px; font-size: 13.5px; color: var(--text-secondary); }
    .legend-row b { margin-left: auto; color: var(--text-primary); font-variant-numeric: tabular-nums; }
    .legend-row small { color: var(--text-muted); width: 42px; text-align: right; font-variant-numeric: tabular-nums; }
    .swatch { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }

    .stat-pair { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--grid); }
    .stat-pair div { background: #f8fafc; border-radius: 12px; padding: 12px; }
    .stat-pair small { display: block; font-size: 12px; color: var(--text-muted); margin-bottom: 4px; }
    .stat-pair b { font-size: 19px; color: var(--text-primary); }

    /* Horizontal bars (mikoa) */
    .hbars { display: flex; flex-direction: column; gap: 14px; }
    .hbar { display: grid; grid-template-columns: 120px 1fr; align-items: center; gap: 12px; }
    .hbar-name { font-size: 13px; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .hbar-track { display: flex; align-items: center; gap: 8px; }
    .hbar-fill { height: 14px; background: var(--accent); border-radius: 0 4px 4px 0; min-width: 2px; }
    .hbar-val { font-size: 12.5px; font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; }

    /* Age column chart (fupi) */
    .agechart { display: flex; align-items: flex-end; gap: 10px; height: 170px; border-bottom: 1px solid #cbd5e1; padding-top: 22px; }
    .age-col { flex: 1; height: 100%; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; position: relative; }
    .age-col .col-bar { width: min(24px, 60%); }
    .age-col .col-val { position: static; transform: none; margin-bottom: 5px; }
    .age-x { display: flex; gap: 10px; padding-top: 8px; }
    .age-x span { flex: 1; text-align: center; font-size: 11.5px; color: var(--text-muted); }

    /* Recent table */
    .search-box { position: relative; width: 260px; max-width: 100%; }
    .search-box i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
    .search-box input {
        width: 100%; padding: 9px 12px 9px 34px;
        border: 1px solid #e2e8f0; border-radius: 10px;
        font: inherit; font-size: 13.5px; outline: none; background: #f8fafc;
    }
    .search-box input:focus { border-color: var(--accent); background: #fff; box-shadow: 0 0 0 3px var(--accent-soft); }

    .tbl-wrap { overflow-x: auto; margin: 0 -22px -20px; }
    .tbl { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    .tbl th {
        text-align: left; font-size: 11.5px; text-transform: uppercase; letter-spacing: .6px;
        color: var(--text-muted); font-weight: 700; padding: 10px 22px; background: #f8fafc;
        border-top: 1px solid var(--grid); border-bottom: 1px solid var(--grid);
    }
    .tbl td { padding: 12px 22px; border-bottom: 1px solid var(--grid); color: var(--text-secondary); white-space: nowrap; }
    .tbl tr:last-child td { border-bottom: 0; }
    .tbl tbody tr { transition: background .15s; }
    .tbl tbody tr:hover { background: #f8fafc; }

    .person { display: flex; align-items: center; gap: 11px; }
    .p-avatar {
        width: 36px; height: 36px; border-radius: 10px; overflow: hidden; flex-shrink: 0;
        display: grid; place-items: center; font-weight: 800; font-size: 13px;
        background: var(--accent-soft); color: var(--accent);
    }
    .p-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .person b { display: block; color: var(--text-primary); font-size: 13.5px; }
    .person small { color: var(--text-muted); font-size: 12px; }

    .nin { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12.5px; color: var(--text-primary); letter-spacing: .3px; }

    .pill { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 999px; background: #f1f5f9; color: var(--text-secondary); }
    .pill .swatch { width: 8px; height: 8px; border-radius: 50%; }
    .pill.active { background: #dcfce7; color: #166534; }
    .pill.inactive { background: #f1f5f9; color: #64748b; }

    .row-link {
        display: inline-flex; align-items: center; justify-content: center;
        width: 32px; height: 32px; border-radius: 9px;
        color: var(--text-secondary); text-decoration: none; border: 1px solid #e2e8f0;
    }
    .row-link:hover { color: var(--accent); border-color: var(--accent); background: var(--accent-soft); }

    .empty { text-align: center; padding: 34px 10px; color: var(--text-muted); font-size: 14px; }
    .empty i { display: block; font-size: 30px; margin-bottom: 8px; }

    /* Tooltip */
    .viz-tip {
        position: fixed; z-index: 100; pointer-events: none;
        background: #0f172a; color: #fff;
        font-size: 12.5px; padding: 8px 11px; border-radius: 9px;
        box-shadow: 0 10px 25px -10px rgba(0,0,0,.4);
        opacity: 0; transform: translate(-50%, calc(-100% - 10px));
        transition: opacity .12s; white-space: nowrap;
    }
    .viz-tip.show { opacity: 1; }
    .viz-tip b { display: block; font-size: 14px; }

    .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }

    @media (max-width: 1200px) {
        .kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 1000px) {
        .hero, .grid-2, .grid-even { grid-template-columns: 1fr; }
    }
    @media (max-width: 560px) {
        .kpis { grid-template-columns: 1fr; }
        .hero { padding: 22px; }
        .hero h2 { font-size: 22px; }
        .hbar { grid-template-columns: 90px 1fr; }
        .card { padding: 18px; }
        .tbl-wrap { margin: 0 -18px -18px; }
    }
</style>
@endpush

@section('content')

<div class="dash viz-root">

    {{-- ================= HERO + NIN LOOKUP ================= --}}
    <section class="hero">
        <div>
            <div class="hero-top">
                <h2>{{ $greeting }}, {{ $firstName }} 👋</h2>
                <span class="hero-date"><i class="bi bi-calendar3"></i> {{ now()->format('D, d M Y') }}</span>
            </div>
            <p>{{ $today }} {{ Str::plural('person', $today) }} registered so far today.</p>

            <div class="hero-actions">
                <a href="{{ route('people.create') }}" class="btn-hero light">
                    <i class="bi bi-person-plus-fill"></i> Register person
                </a>
                <a href="{{ route('people.index') }}" class="btn-hero ghost">
                    <i class="bi bi-people"></i> View all records
                </a>
            </div>
        </div>

        <div class="lookup">
            <label for="ninLookup"><span><i class="bi bi-search"></i> Quick lookup</span><small class="lookup-hint" id="lookupHint">Name or NIN</small></label>
            <form class="lookup-row" id="lookupForm" action="{{ route('search') }}" method="GET"
                  data-url="{{ route('search.quick') }}" role="search">
                <input id="ninLookup" name="q" type="search" maxlength="100"
                       placeholder="Type a name or NIN…" autocomplete="off"
                       role="combobox" aria-expanded="false" aria-controls="lookupResult" aria-autocomplete="list">
                <button type="submit" id="lookupBtn" title="Search"><i class="bi bi-arrow-right"></i></button>
            </form>
            <div class="lookup-result" id="lookupResult" aria-live="polite"></div>
        </div>
    </section>

    {{-- ================= KPI TILES ================= --}}
    <section class="kpis">

        <div class="kpi">
            <div class="kpi-top">
                <span class="kpi-label">Total registered</span>
                <span class="kpi-ico accent"><i class="bi bi-people-fill"></i></span>
            </div>
            <div class="kpi-value">{{ number_format($total) }}</div>
            <div class="kpi-foot">
                <span class="kpi-sub">{{ number_format($active) }} active records</span>
                <svg class="spark" width="120" height="32" viewBox="-4 -4 128 38" aria-label="Registrations, last 12 months">
                    <polyline points="{{ $sparkPoints }}"/>
                    <circle cx="120" cy="{{ round(30 - ($monthly->last()['count'] / $sparkMax) * 26, 1) }}" r="4"/>
                </svg>
            </div>
        </div>

        <div class="kpi">
            <div class="kpi-top">
                <span class="kpi-label">Registered this month</span>
                <span class="kpi-ico"><i class="bi bi-calendar-check"></i></span>
            </div>
            <div class="kpi-value">{{ number_format($thisMonth) }}</div>
            <div class="kpi-foot">
                <span class="delta {{ $delta > 0 ? 'up' : ($delta < 0 ? 'down' : '') }}">
                    <i class="bi bi-arrow-{{ $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'right') }}-short"></i>
                    {{ $delta > 0 ? '+' : '' }}{{ $deltaPct !== null ? $deltaPct . '%' : $delta }}
                </span>
                <span class="kpi-sub">vs {{ number_format($lastMonth) }} last month</span>
            </div>
        </div>

        <div class="kpi">
            <div class="kpi-top">
                <span class="kpi-label">Gender split</span>
                <span class="kpi-ico"><i class="bi bi-gender-ambiguous"></i></span>
            </div>
            <div class="kpi-value">{{ $malePct }}<small style="font-size:16px;color:var(--text-muted)">%</small> / {{ $femalePct }}<small style="font-size:16px;color:var(--text-muted)">%</small></div>
            <div class="kpi-foot">
                <div class="mini-meter" title="Male {{ $malePct }}% · Female {{ $femalePct }}%">
                    <span style="width:{{ $malePct }}%;background:var(--series-1)"></span>
                    <span style="width:{{ $femalePct }}%;background:var(--series-2)"></span>
                </div>
            </div>
        </div>

        <div class="kpi">
            <div class="kpi-top">
                <span class="kpi-label">Average age</span>
                <span class="kpi-ico"><i class="bi bi-hourglass-split"></i></span>
            </div>
            <div class="kpi-value">{{ $avgAge }} <small style="font-size:15px;color:var(--text-muted);font-weight:600">years</small></div>
            <div class="kpi-foot">
                <span class="kpi-sub">Across {{ number_format($ageGroups->sum()) }} people with a birth date</span>
            </div>
        </div>

    </section>

    {{-- ================= TREND + GENDER ================= --}}
    <section class="grid-2">

        <div class="card">
            <div class="card-head">
                <div>
                    <h3>Registrations per month</h3>
                    <p>New NIN records over the last 12 months</p>
                </div>
                <span class="pill"><i class="bi bi-graph-up"></i> {{ number_format($monthly->sum('count')) }} in 12 months</span>
            </div>

            <div class="colchart" role="img" aria-label="Column chart of registrations per month">
                <div class="colchart-y">
                    @foreach ($yTicks as $tick)
                        <span style="top: {{ 100 - ($tick / $yMax * 100) }}%; {{ $loop->last ? 'top: calc(100% - 22px)' : '' }}">{{ number_format($tick) }}</span>
                    @endforeach
                </div>
                <div class="colchart-plot">
                    <div class="colchart-area">
                        @foreach ($yTicks as $tick)
                            @unless ($loop->last)
                                <div class="colchart-grid" style="top: {{ 100 - ($tick / $yMax * 100) }}%"></div>
                            @endunless
                        @endforeach

                        <div class="colchart-cols">
                            @foreach ($monthly as $m)
                                <div class="col" data-tip="<b>{{ number_format($m['count']) }}</b>{{ $m['full'] }}">
                                    @if ($loop->last && $m['count'] > 0)
                                        <span class="col-val" style="bottom: {{ $m['count'] / $yMax * 100 }}%">{{ $m['count'] }}</span>
                                    @endif
                                    <div class="col-bar {{ $loop->last ? '' : 'muted' }}" style="height: {{ $m['count'] / $yMax * 100 }}%"></div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="colchart-x">
                        @foreach ($monthly as $m)
                            <span class="{{ $loop->last ? 'current' : '' }}">{{ $m['label'] }}</span>
                        @endforeach
                    </div>
                </div>
            </div>

            <table class="sr-only">
                <caption>Registrations per month</caption>
                @foreach ($monthly as $m)<tr><th>{{ $m['full'] }}</th><td>{{ $m['count'] }}</td></tr>@endforeach
            </table>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <h3>Gender distribution</h3>
                    <p>Share of all registered people</p>
                </div>
            </div>

            <div class="seg" role="img" aria-label="Male {{ $malePct }} percent, female {{ $femalePct }} percent">
                @if ($male)<span style="flex: {{ $male }}; background: var(--series-1)" data-tip="<b>{{ number_format($male) }}</b>Male · {{ $malePct }}%"></span>@endif
                @if ($female)<span style="flex: {{ $female }}; background: var(--series-2)" data-tip="<b>{{ number_format($female) }}</b>Female · {{ $femalePct }}%"></span>@endif
                @if ($otherCount)<span style="flex: {{ $otherCount }}; background: var(--neutral)"></span>@endif
            </div>

            <div class="legend">
                <div class="legend-row"><span class="swatch" style="background:var(--series-1)"></span> Male <b>{{ number_format($male) }}</b><small>{{ $malePct }}%</small></div>
                <div class="legend-row"><span class="swatch" style="background:var(--series-2)"></span> Female <b>{{ number_format($female) }}</b><small>{{ $femalePct }}%</small></div>
            </div>

            <div class="stat-pair">
                <div><small>Today</small><b>{{ number_format($today) }}</b></div>
                <div><small>Active records</small><b>{{ $total ? round($active / $total * 100) : 0 }}%</b></div>
            </div>
        </div>

    </section>

    {{-- ================= REGIONS + AGE ================= --}}
    <section class="grid-even">

        <div class="card">
            <div class="card-head">
                <div>
                    <h3>Top regions of residence</h3>
                    <p>Regions with the most registered people</p>
                </div>
            </div>

            @if ($topRegions->isEmpty())
                <div class="empty"><i class="bi bi-geo-alt"></i>No location data yet.</div>
            @else
                <div class="hbars">
                    @foreach ($topRegions as $name => $count)
                        <div class="hbar" data-tip="<b>{{ number_format($count) }}</b>{{ $name }}">
                            <span class="hbar-name" title="{{ $name }}">{{ $name }}</span>
                            <div class="hbar-track">
                                <div class="hbar-fill" style="width: calc({{ $count / $regionMax * 100 }}% - 40px)"></div>
                                <span class="hbar-val">{{ number_format($count) }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <h3>Age groups</h3>
                    <p>Number of people by age range (years)</p>
                </div>
            </div>

            <div class="agechart" role="img" aria-label="Column chart of people by age group">
                @foreach ($ageGroups as $label => $count)
                    <div class="age-col" data-tip="<b>{{ number_format($count) }}</b>Age {{ $label }}">
                        <span class="col-val">{{ $count }}</span>
                        <div class="col-bar" style="height: calc({{ $count / $ageMax * 100 }}% - 24px); min-height: {{ $count ? 3 : 0 }}px"></div>
                    </div>
                @endforeach
            </div>
            <div class="age-x">
                @foreach ($ageGroups as $label => $count)<span>{{ $label }}</span>@endforeach
            </div>
        </div>

    </section>

    {{-- ================= RECENT ================= --}}
    <section class="card">
        <div class="card-head" style="align-items:center;flex-wrap:wrap">
            <div>
                <h3>Recently registered</h3>
                <p>The latest {{ $recent->count() }} records</p>
            </div>
            <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" id="dashboardSearch" placeholder="Filter by NIN, name, region…">
                </div>
                <a href="{{ route('people.index') }}" class="card-link">View all <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Person</th>
                        <th>NIN</th>
                        <th>Gender</th>
                        <th>Region</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recent as $person)
                        <tr class="person-row">
                            <td>
                                <div class="person">
                                    <x-avatar :person="$person" :size="36" />
                                    <span>
                                        <b>{{ $person->full_name }}</b>
                                        <small>{{ $person->date_of_birth ? $person->date_of_birth->age . ' yrs · ' . $person->date_of_birth->format('d M Y') : '—' }}</small>
                                    </span>
                                </div>
                            </td>
                            <td><span class="nin">{{ $person->nin }}</span></td>
                            <td>
                                <span class="pill">
                                    <span class="swatch" style="background: {{ strtolower($person->gender) === 'male' ? 'var(--series-1)' : 'var(--series-2)' }}"></span>
                                    {{ $person->gender ?? '—' }}
                                </span>
                            </td>
                            <td>{{ $person->region ?: '—' }}</td>
                            <td>
                                <span class="pill {{ $person->status === 'Active' ? 'active' : 'inactive' }}">
                                    <i class="bi bi-{{ $person->status === 'Active' ? 'check-circle-fill' : 'pause-circle' }}"></i>
                                    {{ $person->status ?? '—' }}
                                </span>
                            </td>
                            <td>{{ $person->created_at?->diffForHumans() }}</td>
                            <td>
                                <a href="{{ route('people.show', $person->id) }}" class="row-link" title="View profile">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty"><i class="bi bi-person-x"></i>No registered people yet.</div></td></tr>
                    @endforelse
                    <tr id="noMatch" style="display:none"><td colspan="7"><div class="empty">No matching records.</div></td></tr>
                </tbody>
            </table>
        </div>
    </section>

</div>

<div class="viz-tip" id="vizTip" role="tooltip"></div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ---------- Tooltip ya chati ----------
    const tip = document.getElementById('vizTip');

    document.querySelectorAll('[data-tip]').forEach(function (el) {
        el.addEventListener('mouseenter', function () {
            tip.innerHTML = el.dataset.tip;
            tip.classList.add('show');
        });
        el.addEventListener('mousemove', function (e) {
            tip.style.left = e.clientX + 'px';
            tip.style.top = e.clientY + 'px';
        });
        el.addEventListener('mouseleave', function () {
            tip.classList.remove('show');
        });
    });

    // ---------- Kichujio cha jedwali ----------
    const search = document.getElementById('dashboardSearch');
    const rows = document.querySelectorAll('.person-row');
    const noMatch = document.getElementById('noMatch');

    if (search) {
        search.addEventListener('input', function () {
            const value = this.value.toLowerCase().trim();
            let visible = 0;

            rows.forEach(function (row) {
                const match = row.textContent.toLowerCase().includes(value);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });

            noMatch.style.display = rows.length && !visible ? '' : 'none';
        });
    }

    // ---------- Utafutaji wa haraka: jina au NIN ----------
    const form = document.getElementById('lookupForm');
    const input = document.getElementById('ninLookup');
    const hint = document.getElementById('lookupHint');
    const result = document.getElementById('lookupResult');

    const escape = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    // Weka alama (highlight) kwenye maneno yaliyotafutwa ndani ya jina.
    function highlight(text, query) {
        let html = escape(text);
        query.split(/\s+/).filter((t) => t.length > 1 && /\D/.test(t)).forEach(function (term) {
            const safe = escape(term).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            html = html.replace(new RegExp('(' + safe + ')', 'ig'), '<mark>$1</mark>');
        });
        return html;
    }

    let timer = null;
    let controller = null;
    let items = [];
    let active = -1;

    function close() {
        result.className = 'lookup-result';
        input.setAttribute('aria-expanded', 'false');
        active = -1;
    }

    function message(html, isError) {
        result.innerHTML = '<div class="lookup-msg' + (isError ? ' error' : '') + '">' + html + '</div>';
        result.className = 'lookup-result show';
        items = [];
    }

    function setActive(i) {
        items.forEach((el, n) => el.classList.toggle('active', n === i));
        active = i;
        if (items[i]) items[i].scrollIntoView({ block: 'nearest' });
    }

    function render(data, q) {
        if (!data.results.length) {
            const digits = q.replace(/\D/g, '');
            message('<i class="bi bi-person-x"></i> No one found for "' + escape(q) + '"' +
                (digits.length && digits.length !== 20 && !/[a-z]/i.test(q) ? ' · a full NIN has 20 digits (' + digits.length + ' typed)' : ''), false);
            return;
        }

        result.innerHTML = data.results.map(function (p) {
            const avatar = p.photo ? '<img src="' + escape(p.photo) + '" alt="">' : escape(p.initials);
            const meta = [p.gender, p.age !== null ? p.age + ' yrs' : null, p.region].filter(Boolean).map(escape).join(' · ');
            return '<a class="lookup-item" href="' + escape(p.url) + '" role="option">' +
                '<span class="lookup-avatar">' + avatar + '</span>' +
                '<span class="info"><b>' + highlight(p.name, q) +
                (data.exact ? '<span class="lookup-badge">Exact NIN</span>' : '') + '</b>' +
                '<small>' + meta + '</small><span class="nin-sm">' + escape(p.nin) + '</span></span>' +
                '<i class="bi bi-chevron-right go"></i></a>';
        }).join('') +
        (data.total > data.results.length
            ? '<a class="lookup-more" href="' + escape(data.more_url) + '">See all results <i class="bi bi-arrow-right"></i></a>'
            : '');

        result.className = 'lookup-result show';
        input.setAttribute('aria-expanded', 'true');
        items = [...result.querySelectorAll('.lookup-item')];
        setActive(data.exact ? 0 : -1);
    }

    async function runSearch() {
        const q = input.value.trim();

        if (q.length < 2) {
            close();
            hint.textContent = 'Name or NIN';
            return;
        }

        const digits = q.replace(/\D/g, '');
        hint.textContent = /^[\d\s-]+$/.test(q) ? digits.length + ' / 20 digits' : 'Searching names';

        if (controller) controller.abort();
        controller = new AbortController();

        try {
            const res = await fetch(form.dataset.url + '?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal
            });

            if (res.status === 429) throw new Error('Too many searches. Wait a moment and try again.');
            if (!res.ok) throw new Error('Search failed. Please try again.');

            render(await res.json(), q);
        } catch (err) {
            if (err.name !== 'AbortError') message('<i class="bi bi-exclamation-circle"></i> ' + escape(err.message), true);
        }
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(runSearch, 250);
    });

    input.addEventListener('focus', function () {
        if (items.length) { result.className = 'lookup-result show'; input.setAttribute('aria-expanded', 'true'); }
    });

    // Mishale ya juu/chini + Enter + Esc
    input.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown' && items.length) { e.preventDefault(); setActive((active + 1) % items.length); }
        else if (e.key === 'ArrowUp' && items.length) { e.preventDefault(); setActive((active - 1 + items.length) % items.length); }
        else if (e.key === 'Escape') { close(); }
    });

    // Enter: fungua aliyechaguliwa; kama hakuna, nenda ukurasa kamili wa Search.
    form.addEventListener('submit', function (e) {
        if (input.value.trim().length < 2) { e.preventDefault(); input.focus(); return; }
        if (active >= 0 && items[active]) { e.preventDefault(); window.location = items[active].href; }
        else if (items.length === 1) { e.preventDefault(); window.location = items[0].href; }
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.lookup')) close();
    });
});
</script>
@endpush
