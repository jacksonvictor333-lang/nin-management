@extends('layouts.app')

@section('title', 'Activity Log')
@section('page_title', 'Activity Log')

@php
    $filterUrl = fn (array $c) => route('activity.index', array_filter(array_merge(['group' => $group, 'user' => $userId, 'q' => $search], $c), fn ($v) => filled($v)));
@endphp

@push('styles')
<style>
    .day { font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .6px; margin: 4px 0 10px; }
    .feed { position: relative; }
    .entry { display: flex; gap: 14px; padding: 12px 0; position: relative; }
    .entry:not(:last-child)::before { content: ''; position: absolute; left: 17px; top: 48px; bottom: -6px; width: 2px; background: #f1f5f9; }
    .entry .ico { width: 36px; height: 36px; border-radius: 11px; display: grid; place-items: center; flex-shrink: 0; font-size: 15px; z-index: 1; }
    .entry .txt { flex: 1; min-width: 0; }
    .entry .txt p { margin: 0; font-size: 13.5px; color: var(--ink); line-height: 1.45; }
    .entry .txt small { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; font-size: 12px; color: var(--muted); margin-top: 3px; }
    .entry .time { font-size: 12px; color: var(--muted); white-space: nowrap; font-variant-numeric: tabular-nums; }
    .entry a.subject { color: var(--primary); font-weight: 600; text-decoration: none; }
    .entry a.subject:hover { text-decoration: underline; }
</style>
@endpush

@section('content')
<div class="ui-page">

    <div class="ui-head">
        <div>
            <h2>Activity log</h2>
            <p>Who did what, and when. Entries cannot be edited or deleted.</p>
        </div>
    </div>

    <form class="ui-toolbar" method="GET" action="{{ route('activity.index') }}">
        @if ($group)<input type="hidden" name="group" value="{{ $group }}">@endif
        <div class="ui-search">
            <i class="bi bi-search"></i>
            <input class="ui-input" type="search" name="q" value="{{ $search }}" placeholder="Search descriptions, e.g. a name or NIN…">
        </div>
        <select class="ui-select" name="user" style="width:auto;min-width:170px" onchange="this.form.submit()">
            <option value="">All users</option>
            @foreach ($users as $u)<option value="{{ $u->id }}" @selected($userId === $u->id)>{{ $u->name }}</option>@endforeach
        </select>
        <div class="ui-seg">
            <a href="{{ $filterUrl(['group' => null, 'page' => null]) }}" class="{{ !$group ? 'on' : '' }}">All</a>
            @foreach ($groups as $key => $label)
                <a href="{{ $filterUrl(['group' => $key, 'page' => null]) }}" class="{{ $group === $key ? 'on' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
    </form>

    <section class="ui-card">
        @if ($logs->isEmpty())
            <div class="ui-empty"><i class="bi bi-clock-history"></i>No activity {{ $group || $userId || $search !== '' ? 'matches these filters' : 'recorded yet' }}.</div>
        @else
            @foreach ($byDay as $day => $entries)
                <div class="day" @if (!$loop->first) style="margin-top:18px" @endif>{{ $day }}</div>
                <div class="feed">
                    @foreach ($entries as $log)
                        @php [$icon, $fg, $bg] = $log->presentation(); @endphp
                        <div class="entry">
                            <span class="ico" style="background:{{ $bg }};color:{{ $fg }}"><i class="bi {{ $icon }}"></i></span>
                            <div class="txt">
                                <p>{{ $log->description }}</p>
                                <small>
                                    <span><i class="bi bi-person"></i> {{ $log->user?->name ?? 'System' }}</span>
                                    <span class="ui-pill" style="font-size:11px;padding:1px 8px">{{ $log->action }}</span>
                                    @if ($log->subject_type === \App\Models\Person::class && $log->subject)
                                        <a class="subject" href="{{ route('people.show', $log->subject_id) }}">Open record <i class="bi bi-arrow-right"></i></a>
                                    @endif
                                    @if ($log->ip_address)<span><i class="bi bi-globe2"></i> {{ $log->ip_address }}</span>@endif
                                </small>
                            </div>
                            <span class="time" title="{{ $log->created_at->format('d M Y H:i:s') }}">{{ $log->created_at->format('H:i') }}</span>
                        </div>
                    @endforeach
                </div>
            @endforeach

            <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border)">
                @include('partials.pager', ['paginator' => $logs])
            </div>
        @endif
    </section>
</div>
@endsection
