@extends('layouts.app')

@section('title', 'Registered Persons')

@section('page_title', 'Registered Persons')

@push('styles')
<style>
    .pl {
        --accent: #16a34a;
        --accent-dark: #15803d;
        --accent-soft: #dcfce7;
        --ink: #0f172a;
        --text: #334155;
        --muted: #94a3b8;
        --line: #e5e7eb;
        display: flex; flex-direction: column; gap: 18px;
    }

    .pl-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .pl-head h2 { margin: 0; font-size: 22px; color: var(--ink); font-weight: 800; letter-spacing: -.3px; }
    .pl-head p { margin: 4px 0 0; font-size: 13.5px; color: #64748b; }

    .btn-a {
        display: inline-flex; align-items: center; gap: 8px; height: 42px; padding: 0 18px;
        border-radius: 12px; font-weight: 700; font-size: 14px; text-decoration: none; cursor: pointer;
        background: var(--accent); color: #fff; border: 0; box-shadow: 0 8px 18px -8px rgba(22,163,74,.7);
    }
    .btn-a:hover { background: var(--accent-dark); }
    .btn-a.ghost { background: #fff; color: var(--accent-dark); border: 1px solid var(--line); box-shadow: none; }
    .btn-a.ghost:hover { background: #f0fdf4; border-color: #86efac; }
    .pl-actions { display: flex; gap: 8px; flex-wrap: wrap; }

    .notice {
        display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 14px;
        background: #fffbeb; border: 1px solid #fde68a; color: #78350f; font-size: 13.5px;
    }
    .notice i { font-size: 18px; color: #d97706; }
    .notice a { margin-left: auto; font-weight: 700; color: #92400e; white-space: nowrap; }

    .toolbar {
        display: flex; gap: 10px; flex-wrap: wrap; align-items: center;
        background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 12px;
    }
    .tb-search { position: relative; flex: 1; min-width: 240px; }
    .tb-search i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--muted); }
    .tb-search input {
        width: 100%; height: 42px; padding: 0 14px 0 38px; border-radius: 11px;
        border: 1.5px solid var(--line); background: #f8fafc; font: inherit; font-size: 14px; outline: none;
    }
    .tb-search input:focus { border-color: var(--accent); background: #fff; box-shadow: 0 0 0 4px rgba(22,163,74,.12); }

    .seg-filter { display: inline-flex; background: #f1f5f9; border-radius: 11px; padding: 3px; gap: 2px; }
    .seg-filter a {
        padding: 8px 12px; border-radius: 9px; font-size: 13px; font-weight: 600; color: #64748b; text-decoration: none; white-space: nowrap;
    }
    .seg-filter a.on { background: #fff; color: var(--ink); box-shadow: 0 1px 3px rgba(15,23,42,.12); }

    .view-toggle { display: inline-flex; gap: 4px; margin-left: auto; }
    .view-toggle button {
        width: 40px; height: 40px; border-radius: 10px; border: 1px solid var(--line); background: #fff;
        color: #64748b; cursor: pointer; font-size: 16px;
    }
    .view-toggle button.on { border-color: var(--accent); color: var(--accent); background: var(--accent-soft); }

    /* ---------- GRID VIEW ---------- */
    .cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 16px; }
    .pcard {
        position: relative; background: #fff; border: 1px solid var(--line); border-radius: 18px;
        padding: 22px 18px 16px; text-align: center; transition: box-shadow .2s, transform .2s;
        display: flex; flex-direction: column; align-items: center;
    }
    .pcard:hover { box-shadow: 0 16px 32px -18px rgba(15,23,42,.3); transform: translateY(-2px); }
    .pcard-photo { position: relative; margin-bottom: 12px; }
    .cam {
        position: absolute; right: -6px; bottom: -6px; width: 30px; height: 30px; border-radius: 50%;
        background: var(--accent); color: #fff; border: 3px solid #fff; display: grid; place-items: center;
        font-size: 12px; text-decoration: none; box-shadow: 0 6px 12px -4px rgba(22,163,74,.6);
        transition: transform .15s;
    }
    .cam:hover { transform: scale(1.1); background: var(--accent-dark); }
    .cam.missing { background: #f59e0b; box-shadow: 0 6px 12px -4px rgba(245,158,11,.6); }

    .pcard b { font-size: 15px; color: var(--ink); display: block; line-height: 1.3; }
    .pcard .sub { font-size: 12.5px; color: #64748b; margin-top: 3px; }
    .pcard .nin { margin-top: 10px; font-family: ui-monospace, Menlo, monospace; font-size: 12px; color: var(--text); background: #f8fafc; border: 1px solid var(--line); padding: 4px 9px; border-radius: 8px; letter-spacing: .3px; }
    .pcard .tags { display: flex; gap: 6px; justify-content: center; flex-wrap: wrap; margin-top: 10px; }
    .tag { display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px; font-weight: 600; padding: 3px 9px; border-radius: 999px; background: #f1f5f9; color: var(--text); }
    .tag .dot { width: 7px; height: 7px; border-radius: 50%; }
    .tag.ok { background: #dcfce7; color: #166534; }
    .tag.warn { background: #fef3c7; color: #92400e; }
    .pcard .open {
        margin-top: 14px; width: 100%; height: 38px; border-radius: 10px; border: 1px solid var(--line);
        display: inline-flex; align-items: center; justify-content: center; gap: 6px;
        font-size: 13px; font-weight: 700; color: var(--text); text-decoration: none; transition: all .15s;
    }
    .pcard .open:hover { border-color: var(--accent); color: var(--accent); background: #f0fdf4; }

    /* ---------- TABLE VIEW ---------- */
    .tbl-card { background: #fff; border: 1px solid var(--line); border-radius: 18px; overflow: hidden; }
    .tbl-card .scroll { overflow-x: auto; }
    .ptbl { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    .ptbl th { text-align: left; font-size: 11.5px; text-transform: uppercase; letter-spacing: .6px; color: var(--muted); font-weight: 700; padding: 12px 18px; background: #f8fafc; border-bottom: 1px solid var(--line); }
    .ptbl td { padding: 12px 18px; border-bottom: 1px solid #f1f5f9; color: var(--text); white-space: nowrap; }
    .ptbl tbody tr:hover { background: #f8fafc; }
    .ptbl tr:last-child td { border-bottom: 0; }
    .who { display: flex; align-items: center; gap: 12px; }
    .who .pcard-photo { margin: 0; }
    .who .cam { width: 22px; height: 22px; font-size: 9px; border-width: 2px; right: -5px; bottom: -5px; }
    .who b { color: var(--ink); display: block; }
    .who small { color: var(--muted); font-size: 12px; }
    .mono { font-family: ui-monospace, Menlo, monospace; font-size: 12.5px; color: var(--ink); }
    .row-act { width: 32px; height: 32px; border-radius: 9px; border: 1px solid var(--line); display: inline-grid; place-items: center; color: var(--text); text-decoration: none; }
    .row-act:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-soft); }

    .empty-state { background: #fff; border: 1px dashed #cbd5e1; border-radius: 18px; padding: 50px 20px; text-align: center; color: #64748b; }
    .empty-state i { font-size: 38px; color: var(--muted); display: block; margin-bottom: 10px; }

    .pager { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; font-size: 13px; color: #64748b; }
    .pager nav { display: flex; gap: 6px; }
    .pager a, .pager span.pg {
        min-width: 36px; height: 36px; padding: 0 10px; border-radius: 10px; display: inline-grid; place-items: center;
        border: 1px solid var(--line); background: #fff; color: var(--text); text-decoration: none; font-weight: 600;
    }
    .pager a:hover { border-color: var(--accent); color: var(--accent); }
    .pager span.pg.on { background: var(--accent); border-color: var(--accent); color: #fff; }
    .pager span.pg.off { opacity: .4; }

    [data-view="table"] .cards, [data-view="grid"] .tbl-card { display: none; }

    @media (max-width: 640px) {
        .view-toggle { margin-left: 0; }
        .seg-filter { width: 100%; overflow-x: auto; }
    }
</style>
@endpush

@section('content')

@php
    $filterUrl = fn (array $changes) => route('people.index', array_filter(
        array_merge(['q' => $search, 'gender' => $gender, 'photo' => $photo], $changes),
        fn ($v) => $v !== null && $v !== ''
    ));
@endphp

<div class="pl" id="peopleList" data-view="grid">

    <div class="pl-head">
        <div>
            <h2>Registered persons</h2>
            <p>{{ number_format($people->total()) }} {{ Str::plural('record', $people->total()) }}{{ $search !== '' ? ' matching "' . $search . '"' : '' }}</p>
        </div>
        @php $canEditList = in_array(auth()->user()->role ?? 'Administrator', ['Administrator', 'Registrar'], true); @endphp
        @if ($canEditList)
            <div class="pl-actions">
                @if ($people->count())
                    <a href="{{ route('cards.print', ['ids' => $people->pluck('id')->implode(',')]) }}" target="_blank" class="btn-a ghost" title="Print ID cards for the {{ $people->count() }} people on this page"><i class="bi bi-printer"></i> Print cards ({{ $people->count() }})</a>
                @endif
                <a href="{{ route('people.import') }}" class="btn-a ghost"><i class="bi bi-file-earmark-spreadsheet"></i> Import CSV</a>
                <a href="{{ route('people.create') }}" class="btn-a"><i class="bi bi-person-plus-fill"></i> Register new person</a>
            </div>
        @endif
    </div>

    @if ($withoutPhoto > 0 && $photo !== 'without')
        <div class="notice">
            <i class="bi bi-camera"></i>
            <span><b>{{ $withoutPhoto }}</b> {{ Str::plural('person', $withoutPhoto) }} still {{ $withoutPhoto === 1 ? 'has' : 'have' }} no profile picture. Click the <i class="bi bi-camera-fill" style="font-size:13px;color:#d97706"></i> button on a person to add one.</span>
            <a href="{{ $filterUrl(['photo' => 'without', 'page' => null]) }}">Show them <i class="bi bi-arrow-right"></i></a>
        </div>
    @endif

    <form class="toolbar" method="GET" action="{{ route('people.index') }}">
        @if ($gender)<input type="hidden" name="gender" value="{{ $gender }}">@endif
        @if ($photo)<input type="hidden" name="photo" value="{{ $photo }}">@endif

        <div class="tb-search">
            <i class="bi bi-search"></i>
            <input type="search" name="q" value="{{ $search }}" placeholder="Search by name, NIN, region or phone…">
        </div>

        <div class="seg-filter" aria-label="Gender filter">
            <a href="{{ $filterUrl(['gender' => null, 'page' => null]) }}" class="{{ !$gender ? 'on' : '' }}">All</a>
            <a href="{{ $filterUrl(['gender' => 'Male', 'page' => null]) }}" class="{{ $gender === 'Male' ? 'on' : '' }}">Male</a>
            <a href="{{ $filterUrl(['gender' => 'Female', 'page' => null]) }}" class="{{ $gender === 'Female' ? 'on' : '' }}">Female</a>
        </div>

        <div class="seg-filter" aria-label="Photo filter">
            <a href="{{ $filterUrl(['photo' => null, 'page' => null]) }}" class="{{ !$photo ? 'on' : '' }}"><i class="bi bi-images"></i> Any</a>
            <a href="{{ $filterUrl(['photo' => 'with', 'page' => null]) }}" class="{{ $photo === 'with' ? 'on' : '' }}">With photo</a>
            <a href="{{ $filterUrl(['photo' => 'without', 'page' => null]) }}" class="{{ $photo === 'without' ? 'on' : '' }}">No photo</a>
        </div>

        <div class="view-toggle">
            <button type="button" data-set-view="grid" class="on" title="Grid view"><i class="bi bi-grid-3x3-gap"></i></button>
            <button type="button" data-set-view="table" title="Table view"><i class="bi bi-list-ul"></i></button>
        </div>
    </form>

    @if ($people->isEmpty())
        <div class="empty-state">
            <i class="bi bi-person-x"></i>
            No persons found{{ $search !== '' ? ' for "' . $search . '"' : '' }}.
            @if ($search !== '' || $gender || $photo)
                <div style="margin-top:12px"><a href="{{ route('people.index') }}" style="color:#16a34a;font-weight:700">Clear filters</a></div>
            @endif
        </div>
    @else

        {{-- GRID --}}
        <div class="cards">
            @foreach ($people as $person)
                @php $hasPhoto = filled($person->profile_picture); @endphp
                <div class="pcard">
                    <div class="pcard-photo">
                        <x-avatar :person="$person" :size="84" :radius="24" />
                        <a href="{{ route('people.show', $person) }}#photo" class="cam {{ $hasPhoto ? '' : 'missing' }}"
                           title="{{ $hasPhoto ? 'Change photo' : 'Add photo' }}"><i class="bi bi-camera-fill"></i></a>
                    </div>
                    <b>{{ $person->full_name }}</b>
                    <span class="sub">
                        {{ $person->date_of_birth ? $person->date_of_birth->age . ' yrs' : '' }}{{ $person->region ? ' · ' . $person->region : '' }}
                    </span>
                    <span class="nin">{{ $person->nin }}</span>
                    <div class="tags">
                        <span class="tag"><span class="dot" style="background: {{ $person->gender === 'Male' ? '#2a78d6' : '#eb6834' }}"></span>{{ $person->gender }}</span>
                        <span class="tag {{ $person->status === 'Active' ? 'ok' : '' }}">{{ $person->status ?? 'Active' }}</span>
                        @unless ($hasPhoto)<span class="tag warn"><i class="bi bi-camera"></i> No photo</span>@endunless
                    </div>
                    <a href="{{ route('people.show', $person) }}" class="open">View profile <i class="bi bi-arrow-right"></i></a>
                </div>
            @endforeach
        </div>

        {{-- TABLE --}}
        <div class="tbl-card">
            <div class="scroll">
                <table class="ptbl">
                    <thead>
                        <tr><th>Person</th><th>NIN</th><th>Gender</th><th>Region</th><th>Phone</th><th>Registered</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($people as $person)
                            <tr>
                                <td>
                                    <div class="who">
                                        <span class="pcard-photo" style="position:relative">
                                            <x-avatar :person="$person" :size="38" />
                                            <a href="{{ route('people.show', $person) }}#photo" class="cam {{ filled($person->profile_picture) ? '' : 'missing' }}" title="Edit photo"><i class="bi bi-camera-fill"></i></a>
                                        </span>
                                        <span><b>{{ $person->full_name }}</b><small>{{ $person->date_of_birth?->format('d M Y') }}</small></span>
                                    </div>
                                </td>
                                <td><span class="mono">{{ $person->nin }}</span></td>
                                <td>{{ $person->gender }}</td>
                                <td>{{ $person->region ?: '—' }}</td>
                                <td>{{ $person->phone ?: '—' }}</td>
                                <td>{{ $person->created_at?->format('d M Y') }}</td>
                                <td><a href="{{ route('people.show', $person) }}" class="row-act" title="View"><i class="bi bi-chevron-right"></i></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- PAGINATION --}}
        @if ($people->hasPages())
            <div class="pager">
                <span>Showing {{ $people->firstItem() }}–{{ $people->lastItem() }} of {{ $people->total() }}</span>
                <nav>
                    @if ($people->onFirstPage())
                        <span class="pg off"><i class="bi bi-chevron-left"></i></span>
                    @else
                        <a href="{{ $people->previousPageUrl() }}"><i class="bi bi-chevron-left"></i></a>
                    @endif

                    @foreach ($people->getUrlRange(max(1, $people->currentPage() - 2), min($people->lastPage(), $people->currentPage() + 2)) as $page => $url)
                        @if ($page === $people->currentPage())
                            <span class="pg on">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach

                    @if ($people->hasMorePages())
                        <a href="{{ $people->nextPageUrl() }}"><i class="bi bi-chevron-right"></i></a>
                    @else
                        <span class="pg off"><i class="bi bi-chevron-right"></i></span>
                    @endif
                </nav>
            </div>
        @endif
    @endif
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const list = document.getElementById('peopleList');
    const buttons = document.querySelectorAll('[data-set-view]');

    function setView(view) {
        list.dataset.view = view;
        buttons.forEach((b) => b.classList.toggle('on', b.dataset.setView === view));
        try { localStorage.setItem('peopleView', view); } catch (e) {}
    }

    buttons.forEach((b) => b.addEventListener('click', () => setView(b.dataset.setView)));

    try { setView(localStorage.getItem('peopleView') || 'grid'); } catch (e) {}
});
</script>
@endpush
