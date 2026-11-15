@extends('layouts.app')

@section('title', 'My Submissions')
@section('page_title', 'My Submissions')

@use('App\Models\Person')
@php
    $total = $counts->sum();
    $order = [Person::RETURNED, Person::DRAFT, Person::SUBMITTED, Person::IN_REVIEW, Person::APPROVED, Person::REJECTED];
@endphp

@push('styles')
<style>
    .ui-seg a .n { display: inline-block; min-width: 20px; padding: 0 6px; margin-left: 4px; border-radius: 999px; background: #e2e8f0; color: var(--text); font-size: 11.5px; text-align: center; }
    .ui-seg a.on .n { background: var(--primary-soft); color: var(--primary); }
    .ui-seg a.attn .n { background: #fef3c7; color: #92400e; }
    .row-actions form { margin: 0; }
    .note-cell { max-width: 300px; min-width: 200px; font-size: 12.5px; white-space: normal !important; line-height: 1.45; }
    .row-actions { flex-wrap: nowrap; }
    .note-cell.warn { color: #92400e; }
    .note-cell.bad { color: #991b1b; }
    tr.needs-me td:first-child { box-shadow: inset 3px 0 0 #f59e0b; }
</style>
@endpush

@section('content')
<div class="ui-page">

    <div class="ui-head">
        <div>
            <h2>My submissions</h2>
            <p>Records you registered or submitted, and where each one is in the approval process.</p>
        </div>
        <div class="ui-head-actions">
            <a href="{{ route('people.create') }}" class="ui-btn primary"><i class="bi bi-person-plus"></i> Register new person</a>
        </div>
    </div>

    <div class="ui-stats">
        <div class="ui-stat"><span class="ico a"><i class="bi bi-arrow-return-left"></i></span><div><small>Need my correction</small><b>{{ number_format($counts[Person::RETURNED] ?? 0) }}</b></div></div>
        <div class="ui-stat"><span class="ico"><i class="bi bi-pencil"></i></span><div><small>Drafts (not submitted)</small><b>{{ number_format($counts[Person::DRAFT] ?? 0) }}</b></div></div>
        <div class="ui-stat"><span class="ico b"><i class="bi bi-hourglass-split"></i></span><div><small>Waiting for approval</small><b>{{ number_format(($counts[Person::SUBMITTED] ?? 0) + ($counts[Person::IN_REVIEW] ?? 0)) }}</b></div></div>
        <div class="ui-stat"><span class="ico g"><i class="bi bi-patch-check"></i></span><div><small>Approved</small><b>{{ number_format($counts[Person::APPROVED] ?? 0) }}</b></div></div>
    </div>

    <div class="ui-toolbar">
        <nav class="ui-seg">
            <a href="{{ route('approvals.mine') }}" class="{{ $stage === null ? 'on' : '' }}">All <span class="n">{{ $total }}</span></a>
            @foreach ($order as $s)
                <a href="{{ route('approvals.mine', ['stage' => $s]) }}" class="{{ $stage === $s ? 'on' : '' }} {{ in_array($s, [Person::RETURNED, Person::DRAFT], true) && ($counts[$s] ?? 0) ? 'attn' : '' }}">
                    <i class="bi {{ Person::STAGES[$s][2] }}"></i> {{ Person::STAGES[$s][0] }} <span class="n">{{ $counts[$s] ?? 0 }}</span>
                </a>
            @endforeach
        </nav>
    </div>

    <section class="ui-card flush">
        @if ($people->isEmpty())
            <div class="ui-empty">
                <i class="bi bi-inboxes"></i>
                {{ $stage ? 'No records at this stage.' : 'You have not registered anyone yet.' }}
            </div>
        @else
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead><tr><th>Person</th><th>Stage</th><th>Comment from approver</th><th>Last change</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($people as $p)
                            @php $needsMe = in_array($p->stage, [Person::RETURNED, Person::DRAFT], true); @endphp
                            <tr class="{{ $needsMe ? 'needs-me' : '' }}">
                                <td>
                                    <a href="{{ route('people.show', $p) }}" class="ui-who" style="text-decoration:none;color:inherit">
                                        <x-avatar :person="$p" :size="34" />
                                        <div><b>{{ $p->full_name }}</b><small>{{ $p->nin ? 'NIN ' . $p->nin : 'NIN issued on approval' }}</small></div>
                                    </a>
                                </td>
                                <td>
                                    <span class="ui-pill {{ $p->stageTone() }}"><i class="bi {{ $p->stageIcon() }}"></i> {{ $p->stageLabel() }}</span>
                                    @if ($p->stage === Person::IN_REVIEW && $p->reviewer)<small style="display:block;color:var(--muted);margin-top:3px">by {{ $p->reviewer->name }}</small>@endif
                                </td>
                                <td class="note-cell {{ $p->stage === Person::RETURNED ? 'warn' : ($p->stage === Person::REJECTED ? 'bad' : '') }}">
                                    {{ in_array($p->stage, [Person::RETURNED, Person::REJECTED, Person::APPROVED], true) && $p->review_note ? $p->review_note : '—' }}
                                </td>
                                <td title="{{ $p->updated_at?->format('d M Y H:i') }}">{{ $p->updated_at?->diffForHumans() }}</td>
                                <td>
                                    <div class="row-actions">
                                        @if ($needsMe)
                                            <a href="{{ route('people.edit', $p) }}" class="ui-btn sm"><i class="bi bi-pencil-square"></i> {{ $p->stage === Person::RETURNED ? 'Fix' : 'Edit' }}</a>
                                            <form method="POST" action="{{ route('approvals.submit', $p) }}">@csrf<button class="ui-btn sm primary"><i class="bi bi-send"></i> {{ $p->stage === Person::RETURNED ? 'Resubmit' : 'Submit' }}</button></form>
                                        @else
                                            <a href="{{ route('people.show', $p) }}" class="ui-btn sm">Open</a>
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
