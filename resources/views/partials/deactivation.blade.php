{{-- Kusitisha / kurejesha NIN (Administrator). Vigezo: $person, $isAdmin --}}
@use('App\Models\Person')

@push('styles')
<style>
    .deact { margin-top: 22px; display: flex; gap: 14px; align-items: flex-start; padding: 18px 20px; border-radius: 18px; background: #fef2f2; border: 1px solid #fecaca; color: #7f1d1d; }
    .deact > i { font-size: 24px; }
    .deact b { display: block; font-size: 15px; }
    .deact p { margin: 4px 0 0; font-size: 13.5px; line-height: 1.5; }
    .deact small { display: block; margin-top: 4px; font-size: 12px; color: #991b1b; opacity: .8; }
    .deact .ui-btn { margin-left: auto; flex-shrink: 0; }
</style>
@endpush

@if ($person->isDeactivated())
    <section class="deact" role="status">
        <i class="bi {{ $person->deactivation_reason === 'deceased' ? 'bi-flower1' : 'bi-slash-circle' }}"></i>
        <div>
            <b>NIN deactivated — {{ $person->deactivationLabel() }}</b>
            <p>
                @if ($person->date_of_death)Date of death: <strong>{{ $person->date_of_death->format('d M Y') }}</strong>. @endif
                {{ $person->deactivation_note }}
            </p>
            <small>{{ $person->deactivated_at?->format('d M Y H:i') }}{{ $person->deactivator ? ' by ' . $person->deactivator->name : '' }} · The NIN no longer verifies through the API or the card QR code.</small>
        </div>
        @if ($isAdmin)
            <button type="button" class="ui-btn sm no-print" onclick="document.getElementById('reactivateDialog').showModal()"><i class="bi bi-arrow-counterclockwise"></i> Reactivate</button>
        @endif
    </section>
@endif

@if ($isAdmin && $person->isApproved() && ! $person->isDeactivated())
<dialog class="ui-dialog no-print" id="deactivateDialog" @if ($errors->getBag('deactivate')->any()) data-open @endif>
    @php $b = $errors->getBag('deactivate'); @endphp
    <form method="POST" action="{{ route('people.deactivate', $person) }}">
        @csrf
        <div class="ui-dialog-head"><h3><i class="bi bi-slash-circle" style="color:#b91c1c"></i> Deactivate NIN</h3><button type="button" class="ui-btn icon sm" onclick="this.closest('dialog').close()" aria-label="Close"><i class="bi bi-x-lg"></i></button></div>
        <div class="ui-dialog-body" style="display:grid;gap:14px">
            <p style="margin:0;font-size:13.5px;color:#64748b">The record stays in the register as evidence, but NIN {{ $person->nin }} will no longer verify and no card can be printed. Use <b>Archive</b> instead for records entered by mistake.</p>
            <div class="ui-field">
                <label for="deReason">Reason</label>
                <select class="ui-select" id="deReason" name="reason" onchange="document.getElementById('deDeath').hidden = this.value !== 'deceased'">
                    @foreach (Person::DEACTIVATION_REASONS as $k => $l)
                        <option value="{{ $k }}" @selected(old('reason', 'deceased') === $k)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ui-field" id="deDeath" @if (old('reason', 'deceased') !== 'deceased') hidden @endif>
                <label for="deDate">Date of death</label>
                <input class="ui-input {{ $b->has('date_of_death') ? 'is-invalid' : '' }}" type="date" id="deDate" name="date_of_death" value="{{ old('date_of_death') }}" max="{{ now()->toDateString() }}">
                @if ($b->has('date_of_death'))<span class="ui-err">{{ $b->first('date_of_death') }}</span>@endif
            </div>
            <div class="ui-field">
                <label for="deNote">Reference / details</label>
                <textarea class="ui-input {{ $b->has('note') ? 'is-invalid' : '' }}" id="deNote" name="note" rows="3" maxlength="500" style="height:auto;padding:10px 12px" placeholder="e.g. Death certificate no. 123456 issued by RITA">{{ old('note') }}</textarea>
                @if ($b->has('note'))<span class="ui-err">{{ $b->first('note') }}</span>@endif
            </div>
        </div>
        <div class="ui-dialog-foot">
            <button type="button" class="ui-btn" onclick="this.closest('dialog').close()">Cancel</button>
            <button class="ui-btn danger"><i class="bi bi-slash-circle"></i> Deactivate NIN</button>
        </div>
    </form>
</dialog>
@endif

@if ($isAdmin && $person->isDeactivated())
<dialog class="ui-dialog no-print" id="reactivateDialog" @if ($errors->getBag('reactivate')->any()) data-open @endif>
    <form method="POST" action="{{ route('people.reactivate', $person) }}">
        @csrf @method('DELETE')
        <div class="ui-dialog-head"><h3>Reactivate NIN</h3><button type="button" class="ui-btn icon sm" onclick="this.closest('dialog').close()" aria-label="Close"><i class="bi bi-x-lg"></i></button></div>
        <div class="ui-dialog-body" style="display:grid;gap:10px">
            <p style="margin:0;font-size:13.5px;color:#64748b">Only reactivate if the deactivation was a mistake. Write why.</p>
            <textarea class="ui-input" name="note" rows="3" maxlength="500" required minlength="5" style="height:auto;padding:10px 12px">{{ old('note') }}</textarea>
            @error('note', 'reactivate')<span class="ui-err">{{ $message }}</span>@enderror
        </div>
        <div class="ui-dialog-foot">
            <button type="button" class="ui-btn" onclick="this.closest('dialog').close()">Cancel</button>
            <button class="ui-btn primary"><i class="bi bi-arrow-counterclockwise"></i> Reactivate</button>
        </div>
    </form>
</dialog>
@endif

@push('scripts')
<script>document.querySelectorAll('dialog[data-open]').forEach((d) => d.showModal());</script>
@endpush
