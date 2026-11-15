@extends('layouts.app')

@section('title', 'Backups')
@section('page_title', 'Backups')

@php
    $kb = fn ($b) => $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : number_format(max(1, $b / 1024)) . ' KB';
    $latest = $backups->first();
@endphp

@section('content')
<div class="ui-page">

    <div class="ui-head">
        <div>
            <h2>Backups</h2>
            <p>A backup contains the whole database plus every photo and signature, in one <span class="ui-mono">.tar.gz</span> file.</p>
        </div>
        <div class="ui-head-actions">
            <form method="POST" action="{{ route('backups.store') }}" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerHTML = '<i class=\'bi bi-hourglass-split\'></i> Creating…'">
                @csrf
                <button class="ui-btn primary"><i class="bi bi-database-add"></i> Create backup now</button>
            </form>
        </div>
    </div>

    <div class="ui-stats">
        <div class="ui-stat"><span class="ico g"><i class="bi bi-database-check"></i></span><div><small>Backups kept</small><b>{{ $backups->count() }} <span style="font-size:13px;color:var(--muted);font-weight:600">/ {{ $keep }}</span></b></div></div>
        <div class="ui-stat"><span class="ico {{ $latest && $latest['created_at']->gt(now()->subDay()) ? 'g' : 'a' }}"><i class="bi bi-clock-history"></i></span><div><small>Latest backup</small><b style="font-size:16px">{{ $latest ? $latest['created_at']->diffForHumans() : 'Never' }}</b></div></div>
        <div class="ui-stat"><span class="ico b"><i class="bi bi-hdd"></i></span><div><small>Space used</small><b style="font-size:16px">{{ $kb($backups->sum('size')) }}</b></div></div>
    </div>

    @if (! $latest || $latest['created_at']->lt(now()->subDays(2)))
        <div class="ui-card" style="border-color:#fde68a;background:#fffbeb;display:flex;gap:12px;align-items:flex-start">
            <i class="bi bi-exclamation-triangle-fill" style="color:#d97706;font-size:20px"></i>
            <div style="font-size:13.5px;color:#78350f">
                <b>No recent backup.</b> Create one now, and turn on automatic daily backups (below) so data is not lost if this computer fails.
            </div>
        </div>
    @endif

    <div class="ui-grid-3">
        <section class="ui-card flush">
            <div class="ui-card-head">
                <span class="ico"><i class="bi bi-archive"></i></span>
                <div><h3>Saved backups</h3><p>Newest first · the oldest are removed automatically after {{ $keep }}</p></div>
            </div>

            @if ($backups->isEmpty())
                <div class="ui-empty" style="border-top:1px solid var(--border);margin-top:16px"><i class="bi bi-database"></i>No backups yet.</div>
            @else
                <div class="ui-table-wrap" style="margin-top:16px">
                    <table class="ui-table">
                        <thead><tr><th>File</th><th>Size</th><th>Created</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($backups as $b)
                                <tr>
                                    <td class="ui-mono" style="font-size:12.5px">{{ $b['name'] }}</td>
                                    <td>{{ $kb($b['size']) }}</td>
                                    <td title="{{ $b['created_at']->format('d M Y H:i') }}">{{ $b['created_at']->format('d M Y, H:i') }}</td>
                                    <td style="text-align:right;white-space:nowrap">
                                        <a class="ui-btn sm" href="{{ route('backups.download', $b['name']) }}"><i class="bi bi-download"></i> Download</a>
                                        <form method="POST" action="{{ route('backups.destroy', $b['name']) }}" style="display:inline" onsubmit="return confirm('Delete this backup file?')">
                                            @csrf @method('DELETE')
                                            <button class="ui-btn sm icon danger" title="Delete"><i class="bi bi-trash3"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="ui-card">
            <div class="ui-card-head">
                <span class="ico"><i class="bi bi-info-circle"></i></span>
                <div><h3>Good to know</h3><p>Automatic backups and restoring</p></div>
            </div>
            <div style="font-size:13px;color:var(--text);line-height:1.6">
                <p style="margin:0 0 6px"><b>Automatic daily backup (01:00)</b></p>
                <p style="margin:0 0 10px;color:var(--muted)">In Windows Task Scheduler, create a task that runs every minute:</p>
                <pre class="ui-mono" style="white-space:pre-wrap;background:#0f172a;color:#e2e8f0;padding:10px 12px;border-radius:10px;font-size:11.5px;margin:0 0 14px">{{ PHP_BINARY }} {{ base_path('artisan') }} schedule:run</pre>

                <p style="margin:0 0 6px"><b>Keep a copy elsewhere</b></p>
                <p style="margin:0 0 14px;color:var(--muted)">Backups are stored on this computer (<span class="ui-mono">storage/app/backups</span>). Download one regularly to a flash drive or cloud storage.</p>

                <p style="margin:0 0 6px"><b>Restoring</b></p>
                <ol style="margin:0;padding-left:18px;color:var(--muted)">
                    <li>Extract the file (7-Zip, or <span class="ui-mono">tar -xzf</span>).</li>
                    <li>Import <span class="ui-mono">database.sql</span> into MySQL (phpMyAdmin → Import).</li>
                    <li>Copy the <span class="ui-mono">files</span> folder contents into <span class="ui-mono">storage/app/public</span>.</li>
                </ol>
            </div>
        </section>
    </div>
</div>
@endsection
