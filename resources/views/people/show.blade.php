@extends('layouts.app')

@section('title', $person->full_name . ' | Person Profile')

@section('page_title', 'Person Profile')

@php
    $dob = $person->date_of_birth;
    $age = $dob ? $dob->age : null;
    $isMale = strtolower((string) $person->gender) === 'male';
    $isActive = ($person->status ?? 'Active') === 'Active';

    $initials = mb_strtoupper(mb_substr($person->first_name, 0, 1) . mb_substr($person->last_name, 0, 1));

    // NIN kwa vikundi ili isomeke kwa urahisi: YYYYMMDD-XXXX-XXXX-XXXX
    $ninGrouped = preg_replace('/^(\d{8})(\d{4})(\d{4})(\d{4})$/', '$1-$2-$3-$4', $person->nin);

    // Ukamilifu wa rekodi (sehemu za hiari zilizojazwa).
    $optional = [
        'middle_name', 'profile_picture', 'birth_region', 'birth_district',
        'region', 'district', 'ward', 'village', 'phone', 'email',
        'father_name', 'mother_name', 'guardian_name',
        'education_level', 'primary_school', 'secondary_school',
    ];
    $filled = collect($optional)->filter(fn ($f) => filled($person->{$f}))->count();
    $completeness = (int) round($filled / count($optional) * 100);

    $residence = array_values(array_filter([$person->region, $person->district, $person->ward, $person->village]));
    $birthPlace = array_values(array_filter([$person->birth_region, $person->birth_district]));

    $family = [
        ['Father', $person->father_name, $person->father_phone, 'bi-person-standing'],
        ['Mother', $person->mother_name, $person->mother_phone, 'bi-person-standing-dress'],
        ['Guardian', $person->guardian_name, $person->guardian_phone, 'bi-shield-check'],
    ];

    $education = [
        ['Primary school', $person->primary_school, $person->primary_completion_year, null, 'bi-backpack'],
        ['Secondary school', $person->secondary_school, $person->secondary_completion_year, null, 'bi-book'],
        ['College / University', $person->higher_institution, null, trim(implode(' · ', array_filter([$person->course, $person->qualification]))), 'bi-bank'],
    ];

    $v = fn ($value) => filled($value) ? e($value) : '<span class="pf-empty">Not provided</span>';

    // Kuhariri kunategemea jukumu na hatua ya idhini (iliyowasilishwa / iliyokataliwa imefungwa).
    $canEdit = $person->canBeEditedBy(auth()->user());
    $approved = $person->isApproved();
    $reviews = $reviews ?? collect();
    $matches = $matches ?? collect();
    $isAdmin = (auth()->user()->role ?? 'Administrator') === 'Administrator';
    $history = $history ?? collect();
@endphp

