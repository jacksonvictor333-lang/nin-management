@extends('layouts.app')

@section('title', 'Reports & Export')
@section('page_title', 'Reports & Export')

@php
    $query = array_filter($f, fn ($v) => filled($v));
    $maxRegion = max($byRegion->max() ?? 0, 1);
    $maxEdu = max($byEducation->max() ?? 0, 1);
@endphp

@push('styles')
<style>
    .rp-filters { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)) auto; gap: 12px; align-items: end; }
    .bars { display: flex; flex-direction: column; gap: 11px; }
    .bar { display: grid; grid-template-columns: 130px 1fr; gap: 12px; align-items: center; font-size: 13px; }
    .bar span.n { color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .bar .track { display: flex; align-items: center; gap: 8px; }
    .bar .fill { height: 12px; border-radius: 0 4px 4px 0; background: #16a34a; min-width: 2px; }
    .bar b { font-size: 12.5px; color: var(--ink); font-variant-numeric: tabular-nums; }
    .export-card { display: flex; align-items: center; gap: 16px; padding: 16px; border: 1px solid var(--border); border-radius: 14px; }
    .export-card + .export-card { margin-top: 10px; }
    .export-card .ico { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; font-size: 20px; flex-shrink: 0; }
    .export-card b { display: block; color: var(--ink); font-size: 14px; }
    .export-card small { color: var(--muted); font-size: 12.5px; }
    .export-card .ui-btn { margin-left: auto; }
    @media (max-width: 1100px) { .rp-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
@endpush

@section('content')
<div class="ui-page">

    <div class="ui-head">
        <div>
            <h2>Reports & export</h2>
            <p>Filter the register, review the summary, then download or print.</p>
        </div>
        @if ((auth()->user()->role ?? 'Administrator') === 'Administrator')
            <div class="ui-head-actions">
                <a href="{{ route('reports.performance') }}" class="ui-btn"><i class="bi bi-speedometer2"></i> Staff performance</a>
            </div>
        @endif
    </div>

    <form class="ui-card" method="GET" action="{{ route('reports.index') }}">
        <div class="rp-filters">
            <div class="ui-field">
                <label for="region">Region</label>
                <select class="ui-select" name="region" id="region">
                    <option value="">All regions</option>
                    @foreach ($regions as $r)
                        <option value="{{ $r->id }}" @selected($f['region'] == $r->id)>{{ $r->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ui-field">
                <label for="gender">Gender</label>
                <select class="ui-select" name="gender" id="gender">
                    <option value="">All</option>
                    <option @selected($f['gender'] === 'Male')>Male</option>
                    <option @selected($f['gender'] === 'Female')>Female</option>
                </select>
            </div>
            <div class="ui-field">
                <label for="status">Status</label>
                <select class="ui-select" name="status" id="status">
                    <option value="">All</option>
                    <option @selected($f['status'] === 'Active')>Active</option>
                    <option @selected($f['status'] === 'Inactive')>Inactive</option>
                </select>
            </div>
            <div class="ui-field">
                <label for="from">Registered from</label>
                <input class="ui-input" type="date" name="from" id="from" value="{{ $f['from'] }}" max="{{ date('Y-m-d') }}">
            </div>
            <div class="ui-field">
                <label for="to">Registered to</label>
                <input class="ui-input" type="date" name="to" id="to" value="{{ $f['to'] }}" max="{{ date('Y-m-d') }}">
            </div>
            <div style="display:flex;gap:8px">
                <button class="ui-btn primary" type="submit"><i class="bi bi-funnel"></i> Apply</button>
                @if ($query)<a href="{{ route('reports.index') }}" class="ui-btn" title="Clear filters"><i class="bi bi-x-lg"></i></a>@endif
            </div>
        </div>
    </form>

    <div class="ui-stats">
        <div class="ui-stat"><span class="ico g"><i class="bi bi-people"></i></span><div><small>Records in report</small><b>{{ number_format($summary['total']) }}</b></div></div>
        <div class="ui-stat"><span class="ico b"><i class="bi bi-gender-male"></i></span><div><small>Male</small><b>{{ number_format($summary['male']) }}</b></div></div>
        <div class="ui-stat"><span class="ico a"><i class="bi bi-gender-female"></i></span><div><small>Female</small><b>{{ number_format($summary['female']) }}</b></div></div>
        <div class="ui-stat"><span class="ico"><i class="bi bi-camera"></i></span><div><small>With photo</small><b>{{ number_format($summary['with_photo']) }}</b></div></div>
    </div>

    <div class="ui-grid-3">
        <div style="display:flex;flex-direction:column;gap:18px;min-width:0">
            <div class="ui-grid-2">
                <section class="ui-card">
                    <div class="ui-card-head"><span class="ico"><i class="bi bi-geo-alt"></i></span><div><h3>By region</h3><p>Top 8 regions of residence</p></div></div>
                    @if ($byRegion->isEmpty())
                        <div class="ui-empty" style="padding:20px">No data</div>
                    @else
                        <div class="bars">
                            @foreach ($byRegion as $name => $count)
                                <div class="bar"><span class="n" title="{{ $name }}">{{ $name }}</span><div class="track"><div class="fill" style="width:calc({{ $count / $maxRegion * 100 }}% - 34px)"></div><b>{{ $count }}</b></div></div>
                            @endforeach
                        </div>
                    @endif
                </section>
                <section class="ui-card">
                    <div class="ui-card-head"><span class="ico"><i class="bi bi-mortarboard"></i></span><div><h3>By education</h3><p>Highest education level</p></div></div>
                    @if ($byEducation->isEmpty())
                        <div class="ui-empty" style="padding:20px">No data</div>
                    @else
                        <div class="bars">
                            @foreach ($byEducation as $name => $count)
                                <div class="bar"><span class="n" title="{{ $name }}">{{ $name }}</span><div class="track"><div class="fill" style="width:calc({{ $count / $maxEdu * 100 }}% - 34px)"></div><b>{{ $count }}</b></div></div>
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>

            <section class="ui-card flush">
                <div class="ui-card-head">
                    <span class="ico"><i class="bi bi-table"></i></span>
                    <div><h3>Preview</h3><p>Latest {{ $preview->count() }} of {{ number_format($summary['total']) }} records. The export contains all of them.</p></div>
                </div>
                @if ($preview->isEmpty())
                    <div class="ui-empty"><i class="bi bi-inbox"></i>No records match these filters.</div>
                @else
                    <div class="ui-table-wrap" style="margin-top:14px">
                        <table class="ui-table">
                            <thead><tr><th>Person</th><th>NIN</th><th>Gender</th><th>Region</th><th>Registered</th></tr></thead>
                            <tbody>
                                @foreach ($preview as $p)
                                    <tr>
                                        <td><div class="ui-who"><x-avatar :person="$p" :size="30" /><b>{{ $p->full_name }}</b></div></td>
                                        <td><span class="ui-mono">{{ $p->nin }}</span></td>
                                        <td>{{ $p->gender }}</td>
                                        <td>{{ $p->region ?: '—' }}</td>
                                        <td>{{ $p->created_at?->format('d M Y') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <section class="ui-card">
            <div class="ui-card-head"><span class="ico"><i class="bi bi-download"></i></span><div><h3>Download</h3><p>Uses the filters above</p></div></div>

            <div class="export-card">
                <span class="ico" style="background:#dcfce7;color:#15803d"><i class="bi bi-filetype-csv"></i></span>
                <div><b>Excel / CSV</b><small>All {{ number_format($summary['total']) }} records, every field</small></div>
                <a href="{{ route('reports.export', $query) }}" class="ui-btn primary sm" @if (!$summary['total']) style="pointer-events:none;opacity:.5" @endif><i class="bi bi-download"></i></a>
            </div>

            <div class="export-card">
                <span class="ico" style="background:#fee2e2;color:#b91c1c"><i class="bi bi-filetype-pdf"></i></span>
                <div><b>Printable / PDF</b><small>Opens a print layout; choose "Save as PDF"</small></div>
                <a href="{{ route('reports.print', $query) }}" target="_blank" class="ui-btn sm" @if (!$summary['total']) style="pointer-events:none;opacity:.5" @endif><i class="bi bi-printer"></i></a>
            </div>

            <p class="ui-help" style="margin:14px 0 0"><i class="bi bi-info-circle"></i> Every export is recorded in the Activity Log.</p>
        </section>
    </div>
</div>
@endsection
