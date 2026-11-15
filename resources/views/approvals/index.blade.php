@extends('layouts.app')

@section('title', 'Approvals')
@section('page_title', 'Approvals')

@use('App\Models\Person')
@php
    $tabLabels = [
        'pending' => ['Waiting', 'bi-hourglass-split'],
        'mine' => ['Reviewing by me', 'bi-search'],
        'returned' => ['Returned', 'bi-arrow-return-left'],
        'rejected' => ['Rejected', 'bi-x-octagon'],
        'approved' => ['Approved', 'bi-patch-check'],
    ];
    $queue = in_array($tab, ['pending', 'mine'], true);
@endphp

@push('styles')
<style>
    .ap-toolbar { justify-content: space-between; }
    .ap-filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .ap-filters .ui-select { width: auto; min-width: 170px; height: 38px; }
    .ui-seg a .n { display: inline-block; min-width: 20px; padding: 0 6px; margin-left: 4px; border-radius: 999px; background: #e2e8f0; color: var(--text); font-size: 11.5px; text-align: center; }
    .ui-seg a.on .n { background: var(--primary-soft); color: var(--primary); }
    .wait { font-size: 12.5px; }
    .wait.late { color: #b45309; font-weight: 700; }
    .wait.very-late { color: #b91c1c; font-weight: 700; }
    .ap-cb { width: 17px; height: 17px; accent-color: #16a34a; }
    .bulk-bar { display: none; align-items: center; gap: 12px; flex-wrap: wrap; padding: 12px 22px; background: #f0fdf4; border-top: 1px solid #bbf7d0; border-bottom: 1px solid #bbf7d0; font-size: 13px; }
    .bulk-bar.on { display: flex; }
    .bulk-bar label { display: flex; gap: 8px; align-items: center; }
    .bulk-bar .ui-btn { margin-left: auto; }
    .row-actions form { margin: 0; }
    .note-cell { max-width: 280px; min-width: 180px; font-size: 12.5px; color: var(--muted); white-space: normal !important; line-height: 1.45; }
</style>
@endpush

@section('content')
<div class="ui-page">

    <div class="ui-head">
        <div>
            <h2>Approvals</h2>
            <p>Review registrations before the NIN is issued. You cannot approve records you registered yourself.</p>
        </div>
    </div>

    <div class="ui-stats">
        <div class="ui-stat"><span class="ico b"><i class="bi bi-hourglass-split"></i></span><div><small>Waiting for approval</small><b>{{ number_format($counts['pending']) }}</b></div></div>
        <div class="ui-stat"><span class="ico a"><i class="bi bi-search"></i></span><div><small>I am reviewing</small><b>{{ number_format($counts['mine']) }}</b></div></div>
        <div class="ui-stat"><span class="ico"><i class="bi bi-arrow-return-left"></i></span><div><small>Returned for correction</small><b>{{ number_format($counts['returned']) }}</b></div></div>
        <div class="ui-stat"><span class="ico g"><i class="bi bi-patch-check"></i></span><div><small>Approved (via workflow)</small><b>{{ number_format($counts['approved']) }}</b></div></div>
    </div>

    <div class="ui-toolbar ap-toolbar">
        <nav class="ui-seg">
            @foreach ($tabLabels as $key => [$label, $icon])
                <a href="{{ route('approvals.index', array_filter(['tab' => $key, 'region_id' => $regionId])) }}" class="{{ $tab === $key ? 'on' : '' }}">
                    <i class="bi {{ $icon }}"></i> {{ $label }} <span class="n">{{ $counts[$key] }}</span>
                </a>
            @endforeach
        </nav>

        <form class="ap-filters" method="GET" action="{{ route('approvals.index') }}">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <select name="region_id" class="ui-select" onchange="this.form.submit()" aria-label="Region">
                <option value="">All regions</option>
                @foreach ($regions as $r)
                    <option value="{{ $r->id }}" @selected($regionId === $r->id)>{{ $r->name }}</option>
                @endforeach
            </select>
            @if ($tab === 'pending')
                <select name="waited" class="ui-select" onchange="this.form.submit()" aria-label="Waiting time">
                    <option value="0">Any waiting time</option>
                    @foreach ([1 => 'Waiting 1+ day', 3 => 'Waiting 3+ days', 7 => 'Waiting 7+ days'] as $d => $l)
                        <option value="{{ $d }}" @selected($waited === $d)>{{ $l }}</option>
                    @endforeach
                </select>
            @endif
        </form>
    </div>

    <section class="ui-card flush">
        @if ($people->isEmpty())
            <div class="ui-empty">
                <i class="bi bi-inbox"></i>
                {{ $tab === 'pending' ? 'Nothing is waiting for approval. 🎉' : 'No records here.' }}
            </div>
        @else
            @if ($tab === 'pending')
                <form method="POST" action="{{ route('approvals.bulk') }}" id="bulkForm" onsubmit="return confirm('Approve the selected records and issue their NINs?')">
                    @csrf
                    <div class="bulk-bar" id="bulkBar">
                        <b><span id="bulkCount">0</span> selected</b>
                        <label><input type="checkbox" name="confirm_checked" value="1" class="ap-cb" required> I checked the photo, signature, duplicates, details and address of every selected record.</label>
                        <button class="ui-btn sm primary"><i class="bi bi-patch-check"></i> Approve selected</button>
                    </div>
                </form>
            @endif

            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            @if ($tab === 'pending')<th style="width:36px"><input type="checkbox" class="ap-cb" id="bulkAll" aria-label="Select all"></th>@endif
                            <th>Person</th>
                            <th>Registered by</th>
                            <th>{{ $queue ? 'Waiting' : 'Decided' }}</th>
                            <th>Stage</th>
                            @unless ($queue)<th>Comment</th>@endunless
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($people as $p)
                            @php
                                $canReview = $p->canBeReviewedBy($user);
                                $days = $p->submitted_at ? (int) $p->submitted_at->diffInDays(now()) : 0;
                            @endphp
                            <tr>
                                @if ($tab === 'pending')
                                    <td>
                                        @if ($canReview && $p->stage === Person::SUBMITTED)
                                            <input type="checkbox" class="ap-cb bulk-cb" name="ids[]" value="{{ $p->id }}" form="bulkForm" aria-label="Select {{ $p->full_name }}">
                                        @endif
                                    </td>
                                @endif
                                <td>
                                    <a href="{{ route('people.show', $p) }}" class="ui-who" style="text-decoration:none;color:inherit">
                                        <x-avatar :person="$p" :size="34" />
                                        <div><b>{{ $p->full_name }}</b><small>{{ $p->gender }} · {{ $p->date_of_birth?->format('d M Y') }} · {{ $p->region ?: '—' }}</small></div>
                                    </a>
                                </td>
                                <td>
                                    {{ $p->creator?->name ?? '—' }}
                                    @if ($p->submitter && $p->submitter->id !== $p->creator?->id)<small style="display:block;color:var(--muted)">submitted by {{ $p->submitter->name }}</small>@endif
                                </td>
                                <td>
                                    @if ($queue)
                                        <span class="wait {{ $days >= 7 ? 'very-late' : ($days >= 3 ? 'late' : '') }}" title="{{ $p->submitted_at?->format('d M Y H:i') }}">{{ $p->submitted_at?->diffForHumans() ?? '—' }}</span>
                                    @else
                                        <span title="{{ $p->reviewed_at?->format('d M Y H:i') }}">{{ $p->reviewed_at?->diffForHumans() ?? '—' }}</span>
                                        @if ($p->reviewer)<small style="display:block;color:var(--muted)">by {{ $p->reviewer->name }}</small>@endif
                                    @endif
                                </td>
                                <td>
                                    <span class="ui-pill {{ $p->stageTone() }}"><i class="bi {{ $p->stageIcon() }}"></i> {{ $p->stageLabel() }}</span>
                                    @if ($p->stage === Person::IN_REVIEW && $p->reviewer)<small style="display:block;color:var(--muted);margin-top:3px">{{ $p->reviewer_id === $user->id ? 'by you' : 'by ' . $p->reviewer->name }}</small>@endif
                                </td>
                                @unless ($queue)<td class="note-cell">{{ $p->review_note ?: '—' }}</td>@endunless
                                <td>
                                    <div class="row-actions">
                                        @if ($canReview && $p->stage === Person::SUBMITTED)
                                            <form method="POST" action="{{ route('approvals.claim', $p) }}">@csrf<button class="ui-btn sm primary"><i class="bi bi-search"></i> Review</button></form>
                                        @elseif ($canReview)
                                            <a href="{{ route('people.show', $p) }}#review" class="ui-btn sm primary"><i class="bi bi-list-check"></i> Continue</a>
                                        @else
                                            <a href="{{ route('people.show', $p) }}" class="ui-btn sm" title="{{ $p->isOwnWork($user) && $queue ? 'You registered this record' : '' }}">
                                                @if ($queue && $p->isOwnWork($user))<i class="bi bi-person-lock"></i>@endif Open
                                            </a>
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
    const refresh = () => {
        const n = boxes().filter((b) => b.checked).length;
        document.getElementById('bulkCount').textContent = n;
        bar.classList.toggle('on', n > 0);
        all.checked = n > 0 && n === boxes().length;
    };
    all.addEventListener('change', () => { boxes().forEach((b) => b.checked = all.checked); refresh(); });
    boxes().forEach((b) => b.addEventListener('change', refresh));
    if (!boxes().length) all.disabled = true;
})();
</script>
@endpush
