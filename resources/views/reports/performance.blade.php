@extends('layouts.app')

@section('title', 'Staff performance')
@section('page_title', 'Staff Performance')

@use('App\Http\Controllers\PerformanceController')

@push('styles')
<style>
    .bar { height: 7px; border-radius: 4px; background: #f1f5f9; overflow: hidden; display: flex; min-width: 120px; }
    .bar span { display: block; height: 100%; }
    .bar .a { background: #16a34a; }
    .bar .r { background: #f59e0b; }
    .bar .x { background: #ef4444; }
    .num { font-variant-numeric: tabular-nums; text-align: right; }
    .legend { display: flex; gap: 14px; font-size: 12px; color: var(--muted); }
    .legend i { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 5px; vertical-align: -1px; }
    .q { font-weight: 700; }
    .q.good { color: #15803d; }
    .q.mid { color: #b45309; }
    .q.low { color: #b91c1c; }
</style>
@endpush

@section('content')
<div class="ui-page">

    <div class="ui-head">
        <div>
            <h2>Staff performance</h2>
            <p>Registrations, approval decisions and how long approval takes — per person.</p>
        </div>
        <div class="ui-head-actions">
            <nav class="ui-seg">
                @foreach ($periods as $d => $label)
                    <a href="{{ route('reports.performance', ['days' => $d]) }}" class="{{ $days === $d ? 'on' : '' }}">{{ $label }}</a>
                @endforeach
            </nav>
        </div>
    </div>

    <div class="ui-stats">
        <div class="ui-stat"><span class="ico b"><i class="bi bi-person-plus"></i></span><div><small>Registered</small><b>{{ number_format($totals['registered']) }}</b></div></div>
        <div class="ui-stat"><span class="ico g"><i class="bi bi-patch-check"></i></span><div><small>Approved</small><b>{{ number_format($totals['approved']) }}</b></div></div>
        <div class="ui-stat"><span class="ico a"><i class="bi bi-arrow-return-left"></i></span><div><small>Returned</small><b>{{ number_format($totals['returned']) }}</b></div></div>
        <div class="ui-stat"><span class="ico r"><i class="bi bi-x-octagon"></i></span><div><small>Rejected</small><b>{{ number_format($totals['rejected']) }}</b></div></div>
        <div class="ui-stat"><span class="ico"><i class="bi bi-hourglass-split"></i></span><div><small>Waiting now</small><b>{{ number_format($totals['waiting']) }}</b></div></div>
    </div>

    <section class="ui-card flush">
        <div class="ui-card-head">
            <span class="ico"><i class="bi bi-person-badge"></i></span>
            <div><h3>Registrars</h3><p>Records they registered, and what approvers decided about them</p></div>
            <div class="legend end"><span><i style="background:#16a34a"></i>Approved</span><span><i style="background:#f59e0b"></i>Returned</span><span><i style="background:#ef4444"></i>Rejected</span></div>
        </div>
        @if (! $registrars)
            <div class="ui-empty"><i class="bi bi-person-badge"></i>No registrations in this period.</div>
        @else
            <div class="ui-table-wrap" style="margin-top:16px">
                <table class="ui-table">
                    <thead><tr><th>Registrar</th><th class="num">Registered</th><th class="num">Submitted</th><th class="num">Approved</th><th class="num">Returned</th><th class="num">Rejected</th><th>Decisions</th><th class="num" title="Share of decisions that were approvals">First-time quality</th></tr></thead>
                    <tbody>
                        @foreach ($registrars as $r)
                            @php $d = max(1, $r['approved'] + $r['returned'] + $r['rejected']); @endphp
                            <tr>
                                <td><b>{{ $r['user']->name }}</b><small style="display:block;color:var(--muted)">{{ $r['user']->role }}</small></td>
                                <td class="num">{{ $r['registered'] }}</td>
                                <td class="num">{{ $r['submitted'] }}</td>
                                <td class="num">{{ $r['approved'] }}</td>
                                <td class="num">{{ $r['returned'] }}</td>
                                <td class="num">{{ $r['rejected'] }}</td>
                                <td>
                                    <div class="bar" title="{{ $r['approved'] }} approved · {{ $r['returned'] }} returned · {{ $r['rejected'] }} rejected">
                                        <span class="a" style="width:{{ $r['approved'] / $d * 100 }}%"></span><span class="r" style="width:{{ $r['returned'] / $d * 100 }}%"></span><span class="x" style="width:{{ $r['rejected'] / $d * 100 }}%"></span>
                                    </div>
                                </td>
                                <td class="num">
                                    @if ($r['quality'] === null) — @else
                                        <span class="q {{ $r['quality'] >= 85 ? 'good' : ($r['quality'] >= 60 ? 'mid' : 'low') }}">{{ $r['quality'] }}%</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="ui-card flush">
        <div class="ui-card-head">
            <span class="ico"><i class="bi bi-patch-check"></i></span>
            <div><h3>Approvers</h3><p>Decisions made, and average time from submission to decision</p></div>
        </div>
        @if (! $approvers)
            <div class="ui-empty"><i class="bi bi-patch-check"></i>No approval decisions in this period.</div>
        @else
            <div class="ui-table-wrap" style="margin-top:16px">
                <table class="ui-table">
                    <thead><tr><th>Approver</th><th class="num">Decisions</th><th class="num">Approved</th><th class="num">Returned</th><th class="num">Rejected</th><th>Split</th><th class="num">Average time to decide</th></tr></thead>
                    <tbody>
                        @foreach ($approvers as $a)
                            @php $d = max(1, $a['decisions']); @endphp
                            <tr>
                                <td><b>{{ $a['user']->name }}</b><small style="display:block;color:var(--muted)">{{ $a['user']->role }}</small></td>
                                <td class="num">{{ $a['decisions'] }}</td>
                                <td class="num">{{ $a['approved'] }}</td>
                                <td class="num">{{ $a['returned'] }}</td>
                                <td class="num">{{ $a['rejected'] }}</td>
                                <td>
                                    <div class="bar"><span class="a" style="width:{{ $a['approved'] / $d * 100 }}%"></span><span class="r" style="width:{{ $a['returned'] / $d * 100 }}%"></span><span class="x" style="width:{{ $a['rejected'] / $d * 100 }}%"></span></div>
                                </td>
                                <td class="num">{{ PerformanceController::hours($a['avgHours']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
