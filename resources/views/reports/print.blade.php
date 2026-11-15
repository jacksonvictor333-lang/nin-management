<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registered Persons Report · {{ now()->format('d M Y') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        @page { size: A4 landscape; margin: 14mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; color: #0f172a; margin: 0; padding: 24px; font-size: 11.5px; }
        .head { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 3px solid #15803d; padding-bottom: 12px; margin-bottom: 14px; }
        .head h1 { margin: 0; font-size: 20px; }
        .head p { margin: 4px 0 0; color: #64748b; }
        .meta { text-align: right; color: #475569; line-height: 1.6; }
        .filters { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; }
        .filters span { background: #f1f5f9; border-radius: 999px; padding: 3px 10px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; background: #f0fdf4; color: #14532d; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; padding: 7px 8px; border-bottom: 1.5px solid #bbf7d0; }
        td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        tr:nth-child(even) td { background: #fafafa; }
        .mono { font-family: ui-monospace, Menlo, monospace; font-size: 10.5px; }
        .foot { margin-top: 14px; display: flex; justify-content: space-between; color: #94a3b8; font-size: 10px; }
        .toolbar { position: fixed; top: 16px; right: 16px; display: flex; gap: 8px; }
        .toolbar button { font: inherit; font-weight: 700; padding: 9px 16px; border-radius: 10px; border: 0; cursor: pointer; background: #16a34a; color: #fff; }
        .toolbar button.ghost { background: #fff; color: #334155; border: 1px solid #e5e7eb; }
        @media print {
            body { padding: 0; }
            .toolbar { display: none; }
            th, tr:nth-child(even) td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            thead { display: table-header-group; }
            tr { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="ghost" onclick="window.close()">Close</button>
        <button onclick="window.print()">Print / Save as PDF</button>
    </div>

    <div class="head">
        <div>
            <h1>Registered Persons Report</h1>
            <p>NIN Management System · National Identity Records</p>
        </div>
        <div class="meta">
            <b>{{ number_format($people->count()) }}</b> records<br>
            Generated {{ now()->format('d M Y, H:i') }}<br>
            By {{ auth()->user()->name }}
        </div>
    </div>

    <div class="filters">
        <span>Region: {{ $regionName ?? 'All' }}</span>
        <span>Gender: {{ $f['gender'] ?? 'All' }}</span>
        <span>Status: {{ $f['status'] ?? 'All' }}</span>
        <span>Registered: {{ $f['from'] ? \Carbon\Carbon::parse($f['from'])->format('d M Y') : 'start' }} – {{ $f['to'] ? \Carbon\Carbon::parse($f['to'])->format('d M Y') : 'today' }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th><th>NIN</th><th>Full name</th><th>Gender</th><th>Date of birth</th>
                <th>Region</th><th>District</th><th>Ward / Village</th><th>Phone</th><th>Status</th><th>Registered</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($people as $p)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td class="mono">{{ $p->nin }}</td>
                    <td><b>{{ $p->full_name }}</b></td>
                    <td>{{ $p->gender }}</td>
                    <td>{{ $p->date_of_birth?->format('d/m/Y') }}</td>
                    <td>{{ $p->region ?: '—' }}</td>
                    <td>{{ $p->district ?: '—' }}</td>
                    <td>{{ collect([$p->ward, $p->village])->filter()->implode(' / ') ?: '—' }}</td>
                    <td>{{ $p->phone ?: '—' }}</td>
                    <td>{{ $p->status }}</td>
                    <td>{{ $p->created_at?->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="11" style="text-align:center;padding:30px;color:#94a3b8">No records match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="foot">
        <span>Confidential: contains personal data. Handle according to data protection policy.</span>
        <span>NIN Management System</span>
    </div>
</body>
</html>
