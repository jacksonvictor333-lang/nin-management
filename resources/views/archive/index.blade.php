@extends('layouts.app')

@section('title', 'Archive')
@section('page_title', 'Archive')

@section('content')
<div class="ui-page">

    <div class="ui-head">
        <div>
            <h2>Archive</h2>
            <p>Records removed from the active register. They are hidden from search, the verification API and card QR checks until restored.</p>
        </div>
    </div>

    <section class="ui-card flush">
        <div class="ui-card-head">
            <span class="ico"><i class="bi bi-archive"></i></span>
            <div><h3>{{ number_format($people->total()) }} archived {{ Str::plural('record', $people->total()) }}</h3><p>Most recently archived first</p></div>
            <form class="end" method="GET" action="{{ route('archive.index') }}" style="width:280px;max-width:100%">
                <div class="ui-search"><i class="bi bi-search"></i><input class="ui-input" type="search" name="q" value="{{ $search }}" placeholder="Name or NIN…"></div>
            </form>
        </div>

        @if ($people->isEmpty())
            <div class="ui-empty" style="border-top:1px solid var(--border);margin-top:16px">
                <i class="bi bi-archive"></i>
                {{ $search !== '' ? 'No archived record matches "' . $search . '".' : 'The archive is empty.' }}
            </div>
        @else
            <div class="ui-table-wrap" style="margin-top:16px">
                <table class="ui-table">
                    <thead><tr><th>Person</th><th>NIN</th><th>Reason</th><th>Archived</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($people as $p)
                            <tr>
                                <td>
                                    <div class="ui-who">
                                        <x-avatar :person="$p" :size="34" />
                                        <div><b>{{ $p->full_name }}</b><small>{{ $p->gender }} · {{ $p->region ?: '—' }}</small></div>
                                    </div>
                                </td>
                                <td class="ui-mono">{{ $p->nin }}</td>
                                <td style="max-width:280px">{{ $p->archive_reason ?: '—' }}</td>
                                <td title="{{ $p->deleted_at->format('d M Y H:i') }}">{{ $p->deleted_at->diffForHumans() }}</td>
                                <td style="text-align:right">
                                    <form method="POST" action="{{ route('archive.restore', $p->id) }}" onsubmit="return confirm('Restore {{ addslashes($p->full_name) }} to the active register?')">
                                        @csrf @method('PATCH')
                                        <button class="ui-btn sm"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>
                                    </form>
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
