@extends('layouts.app')

@section('title', 'NIN Verifications')
@section('page_title', 'NIN Verifications')

@php
    $filterUrl = fn (array $c) => route('verifications.index', array_filter(array_merge(['status' => $status, 'nin' => $nin], $c), fn ($v) => filled($v)));
@endphp

@push('styles')
<style>
    .score { display: inline-flex; gap: 3px; vertical-align: middle; }
    .score span { width: 8px; height: 8px; border-radius: 2px; background: #e2e8f0; }
    .score span.ok { background: #16a34a; }
    .score span.miss { background: #fca5a5; }
    .lock-row { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px dashed var(--border); }
    .lock-row:last-child { border-bottom: 0; }
    .lock-row .end { margin-left: auto; text-align: right; font-size: 12px; color: var(--muted); }
    .rate-bar { height: 8px; border-radius: 999px; background: #fee2e2; overflow: hidden; display: flex; gap: 2px; margin-top: 10px; }
    .rate-bar span { display: block; height: 100%; background: #16a34a; }
</style>
@endpush

@section('content')
<div class="ui-page">

    <div class="ui-head">
        <div>
            <h2>NIN verifications</h2>
            <p>Every identity check made by connected systems through the verification API.</p>
        </div>
        @if (Route::has('settings.api') && (auth()->user()->role ?? 'Administrator') === 'Administrator')
            <div class="ui-head-actions">
                <a href="{{ route('settings.api') }}" class="ui-btn"><i class="bi bi-key"></i> API settings</a>
            </div>
        @endif
    </div>

    <div class="ui-stats">
        <div class="ui-stat"><span class="ico b"><i class="bi bi-arrow-repeat"></i></span><div><small>Attempts (30 days)</small><b>{{ number_format($stats['total']) }}</b></div></div>
        <div class="ui-stat"><span class="ico g"><i class="bi bi-patch-check"></i></span><div><small>Passed</small><b>{{ number_format($stats['passed']) }}</b></div></div>
        <div class="ui-stat"><span class="ico r"><i class="bi bi-x-octagon"></i></span><div><small>Failed</small><b>{{ number_format($stats['failed']) }}</b></div></div>
        <div class="ui-stat">
            <span class="ico a"><i class="bi bi-lock"></i></span>
            <div><small>Locked NINs now</small><b>{{ $locked->count() }}</b></div>
        </div>
    </div>

    <div class="ui-grid-3">

        <section class="ui-card flush">
            <div class="ui-card-head">
                <span class="ico"><i class="bi bi-list-check"></i></span>
                <div><h3>Verification attempts</h3><p>Newest first</p></div>
            </div>

            <form class="ui-toolbar" method="GET" action="{{ route('verifications.index') }}" style="border:0;border-radius:0;border-top:1px solid var(--border);border-bottom:1px solid var(--border);margin-top:16px">
                @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                <div class="ui-search">
                    <i class="bi bi-search"></i>
                    <input class="ui-input" type="search" name="nin" value="{{ $nin }}" placeholder="Filter by NIN…" inputmode="numeric">
                </div>
                <div class="ui-seg">
                    @foreach ([null => 'All', 'passed' => 'Passed', 'failed' => 'Failed', 'pending' => 'Pending', 'expired' => 'Expired'] as $key => $label)
                        <a href="{{ $filterUrl(['status' => $key, 'page' => null]) }}" class="{{ $status === $key ? 'on' : '' }}">{{ $label }}</a>
                    @endforeach
                </div>
            </form>

            @if ($verifications->isEmpty())
                <div class="ui-empty"><i class="bi bi-shield"></i>No verification attempts {{ $status || $nin ? 'match these filters' : 'yet' }}.</div>
            @else
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead><tr><th>Person</th><th>Result</th><th>Answers</th><th>Started</th></tr></thead>
                        <tbody>
                            @foreach ($verifications as $v)
                                @php
                                    $p = $people[$v->nin] ?? null;
                                    $expired = $v->status === 'pending' && $v->expires_at?->isPast();
                                    [$cls, $icon, $label] = match (true) {
                                        $v->status === 'passed' => ['ok', 'patch-check-fill', 'Passed'],
                                        $v->status === 'failed' => ['bad', 'x-octagon-fill', 'Failed'],
                                        $expired => ['', 'hourglass-bottom', 'Expired'],
                                        default => ['warn', 'hourglass-split', 'In progress'],
                                    };
                                    $total = count($v->questions ?? []);
                                @endphp
                                <tr>
                                    <td>
                                        @if ($p)
                                            <a href="{{ route('people.show', $p) }}" class="ui-who" style="text-decoration:none">
                                                <x-avatar :person="$p" :size="34" />
                                                <span><b>{{ $p->full_name }}</b><small class="ui-mono" style="color:var(--muted)">{{ $v->nin }}</small></span>
                                            </a>
                                        @else
                                            <span class="ui-mono">{{ $v->nin }}</span> <span class="ui-pill">Unknown</span>
                                        @endif
                                    </td>
                                    <td><span class="ui-pill {{ $cls }}"><i class="bi bi-{{ $icon }}"></i> {{ $label }}</span></td>
                                    <td>
                                        <span class="score" title="{{ $v->correct_answers }} correct of {{ $v->questions_answered }} answered">
                                            @for ($i = 0; $i < max($total, 3); $i++)
                                                <span class="{{ $i < $v->correct_answers ? 'ok' : ($i < $v->questions_answered ? 'miss' : '') }}"></span>
                                            @endfor
                                        </span>
                                        <small style="color:var(--muted);margin-left:6px">{{ $v->correct_answers }}/{{ $v->questions_answered }}</small>
                                    </td>
                                    <td title="{{ $v->created_at?->format('d M Y H:i:s') }}">{{ $v->created_at?->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="padding:14px 18px;border-top:1px solid var(--border)">
                    @include('partials.pager', ['paginator' => $verifications])
                </div>
            @endif
        </section>

        <div style="display:flex;flex-direction:column;gap:18px">
            <section class="ui-card">
                <div class="ui-card-head">
                    <span class="ico"><i class="bi bi-graph-up"></i></span>
                    <div><h3>Pass rate</h3><p>Completed checks, last 30 days</p></div>
                </div>
                @if ($stats['rate'] === null)
                    <p style="margin:0;color:var(--muted);font-size:13.5px">No completed checks yet.</p>
                @else
                    <b style="font-size:30px;color:var(--ink)">{{ $stats['rate'] }}%</b>
                    <div class="rate-bar"><span style="width:{{ $stats['rate'] }}%"></span></div>
                    <p style="margin:10px 0 0;font-size:12.5px;color:var(--muted)">{{ $stats['passed'] }} passed · {{ $stats['failed'] }} failed · {{ $stats['pending'] }} in progress</p>
                @endif
            </section>

            <section class="ui-card">
                <div class="ui-card-head">
                    <span class="ico" style="background:#fef3c7;color:#b45309"><i class="bi bi-lock"></i></span>
                    <div><h3>Locked NINs</h3><p>3+ failures in the last hour. Unlocks automatically.</p></div>
                </div>
                @forelse ($locked as $l)
                    @php $lp = $lockedPeople[$l->nin] ?? null; @endphp
                    <div class="lock-row">
                        @if ($lp)<x-avatar :person="$lp" :size="34" />@endif
                        <div>
                            <b style="font-size:13.5px;color:var(--ink)">{{ $lp?->full_name ?? 'Unknown person' }}</b>
                            <div class="ui-mono" style="color:var(--muted);font-size:11.5px">{{ $l->nin }}</div>
                        </div>
                        <div class="end">
                            <span class="ui-pill bad">{{ $l->failures }} failures</span>
                            <div style="margin-top:4px">unlocks {{ \Carbon\Carbon::parse($l->last_failure)->addHour()->diffForHumans() }}</div>
                        </div>
                    </div>
                @empty
                    <p style="margin:0;color:var(--muted);font-size:13.5px"><i class="bi bi-check-circle" style="color:#16a34a"></i> No NINs are locked right now.</p>
                @endforelse
            </section>
        </div>

    </div>
</div>
@endsection
