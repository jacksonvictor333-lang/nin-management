{{-- Hatua za kadi chini ya kitambulisho kwenye profile. Vigezo: $person --}}
@use('App\Models\Person')
@php
    $me = auth()->user();
    $manage = $me->canRegister() && $person->isApproved() && ! $person->isDeactivated() && ($person->status ?? 'Active') === 'Active';
    $events = $person->cardEvents;
    $stageKey = $person->cardStageKey();
    $order = array_keys(Person::CARD_STAGES);
    $pos = array_search($stageKey, $order, true);
    $eventText = ['printed' => 'Printed', 'ready' => 'Ready for collection', 'collected' => 'Collected', 'lost' => 'Reported lost', 'damaged' => 'Reported damaged', 'stolen' => 'Reported stolen'];
@endphp

@push('styles')
<style>
    .cl { margin-top: 18px; border-top: 1px solid #eef2f7; padding-top: 18px; }
    .cl-steps { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 6px; }
    .cl-step { padding: 10px 12px; border-radius: 12px; background: #f8fafc; border: 1px solid #eef2f7; font-size: 12.5px; color: #94a3b8; min-width: 0; }
    .cl-step b { display: block; font-size: 13px; }
    .cl-step small { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cl-step.done { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
    .cl-step.now { background: #fff; border-color: #16a34a; color: #15803d; box-shadow: 0 0 0 3px #dcfce7; }
    .cl-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end; margin-top: 14px; }
    .cl-actions form { margin: 0; display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end; }
    .cl-actions .ui-input { width: 240px; max-width: 100%; }
    .cl-log { margin-top: 12px; font-size: 12.5px; color: #64748b; }
    .cl-log summary { cursor: pointer; font-weight: 600; color: #15803d; }
    .cl-log li { margin: 6px 0 0 16px; }
    @media (max-width: 640px) { .cl-steps { grid-template-columns: repeat(2, 1fr); } }
    @media print { .cl { display: none; } }
</style>
@endpush

@if ($person->isApproved() && $person->nin)
<div class="cl no-print" id="card-status">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;flex-wrap:wrap">
        <b style="font-size:14px;color:#0f172a"><i class="bi bi-credit-card-2-front"></i> Card #{{ $person->card_version }}</b>
        <span class="ui-pill {{ Person::CARD_STAGES[$stageKey][1] }}"><i class="bi {{ Person::CARD_STAGES[$stageKey][2] }}"></i> {{ $person->cardStageLabel() }}</span>
        @if ($person->card_version > 1)<span class="ui-pill warn">Replacement</span>@endif
    </div>

    <div class="cl-steps">
        @foreach (Person::CARD_STAGES as $key => [$label])
            @php
                $i = array_search($key, $order, true);
                $at = ['to_print' => $person->reviewed_at, 'printed' => $person->card_printed_at, 'ready' => $person->card_ready_at, 'collected' => $person->card_collected_at][$key];
                $cls = $i < $pos || ($key === 'collected' && $stageKey === 'collected') ? 'done' : ($i === $pos ? 'now' : '');
            @endphp
            <div class="cl-step {{ $cls }}">
                <b>{{ $key === 'to_print' ? 'Approved' : $label }}</b>
                <small>{{ $at && $i <= $pos ? $at->format('d M Y, H:i') : '—' }}</small>
                @if ($key === 'collected' && $person->card_collected_by)<small>by {{ $person->card_collected_by }}</small>@endif
            </div>
        @endforeach
    </div>

    @if ($manage)
        <div class="cl-actions">
            @switch($person->card_status)
                @case(null)
                    <a href="{{ route('cards.show', $person) }}" target="_blank" class="ui-btn sm"><i class="bi bi-printer"></i> Print card</a>
                    <form method="POST" action="{{ route('cards.status', $person) }}">@csrf<input type="hidden" name="action" value="printed"><button class="ui-btn sm primary"><i class="bi bi-check2"></i> Mark as printed</button></form>
                    @break
                @case('printed')
                    <form method="POST" action="{{ route('cards.status', $person) }}">@csrf<input type="hidden" name="action" value="ready"><button class="ui-btn sm primary"><i class="bi bi-box-seam"></i> Ready for collection{{ $person->phone ? ' (send SMS)' : '' }}</button></form>
                    @break
                @case('ready')
                    <form method="POST" action="{{ route('cards.status', $person) }}">
                        @csrf
                        <input type="hidden" name="action" value="collected">
                        <div class="ui-field">
                            <label for="collectedBy" style="font-size:12px">Collected by</label>
                            <input class="ui-input {{ $errors->has('collected_by') ? 'is-invalid' : '' }}" id="collectedBy" name="collected_by" value="{{ old('collected_by', $person->full_name) }}" maxlength="150" required>
                        </div>
                        <div class="ui-field">
                            <label for="collectNote" style="font-size:12px">Note <span class="ui-help">(optional, e.g. relationship)</span></label>
                            <input class="ui-input" id="collectNote" name="note" maxlength="300" placeholder="Holder in person">
                        </div>
                        <button class="ui-btn sm primary"><i class="bi bi-person-check"></i> Hand over card</button>
                    </form>
                    @error('collected_by')<div class="ui-err" style="width:100%">{{ $message }}</div>@enderror
                    @break
            @endswitch
            @if ($person->card_status !== null)
                <button type="button" class="ui-btn sm danger" onclick="document.getElementById('replaceDialog').showModal()"><i class="bi bi-exclamation-octagon"></i> Lost / damaged</button>
            @endif
        </div>
    @endif

    @if ($events->isNotEmpty())
        <details class="cl-log">
            <summary>Card history ({{ $events->count() }})</summary>
            <ul>
                @foreach ($events->reverse() as $e)
                    <li><b>Card #{{ $e->card_version }}</b> — {{ $eventText[$e->event] ?? $e->event }}{{ $e->note ? ': ' . $e->note : '' }} · {{ $e->created_at?->format('d M Y H:i') }}{{ $e->user ? ' · ' . $e->user->name : '' }}</li>
                @endforeach
            </ul>
        </details>
    @endif
</div>

@if ($manage && $person->card_status !== null)
<dialog class="ui-dialog no-print" id="replaceDialog" @if ($errors->getBag('replace')->any()) data-open @endif>
    <form method="POST" action="{{ route('cards.replace', $person) }}">
        @csrf
        <div class="ui-dialog-head"><h3><i class="bi bi-exclamation-octagon" style="color:#b91c1c"></i> Replace card #{{ $person->card_version }}</h3><button type="button" class="ui-btn icon sm" onclick="this.closest('dialog').close()" aria-label="Close"><i class="bi bi-x-lg"></i></button></div>
        <div class="ui-dialog-body" style="display:grid;gap:14px">
            <p style="margin:0;font-size:13.5px;color:#64748b">The current card is recorded as no longer valid and card #{{ $person->card_version + 1 }} goes to the print queue. The NIN does not change.</p>
            <div class="ui-field">
                <label>What happened?</label>
                <div class="ui-seg" style="width:fit-content">
                    @foreach (Person::CARD_LOSS_REASONS as $k => $l)
                        <label style="display:flex;gap:6px;align-items:center;padding:6px 12px;font-size:13px;cursor:pointer"><input type="radio" name="reason" value="{{ $k }}" @checked(old('reason', 'lost') === $k)> {{ $l }}</label>
                    @endforeach
                </div>
            </div>
            <div class="ui-field">
                <label for="replaceNote">Note <span class="ui-help">(e.g. police report number)</span></label>
                <input class="ui-input" id="replaceNote" name="note" maxlength="300" value="{{ old('note') }}">
            </div>
        </div>
        <div class="ui-dialog-foot">
            <button type="button" class="ui-btn" onclick="this.closest('dialog').close()">Cancel</button>
            <button class="ui-btn primary"><i class="bi bi-arrow-repeat"></i> Queue replacement</button>
        </div>
    </form>
</dialog>
@endif
@endif
