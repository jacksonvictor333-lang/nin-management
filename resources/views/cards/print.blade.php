<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $cards->count() === 1 ? 'ID card - ' . $cards->first()['person']->full_name : 'ID cards (' . $cards->count() . ')' }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { font-family: 'Inter', Arial, sans-serif; color: #0f172a; background: #e2e8f0; }

        .toolbar {
            position: sticky; top: 0; z-index: 5; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
            padding: 12px 20px; background: #0f172a; color: #e2e8f0; font-size: 13px;
        }
        .toolbar b { font-size: 14px; color: #fff; }
        .toolbar .sp { flex: 1; }
        .toolbar button, .toolbar a {
            height: 36px; padding: 0 14px; border-radius: 9px; border: 1px solid #334155; background: #1e293b; color: #e2e8f0;
            font: inherit; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; text-decoration: none;
        }
        .toolbar button.primary { background: #16a34a; border-color: #16a34a; color: #fff; }

        .sheet { width: 186mm; margin: 16px auto; background: #fff; padding: 6mm; box-shadow: 0 10px 30px -12px rgba(0,0,0,.35); }
        .row { display: flex; gap: 6mm; justify-content: center; margin-bottom: 6mm; break-inside: avoid; page-break-inside: avoid; }
        .row:last-child { margin-bottom: 0; }

        /* ---- Kadi: 85.6 × 54 mm ---- */
        .card {
            position: relative; overflow: hidden; width: 85.6mm; height: 54mm; border-radius: 3.2mm;
            border: .25mm solid #86efac; padding: 3mm 3.4mm 2.6mm;
            background:
                radial-gradient(circle at 100% 0%, rgba(187,247,208,.65), transparent 45%),
                radial-gradient(circle at 0% 100%, rgba(254,240,138,.4), transparent 50%),
                linear-gradient(135deg, #f0fdf4, #ecfeff 55%, #f7fee7);
            display: flex; flex-direction: column;
        }
        .card::before { content: ''; position: absolute; left: 0; right: 0; top: 0; height: 1.2mm; background: linear-gradient(90deg, #1eb53a 0 40%, #fcd116 40% 45%, #000 45% 55%, #fcd116 55% 60%, #00a3dd 60% 100%); }
        .card.inactive { filter: grayscale(.9); }
        .wm { position: absolute; left: 50%; top: 52%; width: 40mm; height: 46mm; transform: translate(-50%, -50%); background: url('{{ asset('images/coat-of-arms.png') }}') center / contain no-repeat; opacity: .12; }
        .card > *:not(.wm) { position: relative; }

        .top { display: flex; align-items: center; gap: 2mm; margin-top: .6mm; }
        .logo { width: 6mm; height: 6mm; border-radius: 1.6mm; background: #16a34a; color: #fff; display: grid; place-items: center; font-size: 3.4mm; }
        .top b { display: block; font-size: 2.6mm; letter-spacing: .1mm; text-transform: uppercase; color: #14532d; }
        .top small { font-size: 2mm; color: #64748b; }
        .status { margin-left: auto; font-size: 1.9mm; font-weight: 800; letter-spacing: .1mm; text-transform: uppercase; padding: .5mm 1.8mm; border-radius: 5mm; background: #dcfce7; color: #166534; border: .2mm solid #86efac; }

        .body { display: flex; gap: 3mm; margin-top: 2.4mm; flex: 1; min-height: 0; }
        .photo { width: 21mm; height: 27mm; flex-shrink: 0; border-radius: 1.8mm; overflow: hidden; border: .4mm solid #16a34a; background: linear-gradient(160deg, #dcfce7, #bbf7d0); display: grid; place-items: center; font-size: 7mm; font-weight: 800; color: #166534; }
        .photo img { width: 100%; height: 100%; object-fit: cover; }
        dl { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2mm 2.4mm; align-content: start; min-width: 0; flex: 1; }
        dl .full { grid-column: 1 / -1; }
        dt { font-size: 1.7mm; text-transform: uppercase; letter-spacing: .1mm; color: #64748b; }
        dd { font-size: 2.6mm; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        dd.name { font-size: 3.2mm; color: #14532d; white-space: normal; line-height: 1.15; }
        .sig dd { height: 6.4mm; border-bottom: .2mm solid #94a3b8; max-width: 34mm; display: flex; align-items: flex-end; }
        .sig img { max-height: 6.8mm; max-width: 100%; mix-blend-mode: multiply; }

        .foot { display: flex; justify-content: space-between; align-items: flex-end; padding-top: 1.6mm; margin-top: 1.4mm; border-top: .2mm dashed #86efac; }
        .foot small { display: block; font-size: 1.7mm; text-transform: uppercase; letter-spacing: .1mm; color: #64748b; }
        .foot b { font-family: Consolas, 'Courier New', monospace; font-size: 3mm; letter-spacing: .15mm; color: #14532d; }
        .foot .r { text-align: right; }
        .foot .r b { font-size: 2.5mm; color: #0f172a; }

        /* ---- Nyuma ---- */
        .back .bbody { display: flex; gap: 3.4mm; margin-top: 2.6mm; flex: 1; }
        .qr { width: 26mm; height: 26mm; padding: 1mm; background: #fff; border-radius: 1.6mm; border: .25mm solid #bbf7d0; flex-shrink: 0; }
        .qr svg { width: 100%; height: 100%; display: block; }
        .binfo { font-size: 2.2mm; color: #334155; line-height: 1.45; display: flex; flex-direction: column; gap: 1.4mm; min-width: 0; }
        .binfo b { color: #0f172a; }
        .binfo .muted { color: #64748b; font-size: 1.9mm; }
        .bfoot { font-size: 1.8mm; color: #64748b; text-align: center; padding-top: 1.4mm; border-top: .2mm dashed #86efac; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { width: auto; margin: 0; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <b><i class="bi bi-person-vcard"></i> {{ $cards->count() }} {{ Str::plural('card', $cards->count()) }}</b>
    <span>Front and back side by side, actual size (85.6 × 54 mm). Choose <b>Save as PDF</b> in the print dialog for a PDF.</span>
    <span class="sp"></span>
    <a href="{{ url()->previous() }}"><i class="bi bi-arrow-left"></i> Back</a>
    <button class="primary" onclick="window.print()"><i class="bi bi-printer"></i> Print / Save PDF</button>
</div>

<div class="sheet">
    @foreach ($cards as $c)
        @php
            $p = $c['person'];
            $active = ($p->status ?? 'Active') === 'Active';
            $photo = $p->profile_picture && Storage::disk('public')->exists($p->profile_picture) ? asset('storage/' . $p->profile_picture) : null;
            $sign = $p->signature && Storage::disk('public')->exists($p->signature) ? asset('storage/' . $p->signature) : null;
            $initials = mb_strtoupper(mb_substr($p->first_name, 0, 1) . mb_substr($p->last_name, 0, 1));
        @endphp
        <div class="row">
            {{-- MBELE --}}
            <div class="card {{ $active ? '' : 'inactive' }}">
                <span class="wm"></span>
                <div class="top">
                    <span class="logo"><i class="bi bi-fingerprint"></i></span>
                    <div><b>NIN Management</b><small>National Identity Card</small></div>
                    <span class="status">{{ $p->status ?? 'Active' }}</span>
                </div>
                <div class="body">
                    <div class="photo">@if ($photo)<img src="{{ $photo }}" alt="">@else{{ $initials }}@endif</div>
                    <dl>
                        <div class="full"><dt>Full name</dt><dd class="name">{{ $p->full_name }}</dd></div>
                        <div><dt>Date of birth</dt><dd>{{ $p->date_of_birth?->format('d/m/Y') ?? '—' }}</dd></div>
                        <div><dt>Sex</dt><dd>{{ $p->gender ?: '—' }}</dd></div>
                        <div><dt>Nationality</dt><dd>{{ $p->nationality ?: '—' }}</dd></div>
                        <div><dt>Region</dt><dd>{{ $p->region ?: '—' }}</dd></div>
                        <div class="full sig"><dt>Holder's signature</dt><dd>@if ($sign)<img src="{{ $sign }}" alt="">@endif</dd></div>
                    </dl>
                </div>
                <div class="foot">
                    <div><small>NIN</small><b>{{ $c['nin'] }}</b></div>
                    <div class="r"><small>Issued</small><b>{{ $p->created_at?->format('d/m/Y') ?? '—' }}</b></div>
                </div>
            </div>

            {{-- NYUMA --}}
            <div class="card back {{ $active ? '' : 'inactive' }}">
                <span class="wm" style="opacity:.07"></span>
                <div class="top">
                    <span class="logo"><i class="bi bi-qr-code"></i></span>
                    <div><b>Verify this card</b><small>Scan the QR code with any phone camera</small></div>
                </div>
                <div class="bbody">
                    <div class="qr">{!! $c['qr'] !!}</div>
                    <div class="binfo">
                        <div><span class="muted">Residence</span><br><b>{{ implode(', ', array_filter([$p->village, $p->ward, $p->district, $p->region])) ?: '—' }}</b></div>
                        <div><span class="muted">Place of birth</span><br><b>{{ implode(', ', array_filter([$p->birth_district, $p->birth_region])) ?: '—' }}</b></div>
                        <div class="muted">This card remains the property of the issuing office. If found, please return it to the nearest registration office.</div>
                    </div>
                </div>
                <div class="bfoot">Valid only when the QR check shows the same photo and name.</div>
            </div>
        </div>
    @endforeach
</div>

@if (request()->boolean('autoprint'))
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 400));</script>
@endif
</body>
</html>
