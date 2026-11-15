@extends('layouts.app')

@section('title', 'Cards')
@section('page_title', 'Cards')

@use('App\Models\Person')

@push('styles')
<style>
    .ui-seg a .n { display: inline-block; min-width: 20px; padding: 0 6px; margin-left: 4px; border-radius: 999px; background: #e2e8f0; color: var(--text); font-size: 11.5px; text-align: center; }
    .ui-seg a.on .n { background: var(--primary-soft); color: var(--primary); }
    .cd-cb { width: 17px; height: 17px; accent-color: #16a34a; }
    .bulk-bar { display: none; align-items: center; gap: 10px; flex-wrap: wrap; padding: 12px 22px; background: #f0fdf4; border-bottom: 1px solid #bbf7d0; font-size: 13px; }
    .bulk-bar.on { display: flex; }
    .bulk-bar .sp { flex: 1; }
    .ver { font-size: 11px; font-weight: 700; color: #b45309; background: #fef3c7; border-radius: 6px; padding: 1px 6px; margin-left: 6px; }
    .row-actions form { margin: 0; }
</style>
@endpush

@section('content')
<div class="ui-page">

    <div class="ui-head">
        <div>
            <h2>Cards</h2>
            <p>Track every approved card: print it, tell the holder it is ready (SMS), then record who collected it.</p>
        </div>
    </div>

    <div class="ui-stats">
        @foreach (Person::CARD_STAGES as $key => [$label, $tone, $icon])
            <a href="{{ route('cards.index', ['tab' => $key]) }}" class="ui-stat" style="text-decoration:none;color:inherit">
                <span class="ico {{ ['to_print' => '', 'printed' => 'b', 'ready' => 'a', 'collected' => 'g'][$key] }}"><i class="bi {{ $icon }}"></i></span>
                <div><small>{{ $label }}</small><b>{{ number_format($counts[$key]) }}</b></div>
            </a>
        @endforeach
    </div>

    <div class="ui-toolbar" style="justify-content:space-between">
        <nav class="ui-seg">
            @foreach (Person::CARD_STAGES as $key => [$label, $tone, $icon])
                <a href="{{ route('cards.index', ['tab' => $key]) }}" class="{{ $tab === $key ? 'on' : '' }}"><i class="bi {{ $icon }}"></i> {{ $label }} <span class="n">{{ $counts[$key] }}</span></a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('cards.index') }}" style="width:260px;max-width:100%">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="ui-search"><i class="bi bi-search"></i><input class="ui-input" type="search" name="q" value="{{ $search }}" placeholder="Name, NIN or application no."></div>
        </form>
    </div>

    <section class="ui-card flush">
        @if ($people->isEmpty())
            <div class="ui-empty"><i class="bi bi-person-vcard"></i>{{ $search !== '' ? 'No card matches "' . $search . '".' : 'No cards at this stage.' }}</div>
        @else
            @php $bulk = in_array($tab, ['to_print', 'printed'], true); @endphp
            @if ($bulk)
                <form method="POST" action="{{ route('cards.bulk') }}" id="bulkForm">
                    @csrf
                    <input type="hidden" name="action" value="{{ $tab === 'to_print' ? Person::CARD_PRINTED : Person::CARD_READY }}">
                </form>
                <div class="bulk-bar" id="bulkBar">
                    <b><span id="bulkCount">0</span> selected</b>
                    <span class="sp"></span>
                    @if ($tab === 'to_print')
                        <a href="#" class="ui-btn sm" id="printSel" target="_blank"><i class="bi bi-printer"></i> Print selected</a>
                        <button class="ui-btn sm primary" form="bulkForm"><i class="bi bi-check2-all"></i> Mark as printed</button>
                    @else
                        <button class="ui-btn sm primary" form="bulkForm" onclick="return confirm('Mark the selected cards as ready? Holders with a phone number get an SMS.')"><i class="bi bi-box-seam"></i> Mark ready &amp; send SMS</button>
                    @endif
                </div>
            @endif

            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            @if ($bulk)<th style="width:36px"><input type="checkbox" class="cd-cb" id="bulkAll" aria-label="Select all"></th>@endif
                            <th>Holder</th>
                            <th>NIN</th>
                            <th>{{ ['to_print' => 'Approved', 'printed' => 'Printed', 'ready' => 'Ready since', 'collected' => 'Collected'][$tab] }}</th>
                            @if ($tab === 'collected')<th>Collected by</th>@endif
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($people as $p)
                            @php $when = ['to_print' => $p->reviewed_at, 'printed' => $p->card_printed_at, 'ready' => $p->card_ready_at, 'collected' => $p->card_collected_at][$tab]; @endphp
                            <tr>
                                @if ($bulk)<td><input type="checkbox" class="cd-cb bulk-cb" name="ids[]" value="{{ $p->id }}" form="bulkForm" aria-label="Select {{ $p->full_name }}"></td>@endif
                                <td>
                                    <a href="{{ route('people.show', $p) }}#id-card" class="ui-who" style="text-decoration:none;color:inherit">
                                        <x-avatar :person="$p" :size="34" />
                                        <div><b>{{ $p->full_name }}@if ($p->card_version > 1)<span class="ver" title="Replacement card">#{{ $p->card_version }}</span>@endif</b><small>{{ $p->reference }} · {{ $p->phone ?: 'no phone' }}</small></div>
                                    </a>
                                </td>
                                <td class="ui-mono">{{ $p->nin }}</td>
                                <td>
                                    <span title="{{ $when?->format('d M Y H:i') }}">{{ $when?->diffForHumans() ?? '—' }}</span>
                                    @if ($tab === 'ready' && $p->card_ready_at && $p->card_ready_at->lt(now()->subDays(30)))<small style="display:block;color:#b45309">Not collected for 30+ days</small>@endif
                                </td>
                                @if ($tab === 'collected')<td>{{ $p->card_collected_by ?: '—' }}</td>@endif
                                <td>
                                    <div class="row-actions">
                                        @if ($tab === 'ready')
                                            <a href="{{ route('people.show', $p) }}#id-card" class="ui-btn sm primary"><i class="bi bi-person-check"></i> Hand over</a>
                                        @elseif ($tab === 'to_print')
                                            <a href="{{ route('cards.show', $p) }}" target="_blank" class="ui-btn sm"><i class="bi bi-printer"></i> Print</a>
                                        @else
                                            <a href="{{ route('people.show', $p) }}#id-card" class="ui-btn sm">Open</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding:16px 22px">@include('partials.pager', ['paginator' => $people])</div>
        @endif
    </section>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const all = document.getElementById('bulkAll');
    const bar = document.getElementById('bulkBar');
    if (!all || !bar) return;
    const boxes = () => [...document.querySelectorAll('.bulk-cb')];
    const printSel = document.getElementById('printSel');
    const base = @json(route('cards.print'));
    const refresh = () => {
        const ids = boxes().filter((b) => b.checked).map((b) => b.value);
        document.getElementById('bulkCount').textContent = ids.length;
        bar.classList.toggle('on', ids.length > 0);
        all.checked = ids.length > 0 && ids.length === boxes().length;
        if (printSel) printSel.href = base + '?ids=' + ids.slice(0, 40).join(',');
    };
    all.addEventListener('change', () => { boxes().forEach((b) => b.checked = all.checked); refresh(); });
    boxes().forEach((b) => b.addEventListener('change', refresh));
})();
</script>
@endpush
