@extends('layouts.app')

@section('title', 'Import preview')
@section('page_title', 'Import people')

@push('styles')
<style>
    .row-err td { background: #fef2f2; }
    .row-warn td { background: #fffbeb; }
    .msgs { margin: 4px 0 0; padding: 0; list-style: none; font-size: 12px; }
    .msgs li { display: flex; gap: 6px; }
    .msgs li.e { color: #b91c1c; }
    .msgs li.w { color: #92400e; }
    .confirm-bar { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
    .confirm-bar label { display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: var(--text); cursor: pointer; }
    .confirm-bar label input { width: 16px; height: 16px; accent-color: #d97706; }
</style>
@endpush

@section('content')
<div class="ui-page">
    <div class="ui-head">
        <div>
            <h2>Check before importing</h2>
            <p><span class="ui-mono">{{ $filename }}</span> · {{ count($rows) }} {{ Str::plural('row', count($rows)) }}. Nothing has been saved yet.</p>
        </div>
        <div class="ui-head-actions">
            <a class="ui-btn" href="{{ route('people.import') }}"><i class="bi bi-arrow-left"></i> Upload another file</a>
        </div>
    </div>

    <div class="ui-stats">
        <div class="ui-stat"><span class="ico g"><i class="bi bi-check2-circle"></i></span><div><small>Ready</small><b>{{ $ready }}</b></div></div>
        <div class="ui-stat"><span class="ico a"><i class="bi bi-exclamation-triangle"></i></span><div><small>Possible duplicates</small><b>{{ $warned }}</b></div></div>
        <div class="ui-stat"><span class="ico r"><i class="bi bi-x-octagon"></i></span><div><small>Errors (skipped)</small><b>{{ $failed }}</b></div></div>
    </div>

    <section class="ui-card">
        <form method="POST" action="{{ route('people.import.confirm') }}" class="confirm-bar" id="confirmForm">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            @if ($warned)
                <label><input type="checkbox" name="include_warnings" value="1" id="incWarn"> Also import the {{ $warned }} possible {{ Str::plural('duplicate', $warned) }} (I checked them)</label>
            @endif
            <span style="flex:1"></span>
            @if ($ready + $warned)
                <button class="ui-btn primary" id="importBtn" @disabled($ready === 0)><i class="bi bi-fingerprint"></i> Import <span id="impCount">{{ $ready }}</span> &amp; generate NINs</button>
            @else
                <span class="ui-pill bad">No rows can be imported. Fix the file and upload it again.</span>
            @endif
        </form>
    </section>

    <section class="ui-card flush">
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead><tr><th>Row</th><th>Person</th><th>Born</th><th>Residence</th><th>Result</th></tr></thead>
                <tbody>
                    @foreach ($rows as $r)
                        @php
                            $d = $r['data'];
                            $state = $r['errors'] ? 'err' : ($r['warnings'] ? 'warn' : 'ok');
                            $name = trim(implode(' ', array_filter([$d['first_name'] ?? null, $d['middle_name'] ?? null, $d['last_name'] ?? null])));
                        @endphp
                        <tr class="row-{{ $state }}">
                            <td class="ui-mono">{{ $r['line'] }}</td>
                            <td><b>{{ $name ?: '—' }}</b><br><small style="color:var(--muted)">{{ $d['gender'] ?? '—' }}{{ ! empty($d['phone']) ? ' · ' . $d['phone'] : '' }}</small></td>
                            <td>{{ $d['date_of_birth'] ?? '—' }}</td>
                            <td style="font-size:12.5px">{{ implode(', ', array_filter([$d['village'] ?? null, $d['ward'] ?? null, $d['district'] ?? null, $d['region'] ?? null])) ?: '—' }}</td>
                            <td style="min-width:260px">
                                @if ($state === 'ok')
                                    <span class="ui-pill ok"><i class="bi bi-check2"></i> Ready</span>
                                @else
                                    <span class="ui-pill {{ $state === 'err' ? 'bad' : 'warn' }}">{{ $state === 'err' ? 'Will be skipped' : 'Possible duplicate' }}</span>
                                    <ul class="msgs">
                                        @foreach ($r['errors'] as $m)<li class="e"><i class="bi bi-x-circle"></i>{{ $m }}</li>@endforeach
                                        @foreach ($r['warnings'] as $m)<li class="w"><i class="bi bi-exclamation-triangle"></i>{{ $m }}</li>@endforeach
                                    </ul>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ready = {{ $ready }}, warned = {{ $warned }};
    const btn = document.getElementById('importBtn');
    document.getElementById('incWarn')?.addEventListener('change', function () {
        const n = ready + (this.checked ? warned : 0);
        document.getElementById('impCount').textContent = n;
        if (btn) btn.disabled = n === 0;
    });
    document.getElementById('confirmForm').addEventListener('submit', () => {
        if (!btn) return;
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Importing…';
    });
});
</script>
@endpush