@push('styles')
<style>
    .pf, .pe-backdrop {
        --accent: #16a34a;
        --accent-dark: #15803d;
        --accent-soft: #dcfce7;
        --ink: #0f172a;
        --text: #334155;
        --muted: #94a3b8;
        --line: #e5e7eb;
        --male: #2a78d6;
        --female: #eb6834;
    }

    .pf { display: flex; flex-direction: column; gap: 20px; }

    /* ---------- HEADER ---------- */
    .pf-hero { background: #fff; border: 1px solid var(--line); border-radius: 20px; overflow: hidden; }

    .pf-cover {
        height: 120px;
        background:
            radial-gradient(circle at 80% -40%, rgba(255,255,255,.22), transparent 50%),
            radial-gradient(circle at 15% 140%, rgba(134,239,172,.35), transparent 45%),
            linear-gradient(120deg, #14532d, #15803d 55%, #22c55e);
        position: relative;
    }
    .pf-cover-actions { position: absolute; top: 16px; right: 18px; display: flex; gap: 8px; }

    .pf-head { display: flex; align-items: flex-end; gap: 22px; padding: 0 28px 24px; margin-top: -54px; flex-wrap: wrap; }

    .pf-photo {
        width: 128px; height: 128px; border-radius: 26px; flex-shrink: 0; overflow: hidden;
        border: 5px solid #fff; box-shadow: 0 14px 30px -14px rgba(15,23,42,.45);
        background: linear-gradient(135deg, #dcfce7, #86efac); color: var(--accent-dark);
        display: grid; place-items: center; font-size: 40px; font-weight: 800; position: relative;
    }
    .pf-photo img { width: 100%; height: 100%; object-fit: cover; }

    .pf-id { flex: 1; min-width: 260px; padding-bottom: 4px; }
    .pf-id h2 { margin: 0 0 8px; font-size: 26px; font-weight: 800; color: var(--ink); letter-spacing: -.4px; }

    .pf-chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .chip {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 12.5px; font-weight: 600; color: var(--text);
        background: #f1f5f9; padding: 5px 11px; border-radius: 999px;
    }
    .chip i { color: var(--muted); }
    .chip.status-active { background: #dcfce7; color: #166534; }
    .chip.status-active i { color: #16a34a; }
    .chip.status-inactive { background: #fee2e2; color: #991b1b; }
    .chip .dot { width: 8px; height: 8px; border-radius: 50%; }

    .pf-nin {
        display: flex; align-items: center; gap: 14px;
        background: linear-gradient(120deg, #052e16, #14532d);
        color: #fff; border-radius: 16px; padding: 14px 16px 14px 18px;
        min-width: 300px;
    }
    .pf-nin > i { font-size: 28px; opacity: .85; }
    .pf-nin small.nin-ref { margin: 4px 0 0; text-transform: none; letter-spacing: .3px; font-size: 11.5px; opacity: .75; }
    .pf-nin .nin-pending { font-family: inherit; font-size: 15px; letter-spacing: 0; opacity: .9; }
    .idc.is-preview { filter: saturate(.55); }
    .idc-preview { position: absolute; z-index: 5; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-14deg); padding: 6px 16px; border: 2px dashed #b45309; border-radius: 8px; color: #92400e; background: rgba(255, 251, 235, .92); font-weight: 800; font-size: 13px; letter-spacing: .5px; text-transform: uppercase; white-space: nowrap; pointer-events: none; }
    .pf-nin small { display: block; font-size: 11px; letter-spacing: 1px; text-transform: uppercase; opacity: .7; margin-bottom: 2px; }
    .pf-nin b { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 17px; letter-spacing: 1px; white-space: nowrap; }
    .copy-btn {
        margin-left: auto; width: 38px; height: 38px; border-radius: 11px; flex-shrink: 0;
        border: 1px solid rgba(255,255,255,.25); background: rgba(255,255,255,.1); color: #fff;
        cursor: pointer; font-size: 16px; transition: background .15s;
    }
    .copy-btn:hover { background: rgba(255,255,255,.2); }

    .btn-pf {
        display: inline-flex; align-items: center; gap: 7px;
        height: 38px; padding: 0 14px; border-radius: 11px;
        font: inherit; font-size: 13.5px; font-weight: 700; text-decoration: none; cursor: pointer;
        border: 1px solid rgba(255,255,255,.35); background: rgba(255,255,255,.12); color: #fff;
        backdrop-filter: blur(4px); transition: background .15s;
    }
    .btn-pf:hover { background: rgba(255,255,255,.22); }
    .btn-pf.solid { background: #fff; color: var(--accent-dark); border-color: #fff; }

    /* Quick facts strip */
    .pf-facts { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); border-top: 1px solid var(--line); }
    .fact { padding: 16px 24px; display: flex; align-items: center; gap: 12px; }
    .fact + .fact { border-left: 1px solid var(--line); }
    .fact-ico { width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; background: #f1f5f9; color: var(--text); font-size: 16px; flex-shrink: 0; }
    .fact small { display: block; font-size: 12px; color: var(--muted); }
    .fact b { font-size: 14.5px; color: var(--ink); }

    .meter { height: 6px; border-radius: 999px; background: #dcfce7; margin-top: 6px; overflow: hidden; width: 120px; }
    .meter span { display: block; height: 100%; background: var(--accent); border-radius: 999px; }

    /* ---------- BODY ---------- */
    .pf-grid { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(0, 1fr); gap: 20px; align-items: start; }
    .pf-col { display: flex; flex-direction: column; gap: 20px; min-width: 0; }

    .pf-card { background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 22px 24px; }
    .pf-card-head { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
    .pf-card-head .ico { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; background: var(--accent-soft); color: var(--accent); font-size: 18px; }
    .pf-card-head h3 { margin: 0; font-size: 15.5px; color: var(--ink); }
    .pf-card-head p { margin: 2px 0 0; font-size: 12.5px; color: var(--muted); }

    .kv { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0; }
    .kv > div { padding: 12px 0; border-bottom: 1px dashed var(--line); min-width: 0; }
    .kv > div:nth-child(odd) { padding-right: 16px; }
    .kv > div:nth-last-child(-n+2) { border-bottom: 0; }
    .kv small { display: block; font-size: 12px; color: var(--muted); margin-bottom: 3px; }
    .kv b { font-size: 14px; color: var(--ink); font-weight: 650; word-break: break-word; }
    .pf-empty { color: #cbd5e1; font-weight: 500; font-style: italic; }

    /* Location breadcrumb */
    .crumbs { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
    .crumb {
        display: inline-flex; flex-direction: column;
        padding: 9px 13px; border-radius: 12px; background: #f8fafc; border: 1px solid var(--line);
    }
    .crumb small { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; }
    .crumb b { font-size: 13.5px; color: var(--ink); }
    .crumb-sep { color: #cbd5e1; }
    .loc-block + .loc-block { margin-top: 18px; padding-top: 18px; border-top: 1px solid var(--line); }
    .loc-title { font-size: 12.5px; font-weight: 700; color: var(--text); margin-bottom: 10px; display: flex; gap: 6px; align-items: center; }
    .loc-title i { color: var(--accent); }

    .contact-row { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 12px; background: #f8fafc; }
    .contact-row + .contact-row { margin-top: 8px; }
    .contact-row .fact-ico { background: #fff; border: 1px solid var(--line); }
    .contact-row small { display: block; font-size: 12px; color: var(--muted); }
    .contact-row b { font-size: 14px; color: var(--ink); word-break: break-all; }
    .contact-row a.act {
        margin-left: auto; width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0;
        display: grid; place-items: center; color: var(--accent); background: var(--accent-soft); text-decoration: none;
    }

    /* Family */
    .fam { display: flex; flex-direction: column; gap: 10px; }
    .fam-item { display: flex; align-items: center; gap: 12px; padding: 12px; border: 1px solid var(--line); border-radius: 14px; }
    .fam-item .fact-ico { background: var(--accent-soft); color: var(--accent); }
    .fam-item.empty .fact-ico { background: #f1f5f9; color: var(--muted); }
    .fam-item small { display: block; font-size: 11.5px; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; }
    .fam-item b { display: block; font-size: 14px; color: var(--ink); }
    .fam-item span.ph { font-size: 12.5px; color: var(--text); }
    .fam-item a.act { margin-left: auto; width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; color: var(--accent); background: var(--accent-soft); text-decoration: none; flex-shrink: 0; }

    /* Education timeline */
    .edu-level { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 700; color: var(--accent-dark); background: var(--accent-soft); padding: 5px 11px; border-radius: 999px; margin-bottom: 16px; }
    .timeline { position: relative; padding-left: 8px; }
    .tl-item { position: relative; display: flex; gap: 14px; padding-bottom: 18px; }
    .tl-item:last-child { padding-bottom: 0; }
    .tl-item::before { content: ''; position: absolute; left: 17px; top: 38px; bottom: 0; width: 2px; background: var(--line); }
    .tl-item:last-child::before { display: none; }
    .tl-dot { width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0; display: grid; place-items: center; background: var(--accent-soft); color: var(--accent); font-size: 15px; border: 3px solid #fff; box-shadow: 0 0 0 1px var(--line); z-index: 1; }
    .tl-item.empty .tl-dot { background: #f1f5f9; color: var(--muted); }
    .tl-item small { display: block; font-size: 11.5px; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; }
    .tl-item b { display: block; font-size: 14px; color: var(--ink); margin: 1px 0; }
    .tl-item .meta { font-size: 12.5px; color: var(--text); }
    .tl-year { margin-left: auto; font-size: 12px; font-weight: 700; color: var(--text); background: #f1f5f9; padding: 3px 9px; border-radius: 8px; height: fit-content; white-space: nowrap; }

    /* Record meta */
    .meta-list { display: flex; flex-direction: column; gap: 12px; font-size: 13px; }
    .meta-list div { display: flex; justify-content: space-between; gap: 10px; }
    .meta-list span { color: var(--muted); }
    .meta-list b { color: var(--ink); text-align: right; }

    .toast {
        position: fixed; left: 50%; bottom: 28px; transform: translate(-50%, 20px);
        background: #0f172a; color: #fff; padding: 10px 16px; border-radius: 12px;
        font-size: 13.5px; font-weight: 600; opacity: 0; transition: all .25s; z-index: 200;
        display: flex; align-items: center; gap: 8px;
    }
    .toast.show { opacity: 1; transform: translate(-50%, 0); }
    .toast i { color: #4ade80; }

    @media (max-width: 1100px) {
        .pf-grid { grid-template-columns: 1fr; }
        .pf-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .fact:nth-child(3) { border-left: 0; }
        .fact:nth-child(n+3) { border-top: 1px solid var(--line); }
    }
    @media (max-width: 640px) {
        .pf-head { padding: 0 18px 20px; flex-direction: column; align-items: flex-start; }
        .pf-nin { min-width: 0; width: 100%; }
        .pf-nin b { font-size: 14px; }
        .pf-facts, .kv { grid-template-columns: 1fr; }
        .fact + .fact { border-left: 0; border-top: 1px solid var(--line); }
        .kv > div { padding-right: 0 !important; }
        .kv > div:nth-last-child(2) { border-bottom: 1px dashed var(--line); }
        .pf-card { padding: 18px; }
        .pf-cover-actions .label { display: none; }
    }

    /* ---------- PHOTO EDITOR ---------- */
    .pf-photo { overflow: visible; }
    .pf-photo img { border-radius: 21px; }
    .photo-edit-btn {
        position: absolute; right: -8px; bottom: -8px;
        width: 40px; height: 40px; border-radius: 50%;
        border: 3px solid #fff; background: var(--accent); color: #fff;
        font-size: 16px; cursor: pointer; display: grid; place-items: center;
        box-shadow: 0 8px 18px -6px rgba(22,163,74,.7); transition: transform .15s, background .15s;
    }
    .photo-edit-btn:hover { transform: scale(1.08); background: var(--accent-dark); }

    .pe-backdrop {
        position: fixed; inset: 0; z-index: 300; background: rgba(15,23,42,.55);
        backdrop-filter: blur(3px); display: none; align-items: center; justify-content: center; padding: 16px;
    }
    .pe-backdrop.open { display: flex; animation: peFade .2s ease; }
    @keyframes peFade { from { opacity: 0; } to { opacity: 1; } }

    .pe {
        width: 100%; max-width: 560px; max-height: calc(100vh - 32px); overflow-y: auto;
        background: #fff; border-radius: 22px; box-shadow: 0 30px 60px -20px rgba(0,0,0,.45);
        animation: peUp .25s ease;
    }
    @keyframes peUp { from { transform: translateY(14px); opacity: 0; } to { transform: none; opacity: 1; } }

    .pe-head { display: flex; align-items: center; gap: 12px; padding: 18px 22px; border-bottom: 1px solid var(--line); }
    .pe-head .ico { width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; background: var(--accent-soft); color: var(--accent); font-size: 17px; }
    .pe-head h3 { margin: 0; font-size: 16px; color: var(--ink); }
    .pe-head p { margin: 1px 0 0; font-size: 12.5px; color: var(--muted); }
    .pe-close { margin-left: auto; width: 34px; height: 34px; border-radius: 10px; border: 1px solid var(--line); background: #fff; cursor: pointer; color: var(--text); font-size: 15px; }
    .pe-close:hover { background: #f1f5f9; }

    .pe-body { padding: 20px 22px; }

    .pe-stage {
        position: relative; width: 300px; height: 300px; max-width: 100%; aspect-ratio: 1; margin: 0 auto;
        border-radius: 20px; overflow: hidden; touch-action: none;
        background:
            repeating-conic-gradient(#f1f5f9 0% 25%, #fff 0% 50%) 50% / 20px 20px;
    }
    .pe-stage canvas { width: 100%; height: 100%; display: block; cursor: grab; }
    .pe-stage canvas.dragging { cursor: grabbing; }
    .pe-stage .grid-overlay {
        position: absolute; inset: 0; pointer-events: none; opacity: 0; transition: opacity .2s;
        background-image:
            linear-gradient(rgba(255,255,255,.6) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,.6) 1px, transparent 1px);
        background-size: 33.33% 33.33%;
        box-shadow: inset 0 0 0 2px rgba(255,255,255,.9);
    }
    .pe-stage.active .grid-overlay { opacity: 1; }

    .pe-drop {
        position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px;
        border: 2px dashed #cbd5e1; border-radius: 20px; background: #f8fafc; color: #64748b;
        font-size: 13.5px; text-align: center; padding: 20px; cursor: pointer; transition: all .2s;
    }
    .pe-drop i { font-size: 34px; color: var(--accent); }
    .pe-drop b { color: var(--ink); font-size: 14.5px; }
    .pe-drop.drag, .pe-drop:hover { border-color: var(--accent); background: #f0fdf4; }
    .pe-stage.has-image .pe-drop { display: none; }
    .pe-stage.drag-over::after {
        content: 'Drop to replace'; position: absolute; inset: 0; display: grid; place-items: center;
        background: rgba(22,163,74,.75); color: #fff; font-weight: 700;
    }

    .pe-hint { text-align: center; font-size: 12px; color: var(--muted); margin: 10px 0 16px; }

    .pe-tools { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; justify-content: center; }
    .pe-zoom { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 200px; }
    .pe-zoom i { color: var(--muted); }
    .pe-zoom input[type=range] { flex: 1; accent-color: var(--accent); }
    .tool-btn {
        width: 38px; height: 38px; border-radius: 11px; border: 1px solid var(--line); background: #fff;
        color: var(--text); cursor: pointer; font-size: 16px; display: grid; place-items: center; transition: all .15s;
    }
    .tool-btn:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); background: #f0fdf4; }
    .tool-btn:disabled { opacity: .4; cursor: not-allowed; }

    .pe-source { display: flex; gap: 8px; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--line); flex-wrap: wrap; }
    .src-btn {
        flex: 1; min-width: 140px; display: inline-flex; align-items: center; justify-content: center; gap: 7px;
        height: 40px; border-radius: 11px; border: 1px solid var(--line); background: #f8fafc;
        font: inherit; font-size: 13px; font-weight: 600; color: var(--text); cursor: pointer;
    }
    .src-btn:hover { border-color: #cbd5e1; background: #f1f5f9; }
    .src-btn.cam { background: var(--accent); border-color: var(--accent); color: #fff; }
    .src-btn.cam:hover { background: var(--accent-dark); border-color: var(--accent-dark); }
    .src-btn.danger { color: #b91c1c; }
    .src-btn.danger:hover { background: #fef2f2; border-color: #fecaca; }

    .pe-meta { font-size: 12px; color: var(--muted); text-align: center; margin-top: 10px; min-height: 16px; }
    .pe-error { color: #b91c1c; font-weight: 600; }

    .pe-foot { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 22px; border-top: 1px solid var(--line); background: #fcfcfd; border-radius: 0 0 22px 22px; }
    .pe-btn { height: 42px; padding: 0 18px; border-radius: 12px; font: inherit; font-size: 14px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; }
    .pe-btn.ghost { background: #fff; border: 1px solid var(--line); color: var(--text); }
    .pe-btn.primary { background: var(--accent); border: 1px solid var(--accent); color: #fff; box-shadow: 0 8px 18px -8px rgba(22,163,74,.7); }
    .pe-btn.primary:disabled { opacity: .5; cursor: not-allowed; box-shadow: none; }

    @media (max-width: 520px) {
        .pe-stage { width: 100%; height: auto; }
        .pe-body { padding: 16px; }
    }

    /* ---------- ID CARD ---------- */
    .idc-wrap .pf-card-head { align-items: center; }
    .idc-print { margin-left: auto; border: 1px solid var(--line) !important; box-shadow: none !important; }
    .idc-print:hover { border-color: #86efac !important; background: #f0fdf4 !important; }
    .idc-stage {
        display: grid; place-items: center; padding: 28px 16px;
        border-radius: 16px; background: #f8fafc;
        background-image: radial-gradient(#e2e8f0 1px, transparent 1px); background-size: 16px 16px;
    }
    .idc {
        position: relative; overflow: hidden;
        width: 100%; max-width: 430px; aspect-ratio: 85.6 / 54;
        border-radius: 18px; padding: 16px 18px 14px;
        display: flex; flex-direction: column; gap: 12px;
        color: #0f172a;
        background:
            radial-gradient(circle at 100% 0%, rgba(187,247,208,.55), transparent 45%),
            radial-gradient(circle at 0% 100%, rgba(254,240,138,.35), transparent 50%),
            linear-gradient(135deg, #f0fdf4, #ecfeff 55%, #f7fee7);
        border: 1px solid #bbf7d0;
        box-shadow: 0 24px 48px -24px rgba(20,83,45,.45);
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    /* Utepe wa rangi za bendera juu ya kadi */
    .idc::before {
        content: ''; position: absolute; left: 0; right: 0; top: 0; height: 5px; z-index: 2;
        background: linear-gradient(90deg, #1eb53a 0 40%, #fcd116 40% 45%, #000 45% 55%, #fcd116 55% 60%, #00a3dd 60% 100%);
    }
    .idc.is-inactive { filter: grayscale(.9); }
    .idc > *:not(.idc-watermark) { position: relative; z-index: 1; }
    /* Ngao ya taifa kwa mbali */
    .idc-watermark {
        position: absolute; z-index: 0; pointer-events: none;
        left: 50%; top: 50%; width: 46%; height: 88%; transform: translate(-50%, -50%);
        background: url('{{ asset('images/coat-of-arms.png') }}') center / contain no-repeat;
        opacity: .13;
    }

    .idc-top { display: flex; align-items: center; gap: 10px; }
    .idc-logo { width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; background: #16a34a; color: #fff; font-size: 16px; }
    .idc-top b { display: block; font-size: 13px; letter-spacing: .4px; text-transform: uppercase; color: #14532d; }
    .idc-top small { font-size: 10.5px; color: #64748b; }
    .idc-status { margin-left: auto; font-size: 10px; font-weight: 700; letter-spacing: .4px; text-transform: uppercase; padding: 3px 9px; border-radius: 999px; background: #dcfce7; color: #166534; border: 1px solid #86efac; }
    .idc.is-inactive .idc-status { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }

    .idc-body { display: flex; gap: 14px; flex: 1; min-height: 0; }
    .idc-photo {
        width: 27%; aspect-ratio: 3 / 4; flex-shrink: 0; border-radius: 10px; overflow: hidden;
        display: grid; place-items: center; font-size: 26px; font-weight: 800; color: #166534;
        background: linear-gradient(160deg, #dcfce7, #bbf7d0); border: 2px solid #16a34a;
    }
    .idc-photo img { width: 100%; height: 100%; object-fit: cover; }

    .idc-info { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 12px; align-content: start; margin: 0; min-width: 0; }
    .idc-info .full { grid-column: 1 / -1; }
    .idc-info dt { font-size: 9px; text-transform: uppercase; letter-spacing: .5px; color: #64748b; }
    .idc-info dd { margin: 1px 0 0; font-size: 12.5px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .idc-info dd.idc-name { font-size: 15px; white-space: normal; line-height: 1.2; color: #14532d; }

    .idc-foot { display: flex; justify-content: space-between; align-items: flex-end; gap: 10px; padding-top: 10px; border-top: 1px dashed #86efac; }
    .idc-foot small { display: block; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; color: #64748b; }
    .idc-foot b { font-family: ui-monospace, Consolas, monospace; font-size: 14px; letter-spacing: .6px; color: #14532d; }
    .idc-foot .right { text-align: right; }
    .idc-foot .right b { font-size: 12px; color: #0f172a; }

    @media (max-width: 460px) {
        .idc { aspect-ratio: auto; }
        .idc-foot b { font-size: 12px; }
    }

    .idc-sign-btn + .idc-print { margin-left: 8px; }
    .idc-qr {
        align-self: flex-end; flex-shrink: 0; width: 70px; height: 70px; padding: 4px;
        background: #fff; border-radius: 8px; border: 1px solid #bbf7d0; display: block;
    }
    .idc-qr svg { width: 100%; height: 100%; display: block; }
    .idc-info .idc-sign dd {
        height: 34px; margin-top: 0; max-width: 190px;
        border-bottom: 1px solid #94a3b8; display: flex; align-items: flex-end;
    }
    .idc-info .idc-sign img { max-height: 36px; max-width: 100%; object-fit: contain; object-position: left bottom; mix-blend-mode: multiply; }

    /* Pad ya kuchora sahihi */
    .sp-pad {
        position: relative; height: 200px; border-radius: 14px; border: 1.5px dashed #cbd5e1; background: #fff;
        touch-action: none; cursor: crosshair; overflow: hidden;
    }
    .sp-pad canvas { position: absolute; inset: 0; width: 100%; height: 100%; }
    .sp-pad .sp-line { position: absolute; left: 24px; right: 24px; bottom: 46px; border-bottom: 1px solid #e2e8f0; pointer-events: none; }
    .sp-pad .sp-x { position: absolute; left: 24px; bottom: 52px; color: #cbd5e1; font-size: 18px; pointer-events: none; }
    .sp-pad .sp-ph { position: absolute; inset: 0; display: grid; place-items: center; color: #94a3b8; font-size: 13.5px; pointer-events: none; }
    .sp-pad.has-ink .sp-ph { display: none; }
    .sp-tools { display: flex; align-items: center; gap: 10px; margin-top: 12px; flex-wrap: wrap; }
    .sp-colors { display: flex; gap: 6px; }
    .sp-colors button { width: 26px; height: 26px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 0 1px #cbd5e1; cursor: pointer; }
    .sp-colors button.on { box-shadow: 0 0 0 2px var(--accent); }
    .sp-tools .grow { flex: 1; }

    /* Chapisha kadi pekee */
    @media print {
        body.print-card .pf > *:not(.idc-wrap) { display: none !important; }
        body.print-card .idc-wrap { border: 0 !important; padding: 0 !important; }
        body.print-card .idc-wrap .pf-card-head { display: none !important; }
        body.print-card .idc-stage { background: none !important; padding: 0 !important; place-items: start; }
        body.print-card .idc { width: 430px; box-shadow: none; }
    }

    /* ---------- PRINT ---------- */
    @media print {
        .sidebar, .topbar, .overlay, .no-print, .success, .alert-error { display: none !important; }
        .shell { margin: 0 !important; padding: 0 !important; }
        .container { padding: 0 !important; max-width: none !important; }
        body { background: #fff !important; }
        .pf-grid { grid-template-columns: 1.5fr 1fr; }
        .pf-card, .pf-hero { break-inside: avoid; box-shadow: none; }
        .pf-cover { height: 70px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .pf-nin, .tl-dot, .chip, .fact-ico { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
@endpush

@section('content')

<div class="pf">

    {{-- ================= HEADER ================= --}}
    <section class="pf-hero">
        <div class="pf-cover">
            <div class="pf-cover-actions no-print">
                <a href="{{ route('people.index') }}" class="btn-pf"><i class="bi bi-arrow-left"></i><span class="label">All records</span></a>
                @if ($canEdit)
                    <a href="{{ route('people.edit', $person) }}" class="btn-pf"><i class="bi bi-pencil-square"></i><span class="label">Edit details</span></a>
                @endif
                @if ($isAdmin && $approved && ! $person->isDeactivated())
                    <button type="button" class="btn-pf" onclick="document.getElementById('deactivateDialog').showModal()"><i class="bi bi-slash-circle"></i><span class="label">Deactivate NIN</span></button>
                @endif
                @if ($isAdmin)
                    <button type="button" class="btn-pf" onclick="document.getElementById('archiveDialog').showModal()"><i class="bi bi-archive"></i><span class="label">Archive</span></button>
                @endif
                <button type="button" class="btn-pf solid" onclick="window.print()"><i class="bi bi-printer"></i><span class="label">Print</span></button>
            </div>
        </div>

        <div class="pf-head">
            <div class="pf-photo">
                @if ($person->profile_picture && Storage::disk('public')->exists($person->profile_picture))
                    <img src="{{ asset('storage/' . $person->profile_picture) }}?v={{ $person->updated_at?->timestamp }}" alt="{{ $person->full_name }}">
                @else
                    {{ $initials }}
                @endif
                @if ($canEdit)
                <button type="button" class="photo-edit-btn no-print" id="openPhotoEditor" title="Edit photo">
                    <i class="bi bi-camera-fill"></i>
                </button>
                @endif
            </div>

            <div class="pf-id">
                <h2>{{ $person->full_name }}</h2>
                <div class="pf-chips">
                    <span class="chip {{ $isActive ? 'status-active' : 'status-inactive' }}">
                        <i class="bi bi-{{ $isActive ? 'patch-check-fill' : 'pause-circle-fill' }}"></i>
                        {{ $person->status ?? 'Active' }}
                    </span>
                    @if ($person->gender)
                        <span class="chip"><span class="dot" style="background: var({{ $isMale ? '--male' : '--female' }})"></span>{{ $person->gender }}</span>
                    @endif
                    @if ($age !== null)
                        <span class="chip"><i class="bi bi-cake2"></i>{{ $age }} years</span>
                    @endif
                    @if ($person->nationality)
                        <span class="chip"><i class="bi bi-flag"></i>{{ $person->nationality }}</span>
                    @endif
                </div>
            </div>

            <div class="pf-nin {{ $person->nin ? '' : 'pending' }}">
                <i class="bi bi-fingerprint"></i>
                <div>
                    <small>National Identification Number</small>
                    @if ($person->nin)
                        <b>{{ $ninGrouped }}</b>
                    @else
                        <b class="nin-pending">Issued on approval</b>
                    @endif
                    @if ($person->reference)<small class="nin-ref" title="Application number — the applicant uses it to check status">Application {{ $person->reference }}</small>@endif
                </div>
                @if ($person->nin)
                <button type="button" class="copy-btn no-print" id="copyNin" data-nin="{{ $person->nin }}" title="Copy NIN">
                    <i class="bi bi-copy"></i>
                </button>
                @endif
            </div>
        </div>

        <div class="pf-facts">
            <div class="fact">
                <span class="fact-ico"><i class="bi bi-calendar-event"></i></span>
                <div><small>Date of birth</small><b>{{ $dob ? $dob->format('d M Y') : '—' }}</b></div>
            </div>
            <div class="fact">
                <span class="fact-ico"><i class="bi bi-geo-alt"></i></span>
                <div><small>Lives in</small><b>{{ $person->district ? $person->district . ', ' : '' }}{{ $person->region ?: '—' }}</b></div>
            </div>
            <div class="fact">
                <span class="fact-ico"><i class="bi bi-clock-history"></i></span>
                <div><small>Registered</small><b title="{{ $person->created_at?->format('d M Y H:i') }}">{{ $person->created_at?->format('d M Y') ?? '—' }}</b></div>
            </div>
            <div class="fact">
                <span class="fact-ico"><i class="bi bi-clipboard2-check"></i></span>
                <div>
                    <small>Profile completeness</small>
                    <b>{{ $completeness }}%</b>
                    <div class="meter"><span style="width: {{ $completeness }}%"></span></div>
                </div>
            </div>
        </div>
    </section>

    @include('partials.deactivation')

    {{-- ================= IDHINI ================= --}}
    @if (! $approved || $reviews->isNotEmpty())
        @include('partials.approval')
    @endif

    {{-- ================= BODY ================= --}}
    <div class="pf-grid">

        <div class="pf-col">

            {{-- Personal --}}
            <section class="pf-card">
                <div class="pf-card-head">
                    <span class="ico"><i class="bi bi-person-vcard"></i></span>
                    <div><h3>Personal information</h3><p>Core identity details</p></div>
                </div>
                <div class="kv">
                    <div><small>First name</small><b>{!! $v($person->first_name) !!}</b></div>
                    <div><small>Middle name</small><b>{!! $v($person->middle_name) !!}</b></div>
                    <div><small>Last name</small><b>{!! $v($person->last_name) !!}</b></div>
                    <div><small>Gender</small><b>{!! $v($person->gender) !!}</b></div>
                    <div><small>Date of birth</small><b>{!! $v($dob?->format('d F Y')) !!}</b></div>
                    <div><small>Nationality</small><b>{!! $v($person->nationality) !!}</b></div>
                </div>
            </section>

            {{-- Location --}}
            <section class="pf-card">
                <div class="pf-card-head">
                    <span class="ico"><i class="bi bi-geo-alt"></i></span>
                    <div><h3>Location</h3><p>Place of birth and current residence</p></div>
                </div>

                <div class="loc-block">
                    <div class="loc-title"><i class="bi bi-hospital"></i> Place of birth</div>
                    @if ($birthPlace)
                        <div class="crumbs">
                            @foreach (['Region' => $person->birth_region, 'District' => $person->birth_district] as $label => $value)
                                @if ($value)
                                    @if (!$loop->first)<i class="bi bi-chevron-right crumb-sep"></i>@endif
                                    <span class="crumb"><small>{{ $label }}</small><b>{{ $value }}</b></span>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <span class="pf-empty">Not provided</span>
                    @endif
                </div>

                <div class="loc-block">
                    <div class="loc-title"><i class="bi bi-house-door"></i> Current residence</div>
                    @if ($residence)
                        <div class="crumbs">
                            @php $shown = 0; @endphp
                            @foreach (['Region' => $person->region, 'District' => $person->district, 'Ward' => $person->ward, 'Village / Street' => $person->village] as $label => $value)
                                @if ($value)
                                    @if ($shown++)<i class="bi bi-chevron-right crumb-sep"></i>@endif
                                    <span class="crumb"><small>{{ $label }}</small><b>{{ $value }}</b></span>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <span class="pf-empty">Not provided</span>
                    @endif
                </div>
            </section>

            {{-- Education --}}
            <section class="pf-card">
                <div class="pf-card-head">
                    <span class="ico"><i class="bi bi-mortarboard"></i></span>
                    <div><h3>Education</h3><p>Schools attended and qualifications</p></div>
                </div>

                @if ($person->education_level)
                    <span class="edu-level"><i class="bi bi-award"></i> Highest level: {{ $person->education_level }}</span>
                @endif

                <div class="timeline">
                    @foreach ($education as [$label, $school, $year, $extra, $icon])
                        <div class="tl-item {{ $school ? '' : 'empty' }}">
                            <span class="tl-dot"><i class="bi {{ $icon }}"></i></span>
                            <div>
                                <small>{{ $label }}</small>
                                <b>{!! $v($school) !!}</b>
                                @if ($extra)<span class="meta">{{ $extra }}</span>@endif
                            </div>
                            @if ($year)<span class="tl-year">{{ $year }}</span>@endif
                        </div>
                    @endforeach
                </div>
            </section>

        </div>

        <div class="pf-col">

            {{-- Contact --}}
            <section class="pf-card">
                <div class="pf-card-head">
                    <span class="ico"><i class="bi bi-telephone"></i></span>
                    <div><h3>Contact</h3><p>How to reach this person</p></div>
                </div>

                <div class="contact-row">
                    <span class="fact-ico"><i class="bi bi-phone"></i></span>
                    <div><small>Phone</small><b>{!! $v($person->phone) !!}</b></div>
                    @if ($person->phone)<a class="act no-print" href="tel:{{ preg_replace('/[^\d+]/', '', $person->phone) }}" title="Call"><i class="bi bi-telephone-outbound"></i></a>@endif
                </div>
                <div class="contact-row">
                    <span class="fact-ico"><i class="bi bi-envelope"></i></span>
                    <div><small>Email</small><b>{!! $v($person->email) !!}</b></div>
                    @if ($person->email)<a class="act no-print" href="mailto:{{ $person->email }}" title="Send email"><i class="bi bi-send"></i></a>@endif
                </div>
            </section>

            {{-- Family --}}
            <section class="pf-card">
                <div class="pf-card-head">
                    <span class="ico"><i class="bi bi-people"></i></span>
                    <div><h3>Family</h3><p>Parents and guardian</p></div>
                </div>

                <div class="fam">
                    @foreach ($family as [$role, $name, $phone, $icon])
                        <div class="fam-item {{ $name ? '' : 'empty' }}">
                            <span class="fact-ico"><i class="bi {{ $icon }}"></i></span>
                            <div>
                                <small>{{ $role }}</small>
                                <b>{!! $v($name) !!}</b>
                                @if ($phone)<span class="ph"><i class="bi bi-phone"></i> {{ $phone }}</span>@endif
                            </div>
                            @if ($phone)<a class="act no-print" href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}" title="Call {{ strtolower($role) }}"><i class="bi bi-telephone-outbound"></i></a>@endif
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Record --}}
            <section class="pf-card">
                <div class="pf-card-head">
                    <span class="ico"><i class="bi bi-database"></i></span>
                    <div><h3>Record details</h3><p>System information</p></div>
                </div>
                <div class="meta-list">
                    <div><span>Record ID</span><b>#{{ str_pad($person->id, 6, '0', STR_PAD_LEFT) }}</b></div>
                    <div><span>Status</span><b>{{ $person->status ?? 'Active' }}</b></div>
                    <div><span>Created</span><b>{{ $person->created_at?->format('d M Y, H:i') ?? '—' }}</b></div>
                    <div><span>Last updated</span><b>{{ $person->updated_at?->diffForHumans() ?? '—' }}</b></div>
                    <div><span>Printed / viewed</span><b>{{ now()->format('d M Y, H:i') }}</b></div>
                </div>
            </section>

            {{-- History --}}
            <section class="pf-card no-print">
                <div class="pf-card-head">
                    <span class="ico"><i class="bi bi-clock-history"></i></span>
                    <div><h3>Change history</h3><p>Recent actions on this record</p></div>
                </div>
                @forelse ($history as $log)
                    @php [$hIcon, $hFg, $hBg] = $log->presentation(); @endphp
                    <div style="display:flex;gap:10px;padding:9px 0;border-bottom:1px dashed var(--line);font-size:13px">
                        <i class="bi {{ $hIcon }}" style="width:28px;height:28px;border-radius:8px;display:grid;place-items:center;background:{{ $hBg }};color:{{ $hFg }};flex-shrink:0"></i>
                        <span style="color:var(--text);flex:1;min-width:0">{{ ! empty($log->properties['changes']) ? 'Updated details' : $log->description }}
                            <small style="display:block;color:var(--muted);font-size:11.5px">{{ $log->user?->name ?? 'System' }} · {{ $log->created_at->diffForHumans() }}</small>
                            @include('partials.changes', ['log' => $log, 'limit' => 8])
                        </span>
                    </div>
                @empty
                    <p style="margin:0;font-size:13px;color:var(--muted)">No recorded changes yet. Changes made from now on will appear here.</p>
                @endforelse
            </section>

        </div>
    </div>

    {{-- ================= NYARAKA ================= --}}
    @include('partials.documents')

    {{-- ================= KITAMBULISHO (ID CARD) ================= --}}
    <section class="pf-card idc-wrap" id="id-card">
        <div class="pf-card-head">
            <span class="ico"><i class="bi bi-person-vcard"></i></span>
            <div><h3>Identity card</h3><p>Summary of this record in card form</p></div>
            @if ($canEdit)
            <button type="button" class="btn-pf solid idc-print idc-sign-btn no-print" id="openSignPad"><i class="bi bi-pen"></i><span class="label">{{ $person->signature ? 'Change signature' : 'Add signature' }}</span></button>
            @endif
            @if ($canEdit && $approved && ! $person->isDeactivated())
            <a href="{{ route('cards.show', $person) }}" target="_blank" class="btn-pf solid idc-print no-print" id="printCard"><i class="bi bi-printer"></i><span class="label">Print card / PDF</span></a>
            @endif
        </div>

        <div class="idc-stage">
            <div class="idc {{ $isActive ? '' : 'is-inactive' }} {{ $approved ? '' : 'is-preview' }}">
                @unless ($approved)<span class="idc-preview">Preview — not valid until approved</span>@endunless
                <div class="idc-top">
                    <span class="idc-logo"><i class="bi bi-fingerprint"></i></span>
                    <div>
                        <b>NIN Management</b>
                        <small>National Identity Card</small>
                    </div>
                    <span class="idc-status">{{ $person->status ?? 'Active' }}</span>
                </div>

                <div class="idc-body">
                    <div class="idc-photo">
                        @if ($person->profile_picture && Storage::disk('public')->exists($person->profile_picture))
                            <img src="{{ asset('storage/' . $person->profile_picture) }}?v={{ $person->updated_at?->timestamp }}" alt="">
                        @else
                            {{ $initials }}
                        @endif
                    </div>

                    <dl class="idc-info">
                        <div class="full"><dt>Full name</dt><dd class="idc-name">{{ $person->full_name }}</dd></div>
                        <div><dt>Date of birth</dt><dd>{{ $dob ? $dob->format('d/m/Y') : '—' }}</dd></div>
                        <div><dt>Sex</dt><dd>{{ $person->gender ?: '—' }}</dd></div>
                        <div><dt>Nationality</dt><dd>{{ $person->nationality ?: '—' }}</dd></div>
                        <div><dt>Region</dt><dd>{{ $person->region ?: '—' }}</dd></div>
                        <div class="full idc-sign">
                            <dt>Holder's signature</dt>
                            <dd>
                                @if ($person->signature && Storage::disk('public')->exists($person->signature))
                                    <img src="{{ asset('storage/' . $person->signature) }}?v={{ $person->updated_at?->timestamp }}" alt="Signature of {{ $person->full_name }}">
                                @endif
                            </dd>
                        </div>
                    </dl>

                    @isset($qrSvg)
                        <a class="idc-qr" href="{{ $verifyUrl }}" target="_blank" rel="noopener" title="Scan to verify this card">{!! $qrSvg !!}</a>
                    @endisset
                </div>

                <div class="idc-foot">
                    <div>
                        <small>NIN</small>
                        <b>{{ $person->nin ? $ninGrouped : '—' }}</b>
                    </div>
                    <div class="right">
                        <small>Issued</small>
                        <b>{{ $approved ? (($person->reviewed_at ?? $person->created_at)?->format('d/m/Y') ?? '—') : '—' }}</b>
                    </div>
                </div>
                <span class="idc-watermark" aria-hidden="true"></span>
            </div>
        </div>

        @include('partials.card-lifecycle')
    </section>
</div>

<div class="toast" id="toast"><i class="bi bi-check-circle-fill"></i> <span>NIN copied to clipboard</span></div>

@if ($isAdmin)
<dialog class="ui-dialog no-print" id="archiveDialog">
    <form method="POST" action="{{ route('people.destroy', $person) }}">
        @csrf @method('DELETE')
        <div class="ui-dialog-head">
            <span class="ico" style="width:36px;height:36px;border-radius:10px;display:grid;place-items:center;background:#fef3c7;color:#b45309"><i class="bi bi-archive"></i></span>
            <h3>Archive {{ $person->full_name }}?</h3>
            <button type="button" class="ui-btn sm icon" onclick="this.closest('dialog').close()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="ui-dialog-body">
            <p style="margin:0 0 14px;font-size:13.5px;color:#475569">The record will be hidden from the register, search, the verification API and card QR checks. Nothing is deleted: an administrator can restore it from <b>Archive</b>.</p>
            <div class="ui-field">
                <label for="archive_reason">Reason</label>
                <input class="ui-input {{ $errors->has('archive_reason') ? 'is-invalid' : '' }}" id="archive_reason" name="archive_reason" required minlength="3" maxlength="255" placeholder="e.g. Duplicate of another record, deceased, registered in error" value="{{ old('archive_reason') }}">
                @error('archive_reason')<span class="ui-err">{{ $message }}</span>@enderror
            </div>
        </div>
        <div class="ui-dialog-foot">
            <button type="button" class="ui-btn" onclick="this.closest('dialog').close()">Cancel</button>
            <button class="ui-btn danger"><i class="bi bi-archive"></i> Archive record</button>
        </div>
    </form>
</dialog>
@error('archive_reason')<script>document.addEventListener('DOMContentLoaded', () => document.getElementById('archiveDialog').showModal());</script>@enderror
@endif
{{-- ================= PHOTO EDITOR ================= --}}
<div class="pe-backdrop no-print" id="photoEditor" aria-hidden="true">
    <div class="pe" role="dialog" aria-modal="true" aria-labelledby="peTitle">

        <div class="pe-head">
            <span class="ico"><i class="bi bi-camera"></i></span>
            <div>
                <h3 id="peTitle">Edit profile picture</h3>
                <p>{{ $person->full_name }}</p>
            </div>
            <button type="button" class="pe-close" data-close title="Close"><i class="bi bi-x-lg"></i></button>
        </div>

        <div class="pe-body">
            <div class="pe-stage" id="peStage">
                <canvas id="peCanvas" width="600" height="600"></canvas>
                <div class="grid-overlay"></div>
                <label class="pe-drop" id="peDrop" for="peFile">
                    <i class="bi bi-cloud-arrow-up"></i>
                    <b>Drop a photo here</b>
                    <span>or click to browse · JPG or PNG, max 2 MB</span>
                </label>
            </div>

            <p class="pe-hint" id="peHint">Drag the photo to position it. Use the slider to zoom.</p>

            <div class="pe-tools">
                <button type="button" class="tool-btn" id="peRotL" title="Rotate left"><i class="bi bi-arrow-counterclockwise"></i></button>
                <button type="button" class="tool-btn" id="peRotR" title="Rotate right"><i class="bi bi-arrow-clockwise"></i></button>
                <button type="button" class="tool-btn" id="peFlip" title="Flip horizontally"><i class="bi bi-symmetry-vertical"></i></button>
                <div class="pe-zoom">
                    <i class="bi bi-zoom-out"></i>
                    <input type="range" id="peZoom" min="1" max="4" step="0.01" value="1" aria-label="Zoom">
                    <i class="bi bi-zoom-in"></i>
                </div>
                <button type="button" class="tool-btn" id="peReset" title="Reset"><i class="bi bi-bootstrap-reboot"></i></button>
            </div>

            <div class="pe-meta" id="peMeta"></div>

            <div class="pe-source">
                <button type="button" class="src-btn cam" id="peCamera"><i class="bi bi-camera"></i> Take photo</button>
                <label class="src-btn" for="peFile"><i class="bi bi-upload"></i> Choose new photo</label>
                @if ($person->profile_picture)
                    <button type="button" class="src-btn" id="peUseCurrent"><i class="bi bi-image"></i> Edit current photo</button>
                    <button type="button" class="src-btn danger" id="peRemove"><i class="bi bi-trash3"></i> Remove photo</button>
                @endif
            </div>
            <input type="file" id="peFile" accept="image/jpeg,image/png" hidden>
        </div>

        <div class="pe-foot">
            <button type="button" class="pe-btn ghost" data-close>Cancel</button>
            <button type="button" class="pe-btn primary" id="peSave" disabled><i class="bi bi-check2"></i> Save photo</button>
        </div>
    </div>
</div>

{{-- ================= SIGNATURE PAD ================= --}}
@if ($canEdit)
<div class="pe-backdrop no-print" id="signPad" aria-hidden="true">
    <div class="pe" role="dialog" aria-modal="true" aria-labelledby="spTitle">
        <div class="pe-head">
            <span class="ico"><i class="bi bi-pen"></i></span>
            <div>
                <h3 id="spTitle">Holder's signature</h3>
                <p>{{ $person->full_name }}</p>
            </div>
            <button type="button" class="pe-close" data-close title="Close"><i class="bi bi-x-lg"></i></button>
        </div>

        <div class="pe-body">
            <div class="sp-pad" id="spPad">
                <canvas id="spCanvas"></canvas>
                <span class="sp-x">✕</span>
                <span class="sp-line"></span>
                <span class="sp-ph">Sign here with the mouse, a finger or a stylus</span>
            </div>

            <div class="sp-tools">
                <div class="sp-colors" id="spColors">
                    <button type="button" class="on" data-color="#0f172a" style="background:#0f172a" title="Black"></button>
                    <button type="button" data-color="#1d4ed8" style="background:#1d4ed8" title="Blue"></button>
                </div>
                <button type="button" class="tool-btn" id="spUndo" title="Undo"><i class="bi bi-arrow-counterclockwise"></i></button>
                <button type="button" class="tool-btn" id="spClear" title="Clear"><i class="bi bi-eraser"></i></button>
                <span class="grow"></span>
                <label class="src-btn" for="spFile" style="flex:0 0 auto;min-width:0;padding:0 14px"><i class="bi bi-upload"></i> Upload image</label>
                <input type="file" id="spFile" accept="image/png,image/jpeg" hidden>
            </div>

            @if ($person->signature)
                <div class="pe-source" style="margin-top:12px">
                    <button type="button" class="src-btn danger" id="spRemove"><i class="bi bi-trash3"></i> Remove current signature</button>
                </div>
            @endif
        </div>

        <div class="pe-foot">
            <button type="button" class="pe-btn ghost" data-close>Cancel</button>
            <button type="button" class="pe-btn primary" id="spSave" disabled><i class="bi bi-check2"></i> Save signature</button>
        </div>
    </div>
</div>

<form id="signForm" method="POST" action="{{ route('people.signature.update', $person) }}" enctype="multipart/form-data" hidden>
    @csrf
    @method('PATCH')
    <input type="hidden" name="signature_data" id="spData">
    <input type="file" name="signature_file" id="spFileInput">
</form>
@if ($person->signature)
<form id="signRemoveForm" method="POST" action="{{ route('people.signature.destroy', $person) }}" hidden>
    @csrf
    @method('DELETE')
</form>
@endif
@endif

{{-- Fomu zilizofichwa zinazotumwa na editor --}}
<form id="photoUploadForm" method="POST" action="{{ route('people.photo.update', $person) }}" enctype="multipart/form-data" hidden>
    @csrf
    @method('PATCH')
    <input type="file" name="profile_picture" id="photoUploadInput">
</form>

@if ($person->profile_picture)
    <form id="photoRemoveForm" method="POST" action="{{ route('people.photo.destroy', $person) }}" hidden>
        @csrf
        @method('DELETE')
    </form>
@endif

@if ($canEdit)
    @include('partials.camera')
@endif
@endsection

@push('scripts')
<script>

// ======================================================
// SIGNATURE PAD: chora sahihi, hifadhi kama PNG iliyokatwa
// ======================================================
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('signPad');
    if (!modal) return;

    const pad = document.getElementById('spPad');
    const canvas = document.getElementById('spCanvas');
    const ctx = canvas.getContext('2d');
    const saveBtn = document.getElementById('spSave');
    let strokes = [], current = null, color = '#0f172a', dpr = 1;

    function resize() {
        dpr = window.devicePixelRatio || 1;
        canvas.width = pad.clientWidth * dpr;
        canvas.height = pad.clientHeight * dpr;
        redraw();
    }

    function drawStroke(s) {
        ctx.strokeStyle = s.color;
        ctx.fillStyle = s.color;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        const p = s.points;
        if (p.length === 1) {
            ctx.beginPath(); ctx.arc(p[0].x, p[0].y, 1.4, 0, Math.PI * 2); ctx.fill();
            return;
        }
        // Mstari laini: unene unategemea kasi ya kalamu.
        for (let i = 1; i < p.length; i++) {
            const a = p[i - 1], b = p[i];
            const speed = Math.hypot(b.x - a.x, b.y - a.y);
            ctx.lineWidth = Math.max(1.4, 3.2 - speed * 0.08);
            ctx.beginPath();
            if (i > 1) {
                const z = p[i - 2];
                ctx.moveTo((z.x + a.x) / 2, (z.y + a.y) / 2);
                ctx.quadraticCurveTo(a.x, a.y, (a.x + b.x) / 2, (a.y + b.y) / 2);
            } else {
                ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y);
            }
            ctx.stroke();
        }
    }

    function redraw() {
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        strokes.forEach(drawStroke);
        pad.classList.toggle('has-ink', strokes.length > 0);
        saveBtn.disabled = strokes.length === 0;
    }

    function point(e) {
        const r = canvas.getBoundingClientRect();
        return { x: e.clientX - r.left, y: e.clientY - r.top };
    }

    canvas.addEventListener('pointerdown', (e) => {
        canvas.setPointerCapture(e.pointerId);
        current = { color, points: [point(e)] };
        strokes.push(current);
        redraw();
    });
    canvas.addEventListener('pointermove', (e) => {
        if (!current) return;
        current.points.push(point(e));
        redraw();
    });
    ['pointerup', 'pointercancel'].forEach((ev) => canvas.addEventListener(ev, () => { current = null; }));

    document.getElementById('spUndo').addEventListener('click', () => { strokes.pop(); redraw(); });
    document.getElementById('spClear').addEventListener('click', () => { strokes = []; redraw(); });
    document.querySelectorAll('#spColors button').forEach((b) => b.addEventListener('click', () => {
        document.querySelectorAll('#spColors button').forEach((x) => x.classList.remove('on'));
        b.classList.add('on');
        color = b.dataset.color;
    }));

    // Hifadhi: kata nafasi tupu kuzunguka sahihi, PNG yenye uwazi.
    saveBtn.addEventListener('click', () => {
        const xs = [], ys = [];
        strokes.forEach((s) => s.points.forEach((p) => { xs.push(p.x); ys.push(p.y); }));
        const pad8 = 8;
        const minX = Math.max(0, Math.min(...xs) - pad8), minY = Math.max(0, Math.min(...ys) - pad8);
        const maxX = Math.min(pad.clientWidth, Math.max(...xs) + pad8), maxY = Math.min(pad.clientHeight, Math.max(...ys) + pad8);

        const out = document.createElement('canvas');
        out.width = Math.max(1, Math.round((maxX - minX) * dpr));
        out.height = Math.max(1, Math.round((maxY - minY) * dpr));
        out.getContext('2d').drawImage(canvas, minX * dpr, minY * dpr, out.width, out.height, 0, 0, out.width, out.height);

        document.getElementById('spData').value = out.toDataURL('image/png');
        document.getElementById('spFileInput').value = '';
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving…';
        document.getElementById('signForm').submit();
    });

    // Pakia picha ya sahihi badala ya kuchora.
    document.getElementById('spFile').addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        if (file.size > 1024 * 1024) { alert('The signature image must be 1 MB or smaller.'); this.value = ''; return; }
        const dt = new DataTransfer();
        dt.items.add(file);
        document.getElementById('spFileInput').files = dt.files;
        document.getElementById('spData').value = '';
        document.getElementById('signForm').submit();
    });

    const removeBtn = document.getElementById('spRemove');
    if (removeBtn) {
        removeBtn.addEventListener('click', () => {
            if (confirm('Remove the current signature?')) document.getElementById('signRemoveForm').submit();
        });
    }

    function open() {
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        strokes = [];
        requestAnimationFrame(resize);
    }
    function close() {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    document.getElementById('openSignPad').addEventListener('click', open);
    modal.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', close));
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal.classList.contains('open')) close(); });
    window.addEventListener('resize', () => { if (modal.classList.contains('open')) resize(); });
});

// ======================================================
// PHOTO EDITOR: chagua, sogeza, kuza, zungusha, geuza
// ======================================================
document.addEventListener('DOMContentLoaded', function () {
    const $ = (id) => document.getElementById(id);

    const modal = $('photoEditor');
    const stage = $('peStage');
    const canvas = $('peCanvas');
    const ctx = canvas.getContext('2d');
    const zoomEl = $('peZoom');
    const meta = $('peMeta');
    const saveBtn = $('peSave');
    const fileInput = $('peFile');
    const tools = ['peRotL', 'peRotR', 'peFlip', 'peReset', 'peZoom'].map($);

    const SIZE = canvas.width;              // 600x600 -> picha ya mraba
    const MAX_BYTES = 2 * 1024 * 1024;
    const currentUrl = @json($person->profile_picture ? asset('storage/' . $person->profile_picture) : null);

    let img = null;
    let state = { zoom: 1, rotation: 0, flip: 1, x: 0, y: 0 };
    let dirty = false;

    function open() {
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        if (!img && currentUrl) loadFromUrl(currentUrl, false);
        refreshUi();
    }

    function close() {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    if (!$('openPhotoEditor')) return;
    $('openPhotoEditor').addEventListener('click', open);
    modal.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', close));
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal.classList.contains('open')) close(); });

    // ---------- Kupakia picha ----------
    function loadFromUrl(url, markDirty) {
        const image = new Image();
        image.onload = function () {
            img = image;
            resetState();
            dirty = markDirty;
            meta.textContent = image.naturalWidth + ' × ' + image.naturalHeight + ' px';
            draw();
            refreshUi();
        };
        image.onerror = function () {
            meta.innerHTML = '<span class="pe-error">Could not load the image.</span>';
        };
        image.src = url;
    }

    function loadFile(file) {
        if (!file) return;

        if (!['image/jpeg', 'image/png'].includes(file.type)) {
            meta.innerHTML = '<span class="pe-error">Please choose a JPG or PNG image.</span>';
            return;
        }
        // Picha kubwa inaruhusiwa hapa kwa sababu itapunguzwa hadi 600x600 kabla ya kutumwa.
        if (file.size > 15 * 1024 * 1024) {
            meta.innerHTML = '<span class="pe-error">Image is too large (max 15 MB before editing).</span>';
            return;
        }

        loadFromUrl(URL.createObjectURL(file), true);
    }

    fileInput.addEventListener('change', function () { loadFile(this.files[0]); this.value = ''; });

    // Piga picha kwa kamera, kisha ihariri hapa kama picha nyingine.
    $('peCamera').addEventListener('click', () => NinCamera.capture().then((file) => file && loadFile(file)));

    if ($('peUseCurrent')) {
        $('peUseCurrent').addEventListener('click', () => loadFromUrl(currentUrl + '?t=' + Date.now(), false));
    }

    ['dragenter', 'dragover'].forEach((ev) => stage.addEventListener(ev, (e) => {
        e.preventDefault();
        stage.classList.add('drag-over');
    }));
    ['dragleave', 'drop'].forEach((ev) => stage.addEventListener(ev, (e) => {
        e.preventDefault();
        stage.classList.remove('drag-over');
    }));
    stage.addEventListener('drop', (e) => loadFile(e.dataTransfer.files[0]));

    // ---------- Kuchora ----------
    function baseScale() {
        // Picha ijaze fremu (cover) kwa zoom = 1, ikizingatia mzunguko.
        const rotated = state.rotation % 180 !== 0;
        const w = rotated ? img.naturalHeight : img.naturalWidth;
        const h = rotated ? img.naturalWidth : img.naturalHeight;
        return Math.max(SIZE / w, SIZE / h);
    }

    function clampOffset() {
        const scale = baseScale() * state.zoom;
        const rotated = state.rotation % 180 !== 0;
        const w = (rotated ? img.naturalHeight : img.naturalWidth) * scale;
        const h = (rotated ? img.naturalWidth : img.naturalHeight) * scale;
        const maxX = Math.max(0, (w - SIZE) / 2);
        const maxY = Math.max(0, (h - SIZE) / 2);
        state.x = Math.min(maxX, Math.max(-maxX, state.x));
        state.y = Math.min(maxY, Math.max(-maxY, state.y));
    }

    function draw() {
        ctx.clearRect(0, 0, SIZE, SIZE);
        if (!img) return;

        clampOffset();
        const scale = baseScale() * state.zoom;

        ctx.save();
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, SIZE, SIZE);
        ctx.translate(SIZE / 2 + state.x, SIZE / 2 + state.y);
        ctx.rotate(state.rotation * Math.PI / 180);
        ctx.scale(scale * state.flip, scale);
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(img, -img.naturalWidth / 2, -img.naturalHeight / 2);
        ctx.restore();
    }

    function resetState() {
        state = { zoom: 1, rotation: 0, flip: 1, x: 0, y: 0 };
        zoomEl.value = 1;
    }

    function refreshUi() {
        stage.classList.toggle('has-image', !!img);
        tools.forEach((t) => t.disabled = !img);
        saveBtn.disabled = !img || !dirty;
    }

    function change(fn) {
        if (!img) return;
        fn();
        dirty = true;
        draw();
        refreshUi();
    }

    $('peRotL').addEventListener('click', () => change(() => state.rotation = (state.rotation + 270) % 360));
    $('peRotR').addEventListener('click', () => change(() => state.rotation = (state.rotation + 90) % 360));
    $('peFlip').addEventListener('click', () => change(() => state.flip *= -1));
    $('peReset').addEventListener('click', () => change(resetState));
    zoomEl.addEventListener('input', () => change(() => state.zoom = parseFloat(zoomEl.value)));

    // ---------- Kusogeza (mouse + touch) ----------
    let drag = null;

    canvas.addEventListener('pointerdown', function (e) {
        if (!img) return;
        drag = { x: e.clientX, y: e.clientY, sx: state.x, sy: state.y };
        canvas.setPointerCapture(e.pointerId);
        canvas.classList.add('dragging');
        stage.classList.add('active');
    });

    canvas.addEventListener('pointermove', function (e) {
        if (!drag) return;
        // Badilisha pikseli za skrini kuwa pikseli za canvas.
        const ratio = SIZE / canvas.getBoundingClientRect().width;
        change(() => {
            state.x = drag.sx + (e.clientX - drag.x) * ratio;
            state.y = drag.sy + (e.clientY - drag.y) * ratio;
        });
    });

    ['pointerup', 'pointercancel'].forEach((ev) => canvas.addEventListener(ev, function () {
        drag = null;
        canvas.classList.remove('dragging');
        stage.classList.remove('active');
    }));

    canvas.addEventListener('wheel', function (e) {
        if (!img) return;
        e.preventDefault();
        change(() => {
            state.zoom = Math.min(4, Math.max(1, state.zoom - e.deltaY * 0.0015));
            zoomEl.value = state.zoom;
        });
    }, { passive: false });

    // ---------- Kuhifadhi ----------
    saveBtn.addEventListener('click', function () {
        if (!img) return;

        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving…';

        canvas.toBlob(function (blob) {
            if (!blob || blob.size > MAX_BYTES) {
                meta.innerHTML = '<span class="pe-error">Edited image is larger than 2 MB. Try zooming in.</span>';
                saveBtn.innerHTML = '<i class="bi bi-check2"></i> Save photo';
                refreshUi();
                return;
            }

            const file = new File([blob], 'profile-' + Date.now() + '.jpg', { type: 'image/jpeg' });
            const dt = new DataTransfer();
            dt.items.add(file);
            $('photoUploadInput').files = dt.files;
            $('photoUploadForm').submit();
        }, 'image/jpeg', 0.9);
    });

    if ($('peRemove')) {
        $('peRemove').addEventListener('click', function () {
            if (confirm('Remove this profile picture? This cannot be undone.')) {
                $('photoRemoveForm').submit();
            }
        });
    }

    refreshUi();

    // Kutoka kwenye orodha (kitufe cha kamera): fungua editor moja kwa moja.
    if (location.hash === '#photo') {
        open();
        history.replaceState(null, '', location.pathname);
    }
});

document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('copyNin');
    const toast = document.getElementById('toast');

    btn.addEventListener('click', async function () {
        const nin = this.dataset.nin;

        try {
            await navigator.clipboard.writeText(nin);
        } catch (e) {
            // Njia mbadala kwa vivinjari visivyoruhusu clipboard API (mf. http).
            const ta = document.createElement('textarea');
            ta.value = nin;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            ta.remove();
        }

        btn.innerHTML = '<i class="bi bi-check2"></i>';
        toast.classList.add('show');

        setTimeout(function () {
            btn.innerHTML = '<i class="bi bi-copy"></i>';
            toast.classList.remove('show');
        }, 1800);
    });
});
</script>
@endpush
