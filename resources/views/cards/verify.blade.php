<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ isset($person) ? 'Card verification' : 'Invalid card' }} | NIN Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh; display: grid; place-items: center; padding: 24px 16px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; color: #0f172a;
            background:
                radial-gradient(circle at 10% 0%, rgba(187,247,208,.6), transparent 40%),
                radial-gradient(circle at 100% 100%, rgba(254,240,138,.35), transparent 40%),
                #f8fafc;
        }
        .box { width: 100%; max-width: 400px; background: #fff; border: 1px solid #e5e7eb; border-radius: 22px; overflow: hidden; box-shadow: 0 30px 60px -30px rgba(15,23,42,.35); }
        .flag { height: 5px; background: linear-gradient(90deg, #1eb53a 0 40%, #fcd116 40% 45%, #000 45% 55%, #fcd116 55% 60%, #00a3dd 60% 100%); }
        .head { display: flex; align-items: center; gap: 10px; padding: 16px 20px; border-bottom: 1px solid #f1f5f9; }
        .head .logo { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; background: #16a34a; color: #fff; font-size: 17px; }
        .head b { display: block; font-size: 13.5px; letter-spacing: .3px; text-transform: uppercase; color: #14532d; }
        .head small { font-size: 11.5px; color: #64748b; }

        .verdict { margin: 18px 20px 0; padding: 14px 16px; border-radius: 14px; display: flex; align-items: center; gap: 12px; }
        .verdict i { font-size: 26px; }
        .verdict b { display: block; font-size: 15px; }
        .verdict span { font-size: 12.5px; opacity: .85; }
        .verdict.ok { background: #dcfce7; color: #166534; }
        .verdict.warn { background: #fef3c7; color: #92400e; }
        .verdict.bad { background: #fee2e2; color: #991b1b; }

        .person { display: flex; gap: 16px; align-items: center; padding: 20px; }
        .photo { width: 84px; height: 108px; border-radius: 12px; overflow: hidden; flex-shrink: 0; display: grid; place-items: center; font-size: 28px; font-weight: 800; color: #166534; background: linear-gradient(160deg, #dcfce7, #bbf7d0); border: 2px solid #16a34a; }
        .photo img { width: 100%; height: 100%; object-fit: cover; }
        .person h1 { font-size: 19px; line-height: 1.25; color: #14532d; }
        .person p { font-size: 13px; color: #64748b; margin-top: 4px; }

        dl { margin: 0 20px; border-top: 1px dashed #d1fae5; }
        dl div { display: flex; justify-content: space-between; gap: 12px; padding: 11px 0; border-bottom: 1px dashed #e2e8f0; font-size: 13.5px; }
        dt { color: #64748b; }
        dd { font-weight: 700; text-align: right; }
        dd.mono { font-family: ui-monospace, Consolas, monospace; letter-spacing: .5px; }

        .foot { padding: 16px 20px 18px; font-size: 11.5px; color: #94a3b8; display: flex; gap: 8px; align-items: flex-start; line-height: 1.5; }
        .foot i { color: #16a34a; margin-top: 1px; }
    </style>
</head>
<body>
<main class="box">
    <div class="flag"></div>
    <div class="head">
        <span class="logo"><i class="bi bi-fingerprint"></i></span>
        <div><b>NIN Management</b><small>Identity card verification</small></div>
    </div>

    @isset($person)
        @if ($deactivated ?? null)
            <div class="verdict bad">
                <i class="bi bi-x-octagon-fill"></i>
                <div><b>NIN deactivated — {{ $deactivated }}</b><span>This identity is no longer valid. Do not accept this card.</span></div>
            </div>
        @elseif ($replaced ?? false)
            <div class="verdict bad">
                <i class="bi bi-x-octagon-fill"></i>
                <div><b>Card no longer valid</b><span>This card was reported lost, stolen or damaged and has been replaced. Do not accept it.</span></div>
            </div>
        @elseif ($isActive)
            <div class="verdict ok">
                <i class="bi bi-patch-check-fill"></i>
                <div><b>Valid card</b><span>This card matches an active record.</span></div>
            </div>
        @else
            <div class="verdict warn">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div><b>Record inactive</b><span>The card is genuine but the record is not active.</span></div>
            </div>
        @endif

        <div class="person">
            <div class="photo">
                @if ($photo)<img src="{{ $photo }}" alt="">@else{{ $initials }}@endif
            </div>
            <div>
                <h1>{{ $person->full_name }}</h1>
                <p>{{ $person->gender }}{{ $person->nationality ? ' · ' . $person->nationality : '' }}</p>
            </div>
        </div>

        <dl>
            <div><dt>NIN</dt><dd class="mono">{{ $maskedNin }}</dd></div>
            <div><dt>Status</dt><dd>{{ $person->status ?? 'Active' }}</dd></div>
            <div><dt>Issued</dt><dd>{{ $person->created_at?->format('d M Y') ?? '—' }}</dd></div>
            <div><dt>Checked</dt><dd>{{ now()->format('d M Y, H:i') }}</dd></div>
        </dl>

        <div class="foot">
            <i class="bi bi-shield-lock-fill"></i>
            <span>Only limited details are shown for privacy. Compare the photo and name with the card holder.</span>
        </div>
    @else
        <div class="verdict bad">
            <i class="bi bi-x-octagon-fill"></i>
            <div><b>Card could not be verified</b><span>This QR code is not valid or has been altered.</span></div>
        </div>
        <div class="foot" style="padding-top:18px">
            <i class="bi bi-info-circle-fill" style="color:#94a3b8"></i>
            <span>Do not accept this card without further checks. Scan the original QR code again or contact the issuing office.</span>
        </div>
    @endisset
</main>
</body>
</html>
