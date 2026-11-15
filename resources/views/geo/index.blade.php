@extends('layouts.app')

@section('title', 'Locations')
@section('page_title', 'Locations')

@php
    $isAdmin = (auth()->user()->role ?? 'Administrator') === 'Administrator';
    $levelInfo = [
        'regions' => ['Regions', 'bi-map'],
        'districts' => ['Districts', 'bi-signpost-split'],
        'wards' => ['Wards', 'bi-pin-map'],
        'villages' => ['Villages / Streets', 'bi-signpost'],
    ];
    $here = $ward ?? $district ?? $region;
    $param = ['regions' => 'region', 'districts' => 'district', 'wards' => 'ward'][$level] ?? null;
@endphp

@push('styles')
<style>
    .crumbs { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; font-size: 13.5px; }
    .crumbs a { color: var(--muted); text-decoration: none; font-weight: 600; }
    .crumbs a:hover { color: var(--primary); }
    .crumbs i { color: #cbd5e1; font-size: 11px; }
    .crumbs b { color: var(--ink); }
    .geo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 12px; }
    .geo-item {
        display: flex; flex-direction: column; gap: 10px; padding: 16px; border-radius: 14px; border: 1px solid var(--border);
        background: #fff; text-decoration: none; color: inherit; transition: all .15s;
    }
    a.geo-item:hover { border-color: #86efac; box-shadow: 0 10px 22px -14px rgba(21,128,61,.5); transform: translateY(-1px); }
    .geo-item .top { display: flex; align-items: center; gap: 10px; }
    .geo-item .ico { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; background: #f0fdf4; color: var(--primary); flex-shrink: 0; }
    .geo-item b { color: var(--ink); font-size: 14px; line-height: 1.3; }
    .geo-item .stats { display: flex; gap: 6px; flex-wrap: wrap; }
    .geo-item .stats span { font-size: 11.5px; color: var(--muted); background: #f8fafc; padding: 2px 8px; border-radius: 6px; }
    .geo-item .stats span.people { background: #dcfce7; color: #166534; font-weight: 600; }
    .geo-item.village { flex-direction: row; align-items: center; }
    .geo-item.village form { margin-left: auto; }
    .add-form { display: grid; grid-template-columns: 1fr 160px auto; gap: 10px; align-items: end; }
    @media (max-width: 700px) { .add-form { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
<div class="ui-page">

    <div class="ui-head">
        <div>
            <h2>{{ $here?->name ?? 'Tanzania' }}</h2>
            @if ($region)
            <div class="crumbs" style="margin-top:6px">
                <a href="{{ route('geo.index') }}"><i class="bi bi-globe-africa" style="font-size:13px;color:inherit"></i> Tanzania</a>
                @if ($region)<i class="bi bi-chevron-right"></i>@if ($district)<a href="{{ route('geo.index', ['region' => $region->id]) }}">{{ $region->name }}</a>@else<b>{{ $region->name }}</b>@endif @endif
                @if ($district)<i class="bi bi-chevron-right"></i>@if ($ward)<a href="{{ route('geo.index', ['district' => $district->id]) }}">{{ $district->name }}</a>@else<b>{{ $district->name }}</b>@endif @endif
                @if ($ward)<i class="bi bi-chevron-right"></i><b>{{ $ward->name }}</b>@endif
            </div>
            @else
            <p>Regions, districts, wards and villages used in registration forms.</p>
            @endif
        </div>
    </div>

    @unless ($region)
        <div class="ui-stats">
            <div class="ui-stat"><span class="ico g"><i class="bi bi-map"></i></span><div><small>Regions</small><b>{{ number_format($totals['regions']) }}</b></div></div>
            <div class="ui-stat"><span class="ico b"><i class="bi bi-signpost-split"></i></span><div><small>Districts</small><b>{{ number_format($totals['districts']) }}</b></div></div>
            <div class="ui-stat"><span class="ico a"><i class="bi bi-pin-map"></i></span><div><small>Wards</small><b>{{ number_format($totals['wards']) }}</b></div></div>
            <div class="ui-stat"><span class="ico"><i class="bi bi-signpost"></i></span><div><small>Villages / Streets</small><b>{{ number_format($totals['villages']) }}</b></div></div>
        </div>
    @endunless

    @if ($ward && $isAdmin)
        <section class="ui-card">
            <div class="ui-card-head">
                <span class="ico"><i class="bi bi-plus-lg"></i></span>
                <div><h3>Add village / street to {{ $ward->name }}</h3><p>It will appear immediately in the registration form.</p></div>
            </div>
            <form class="add-form" method="POST" action="{{ route('geo.villages.store', $ward) }}">
                @csrf
                <div class="ui-field">
                    <label for="vname">Name</label>
                    <input class="ui-input {{ $errors->has('name') ? 'is-invalid' : '' }}" id="vname" name="name" value="{{ old('name') }}" placeholder="e.g. Mji Mpya" required maxlength="180">
                    @error('name')<span class="ui-err">{{ $message }}</span>@enderror
                </div>
                <div class="ui-field">
                    <label for="vtype">Type</label>
                    <select class="ui-select" id="vtype" name="type">
                        @foreach (['Village', 'Street', 'Hamlet', 'Other'] as $t)<option @selected(old('type', 'Village') === $t)>{{ $t }}</option>@endforeach
                    </select>
                </div>
                <button class="ui-btn primary" style="height:42px"><i class="bi bi-plus-lg"></i> Add</button>
            </form>
        </section>
    @endif

    <section class="ui-card">
        <div class="ui-card-head">
            <span class="ico"><i class="bi {{ $levelInfo[$level][1] }}"></i></span>
            <div><h3>{{ $items->count() }} {{ $levelInfo[$level][0] }}</h3><p>{{ $level === 'villages' ? 'Villages and streets in this ward' : 'Select one to see what is inside' }}</p></div>
            <form class="end" method="GET" action="{{ route('geo.index') }}" style="width:260px;max-width:100%">
                @if ($ward)<input type="hidden" name="ward" value="{{ $ward->id }}">@elseif ($district)<input type="hidden" name="district" value="{{ $district->id }}">@elseif ($region)<input type="hidden" name="region" value="{{ $region->id }}">@endif
                <div class="ui-search"><i class="bi bi-search"></i><input class="ui-input" type="search" name="q" value="{{ $search }}" placeholder="Filter {{ strtolower($levelInfo[$level][0]) }}…"></div>
            </form>
        </div>

        @if ($items->isEmpty())
            <div class="ui-empty">
                <i class="bi {{ $levelInfo[$level][1] }}"></i>
                {{ $search !== '' ? 'Nothing matches "' . $search . '".' : 'No ' . strtolower($levelInfo[$level][0]) . ' recorded here yet.' }}
                @if ($level === 'villages' && $isAdmin && $search === '')<div class="ui-help" style="margin-top:6px">Use the form above to add the first one.</div>@endif
            </div>
        @else
            <div class="geo-grid">
                @foreach ($items as $item)
                    @php $people = $peopleCounts[$item->id] ?? 0; @endphp
                    @if ($level === 'villages')
                        <div class="geo-item village">
                            <span class="ico"><i class="bi bi-{{ $item->type === 'Street' ? 'signpost' : 'house' }}"></i></span>
                            <div>
                                <b>{{ $item->name }}</b>
                                <div class="stats" style="margin-top:4px">
                                    <span>{{ $item->type }}</span>
                                    @if ($people)<span class="people">{{ $people }} {{ Str::plural('person', $people) }}</span>@endif
                                </div>
                            </div>
                            @if ($isAdmin && ! $people)
                                <form method="POST" action="{{ route('geo.villages.destroy', $item) }}" onsubmit="return confirm('Delete {{ addslashes($item->name) }}?')">
                                    @csrf @method('DELETE')
                                    <button class="ui-btn sm icon danger" title="Delete"><i class="bi bi-trash3"></i></button>
                                </form>
                            @endif
                        </div>
                    @else
                        <a class="geo-item" href="{{ route('geo.index', [$param => $item->id]) }}">
                            <div class="top">
                                <span class="ico"><i class="bi {{ $levelInfo[$level][1] }}"></i></span>
                                <b>{{ $item->name }}</b>
                                <i class="bi bi-chevron-right" style="margin-left:auto;color:#cbd5e1"></i>
                            </div>
                            <div class="stats">
                                @isset($item->districts_count)<span>{{ $item->districts_count }} districts</span>@endisset
                                @isset($item->wards_count)<span>{{ number_format($item->wards_count) }} wards</span>@endisset
                                @isset($item->villages_count)<span>{{ number_format($item->villages_count) }} villages</span>@endisset
                                @if ($people)<span class="people">{{ $people }} {{ Str::plural('person', $people) }}</span>@endif
                            </div>
                        </a>
                    @endif
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
