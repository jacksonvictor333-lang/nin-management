@extends('layouts.app')

@section('title', 'Data Quality')
@section('page_title', 'Data Quality')

@php
    $isAdmin = (auth()->user()->role ?? 'Administrator') === 'Administrator';
    $canEdit = in_array(auth()->user()->role ?? 'Administrator', ['Administrator', 'Registrar'], true);
    $sev = [
        'serious' => ['bad', 'Serious'],
        'warning' => ['warn', 'Warning'],
        'info' => ['info', 'Info'],
    ];
@endphp

@push('styles')
<style>
    .dq-score { display: flex; align-items: center; gap: 22px; }
    .ring { --p: 0; width: 104px; height: 104px; border-radius: 50%; flex-shrink: 0;
        background: conic-gradient(#16a34a calc(var(--p) * 1%), #dcfce7 0);
        display: grid; place-items: center; }
    .ring::before { content: ''; grid-area: 1/1; width: 80px; height: 80px; border-radius: 50%; background: #fff; }
    .ring b { grid-area: 1/1; font-size: 24px; font-weight: 800; color: var(--ink); z-index: 1; }
    .dq-score h3 { margin: 0 0 4px; font-size: 17px; color: var(--ink); }
    .dq-score p { margin: 0; font-size: 13.5px; color: var(--muted); line-height: 1.5; }

    .issue { border: 1px solid var(--border); border-radius: 16px; background: #fff; overflow: hidden; }
    .issue + .issue { margin-top: 12px; }
    .issue summary { list-style: none; display: flex; align-items: center; gap: 14px; padding: 16px 18px; cursor: pointer; }
    .issue summary::-webkit-details-marker { display: none; }
    .issue summary:hover { background: #fafafa; }
    .issue .ico { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; font-size: 18px; background: #f1f5f9; color: var(--text); flex-shrink: 0; }
    .issue.serious .ico { background: #fee2e2; color: #b91c1c; }
    .issue.warning .ico { background: #fef3c7; color: #b45309; }
    .issue.info .ico { background: #dbeafe; color: #1d4ed8; }
    .issue.clear .ico { background: #dcfce7; color: #15803d; }
    .issue h4 { margin: 0; font-size: 14.5px; color: var(--ink); }
    .issue summary p { margin: 2px 0 0; font-size: 12.5px; color: var(--muted); }
    .issue .count { margin-left: auto; display: flex; align-items: center; gap: 10px; }
    .issue .count b { font-size: 20px; color: var(--ink); min-width: 28px; text-align: right; }
    .issue .chev { color: var(--muted); transition: transform .2s; }
    .issue[open] .chev { transform: rotate(180deg); }
    .issue-body { border-top: 1px solid var(--border); }
    .issue-actions { display: flex; align-items: center; gap: 10px; padding: 12px 18px; background: #f8fafc; border-bottom: 1px solid var(--border); flex-wrap: wrap; font-size: 12.5px; color: var(--muted); }
    .issue-actions form { margin-left: auto; }
    .issue-list { max-height: 360px; overflow-y: auto; }
</style>
@endpush

@section('content')
<div class="ui-page">

    <div class="ui-head">
        <div>
            <h2>Data quality</h2>
            <p>Problems found in registered records, and quick ways to fix them.</p>
        </div>
    </div>

    <section class="ui-card">
        <div class="dq-score">
            <div class="ring" style="--p: {{ $score }}"><b>{{ $score }}%</b></div>
            <div>
                <h3>{{ $score >= 90 ? 'Excellent' : ($score >= 70 ? 'Good, with some gaps' : 'Needs attention') }}</h3>
                <p>{{ number_format($total - $affected) }} of {{ number_format($total) }} records have no issues.
                   {{ number_format($affected) }} {{ Str::plural('record', $affected) }} {{ $affected === 1 ? 'has' : 'have' }} at least one issue below.</p>
            </div>
        </div>
    </section>

    <div>
        @foreach ($issues as $key => $issue)
            @php $n = $issue['people']->count(); @endphp
            <details class="issue {{ $n ? $issue['severity'] : 'clear' }}" {{ $n && $loop->index < 2 ? 'open' : '' }}>
                <summary>
                    <span class="ico"><i class="bi {{ $n ? $issue['icon'] : 'bi-check2-circle' }}"></i></span>
                    <div>
                        <h4>{{ $issue['title'] }}</h4>
                        <p>{{ $n ? $issue['hint'] : 'No records with this issue.' }}</p>
                    </div>
                    <div class="count">
                        @if ($n)<span class="ui-pill {{ $sev[$issue['severity']][0] }}">{{ $sev[$issue['severity']][1] }}</span>@endif
                        <b>{{ $n }}</b>
                        <i class="bi bi-chevron-down chev"></i>
                    </div>
                </summary>

                @if ($n)
                    <div class="issue-body">
                        @if (!empty($issue['fix']))
                            <div class="issue-actions">
                                <i class="bi bi-magic"></i>
                                <span>This issue can be fixed automatically. Unmatched records are left untouched.</span>
                                @if ($isAdmin)
                                    <form method="POST" action="{{ route($issue['fix'] === 'regions' ? 'data-quality.fix-regions' : 'data-quality.fix-names') }}"
                                          onsubmit="return confirm('Apply this fix to all matching records? The change is recorded in the Activity Log.')">
                                        @csrf
                                        <button class="ui-btn primary sm"><i class="bi bi-magic"></i> Fix automatically</button>
                                    </form>
                                @else
                                    <span class="ui-pill" style="margin-left:auto">Administrators only</span>
                                @endif
                            </div>
                        @endif

                        @if ($key === 'duplicates')
                        <div class="issue-list ui-table-wrap">
                            <table class="ui-table">
                                <tbody>
                                    @foreach ($issue['pairs'] as $pair)
                                        <tr>
                                            @foreach (['a', 'b'] as $side)
                                                @php $p = $pair[$side]; @endphp
                                                <td>
                                                    <a class="ui-who" href="{{ route('people.show', $p) }}" style="text-decoration:none">
                                                        <x-avatar :person="$p" :size="32" />
                                                        <span><b>{{ $p->full_name }}</b><small class="ui-mono" style="color:var(--muted)">{{ $p->nin }} · {{ $p->date_of_birth?->format('d M Y') }}</small></span>
                                                    </a>
                                                </td>
                                                @if ($side === 'a')<td style="width:28px;color:#cbd5e1;text-align:center"><i class="bi bi-arrow-left-right"></i></td>@endif
                                            @endforeach
                                            <td class="num">
                                                @foreach (array_unique($pair['reasons']) as $r)<span class="ui-pill bad" style="font-size:11px;margin:1px">{{ $r }}</span>@endforeach
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p style="margin:10px 2px 0;font-size:12.5px;color:var(--muted)"><i class="bi bi-info-circle"></i> If two records are the same person, keep the correct one and archive the other with the reason "Duplicate of NIN …".</p>
                        @else
                        <div class="issue-list ui-table-wrap">
                            <table class="ui-table">
                                <tbody>
                                    @foreach ($issue['people'] as $p)
                                        <tr>
                                            <td>
                                                <div class="ui-who">
                                                    <x-avatar :person="$p" :size="32" />
                                                    <span><b>{{ $p->full_name }}</b><small class="ui-mono" style="color:var(--muted)">{{ $p->nin }}</small></span>
                                                </div>
                                            </td>
                                            <td style="color:var(--muted);font-size:12.5px">
                                                @switch($key)
                                                    @case('region_mismatch') Region: <b style="color:var(--ink)">{{ $p->region ?: '—' }}</b>@if($p->birth_region) · Birth: <b style="color:var(--ink)">{{ $p->birth_region }}</b>@endif @break
                                                    @case('unlinked_village') Village: <b style="color:var(--ink)">{{ $p->village }}</b> in {{ $p->ward ?: '—' }} @break
                                                    @case('missing_file') File: <span class="ui-mono">{{ $p->profile_picture }}</span> @break
                                                    @default {{ $p->region ?: '' }}
                                                @endswitch
                                            </td>
                                            <td class="num">
                                                @if (in_array($key, ['no_photo', 'missing_file']) && $canEdit)
                                                    <a href="{{ route('people.show', $p) }}#photo" class="ui-btn sm"><i class="bi bi-camera"></i> Add photo</a>
                                                @elseif ($canEdit && in_array($key, ['no_residence', 'unlinked_village', 'no_contact', 'region_mismatch']))
                                                    <a href="{{ route('people.edit', $p) }}" class="ui-btn sm"><i class="bi bi-pencil"></i> Edit</a>
                                                @else
                                                    <a href="{{ route('people.show', $p) }}" class="ui-btn sm">View</a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>
                @endif
            </details>
        @endforeach
    </div>
</div>
@endsection
