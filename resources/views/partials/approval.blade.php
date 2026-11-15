{{--
    Hatua za idhini kwenye profile: mstari wa maendeleo, maelezo ya anayeidhinisha,
    vitufe vya Registrar (wasilisha / rekebisha) na fomu ya ukaguzi ya Approver.
    Vigezo: $person, $reviews, $matches
--}}
@use('App\Models\Person')
@php
    $me = auth()->user();
    $stage = $person->stage;
    $lastOf = fn (array $actions) => $reviews->whereIn('action', $actions)->last();

    $submittedAt = $lastOf(['submitted', 'resubmitted', 'edited'])?->created_at ?? $person->submitted_at;
    $claimed = $lastOf(['claimed']);
    $claimedAt = $claimed && $submittedAt && $claimed->created_at->gte($submittedAt) ? $claimed->created_at : null;

    $final = match ($stage) {
        Person::RETURNED => ['Returned', 'bi-arrow-return-left', 'warn'],
        Person::REJECTED => ['Rejected', 'bi-x-lg', 'bad'],
        default => ['Approved', 'bi-patch-check', 'ok'],
    };

    $position = [Person::DRAFT => 0, Person::SUBMITTED => 1, Person::IN_REVIEW => 2][$stage] ?? 3;

    $steps = [
        ['Draft', 'bi-pencil', $person->created_at, $person->creator?->name],
        ['Submitted', 'bi-send', $submittedAt, $person->submitter?->name],
        ['In review', 'bi-search', $claimedAt, $stage === Person::IN_REVIEW || $position === 3 ? $person->reviewer?->name : null],
        [$final[0], $final[1], $position === 3 ? $person->reviewed_at : null, $position === 3 ? $person->reviewer?->name : null],
    ];

    $canSubmit = $person->canBeSubmittedBy($me);
    $canReview = $person->canBeReviewedBy($me);
    $canRelease = $person->canBeReleasedBy($me);
    $ownWork = $me->canApprove() && $person->isOwnWork($me) && in_array($stage, [Person::SUBMITTED, Person::IN_REVIEW], true);
    $reviewErrors = $errors->getBag('review');

    $actionText = [
        'draft' => 'Saved as draft', 'submitted' => 'Submitted for approval', 'resubmitted' => 'Resubmitted after correction',
        'edited' => 'Sensitive details changed — sent for re-approval', 'claimed' => 'Started review',
        'released' => 'Released back to the queue', 'approved' => 'Approved', 'returned' => 'Returned for correction', 'rejected' => 'Rejected',
    ];
    $actionTone = ['approved' => 'ok', 'returned' => 'warn', 'rejected' => 'bad', 'claimed' => 'violet', 'edited' => 'warn'];
@endphp

@push('styles')
<style>
    .apv { margin-top: 22px; padding: 22px 24px; }
    .apv.is-returned { border-color: #fcd34d; box-shadow: 0 0 0 3px #fef3c7; }
    .apv.is-rejected { border-color: #fca5a5; }
    .apv-head { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .apv-head h3 { margin: 0; font-size: 16px; color: var(--ink); }
    .apv-head p { margin: 2px 0 0; font-size: 12.5px; color: var(--muted); }
    .apv-head .end { margin-left: auto; display: flex; gap: 8px; flex-wrap: wrap; }
    .apv-head .end form { margin: 0; }

    .stepper { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); margin: 22px 0 6px; list-style: none; padding: 0; }
    .stepper li { position: relative; text-align: center; padding: 0 6px; min-width: 0; }
    .stepper li::before { content: ""; position: absolute; top: 17px; left: calc(-50% + 22px); right: calc(50% + 22px); height: 3px; border-radius: 3px; background: #e2e8f0; }
    .stepper li:first-child::before { display: none; }
    .stepper li.done::before, .stepper li.current::before { background: var(--primary-light); }
    .stepper .dot { width: 36px; height: 36px; margin: 0 auto 8px; border-radius: 50%; display: grid; place-items: center; font-size: 16px; background: #f1f5f9; color: #94a3b8; border: 2px solid #e2e8f0; position: relative; }
    .stepper li.done .dot { background: var(--primary-light); border-color: var(--primary-light); color: #fff; }
    .stepper li.current .dot { background: #fff; border-color: var(--primary-light); color: var(--primary); box-shadow: 0 0 0 5px var(--primary-soft); }
    .stepper li.current.warn .dot { border-color: #f59e0b; color: #b45309; box-shadow: 0 0 0 5px #fef3c7; }
    .stepper li.current.bad .dot { border-color: #ef4444; color: #b91c1c; box-shadow: 0 0 0 5px #fee2e2; }
    .stepper li.done.ok .dot { box-shadow: 0 0 0 5px var(--primary-soft); }
    .stepper b { display: block; font-size: 13px; color: var(--ink); }
    .stepper li:not(.done):not(.current) b { color: #94a3b8; }
    .stepper small { display: block; font-size: 11.5px; color: var(--muted); line-height: 1.4; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .apv-note { margin-top: 18px; display: flex; gap: 12px; padding: 14px 16px; border-radius: 14px; font-size: 13.5px; line-height: 1.5; }
    .apv-note > i { font-size: 18px; flex-shrink: 0; margin-top: 1px; }
    .apv-note b { display: block; margin-bottom: 2px; }
    .apv-note.warn { background: #fffbeb; color: #78350f; border: 1px solid #fde68a; }
    .apv-note.bad { background: #fef2f2; color: #7f1d1d; border: 1px solid #fecaca; }
    .apv-note.info { background: #eff6ff; color: #1e3a8a; border: 1px solid #bfdbfe; }
    .apv-note.ok { background: #f0fdf4; color: #14532d; border: 1px solid #bbf7d0; }

    .review-box { margin-top: 18px; border: 1px solid var(--border); border-radius: 16px; padding: 18px; background: #fcfcfd; }
    .review-box h4 { margin: 0 0 4px; font-size: 14.5px; color: var(--ink); }
    .review-box > p { margin: 0 0 14px; font-size: 12.5px; color: var(--muted); }
    .checks { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 8px; margin-bottom: 14px; }
    .checks label { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid var(--border); border-radius: 11px; background: #fff; font-size: 13px; cursor: pointer; }
    .checks label:has(input:checked) { border-color: #86efac; background: #f0fdf4; }
    .checks input { width: 17px; height: 17px; accent-color: #16a34a; flex-shrink: 0; }
    .checks .hint { margin-left: auto; font-size: 11.5px; font-weight: 600; white-space: nowrap; }
    .checks .hint.bad { color: #b91c1c; }
    .checks .hint.ok { color: #15803d; }
    .review-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
    .review-actions .ui-btn.warn { color: #b45309; }
    .review-actions .ui-btn.warn:hover { background: #fffbeb; border-color: #fde68a; }

    .apv-matches { margin: 0 0 14px; padding: 12px 14px; border-radius: 12px; background: #fff7ed; border: 1px solid #fed7aa; font-size: 13px; color: #7c2d12; }
    .apv-matches ul { margin: 6px 0 0; padding-left: 18px; }
    .apv-matches a { color: inherit; font-weight: 700; }

    .apv-log { margin-top: 16px; }
    .apv-log summary { cursor: pointer; font-size: 13px; font-weight: 600; color: var(--primary); }
    .apv-log ol { list-style: none; margin: 12px 0 0; padding: 0; display: grid; gap: 10px; }
    .apv-log li { display: flex; gap: 10px; align-items: flex-start; font-size: 13px; }
    .apv-log li .when { margin-left: auto; color: var(--muted); font-size: 12px; white-space: nowrap; }
    .apv-log li .note { display: block; color: var(--muted); font-size: 12.5px; margin-top: 2px; }

    @media (max-width: 640px) {
        .apv { padding: 18px 16px; }
        .stepper small { display: none; }
        .stepper b { font-size: 11.5px; }
    }
    @media print { .apv { display: none; } }
</style>
@endpush

<section class="pf-card apv no-print is-{{ $stage }}" id="review">
    <div class="apv-head">
        <span class="ui-pill {{ $person->stageTone() }}"><i class="bi {{ $person->stageIcon() }}"></i> {{ $person->stageLabel() }}</span>
        <div>
            <h3>Approval</h3>
            <p>
                @switch($stage)
                    @case(Person::DRAFT) Not yet submitted. The NIN is issued after approval. @break
                    @case(Person::SUBMITTED) Waiting in the approval queue since {{ $person->submitted_at?->diffForHumans() }}. @break
                    @case(Person::IN_REVIEW) {{ $person->reviewer?->name ?? 'An approver' }} is reviewing this record. @break
                    @case(Person::RETURNED) Returned for correction {{ $person->reviewed_at?->diffForHumans() }}. @break
                    @case(Person::REJECTED) Rejected {{ $person->reviewed_at?->diffForHumans() }}. @break
                    @default Approved {{ $person->reviewed_at?->diffForHumans() }}{{ $person->reviewer ? ' by ' . $person->reviewer->name : '' }}.
                @endswitch
            </p>
        </div>

        <div class="end">
            @if ($canSubmit)
                <a href="{{ route('people.edit', $person) }}" class="ui-btn sm"><i class="bi bi-pencil-square"></i> {{ $stage === Person::RETURNED ? 'Fix details' : 'Edit' }}</a>
                <form method="POST" action="{{ route('approvals.submit', $person) }}">
                    @csrf
                    <button class="ui-btn sm primary"><i class="bi bi-send"></i> {{ $stage === Person::RETURNED ? 'Resubmit for approval' : 'Submit for approval' }}</button>
                </form>
            @endif
            @if ($canReview && $stage === Person::SUBMITTED)
                <form method="POST" action="{{ route('approvals.claim', $person) }}">
                    @csrf
                    <button class="ui-btn sm primary"><i class="bi bi-search"></i> Start review</button>
                </form>
            @endif
            @if ($canRelease)
                <form method="POST" action="{{ route('approvals.release', $person) }}">
                    @csrf
                    <button class="ui-btn sm" title="Put the record back in the queue for any approver"><i class="bi bi-box-arrow-left"></i> Release</button>
                </form>
            @endif
        </div>
    </div>

    <ol class="stepper">
        @foreach ($steps as $i => [$label, $icon, $at, $who])
            @php
                $state = $i < $position ? 'done' : ($i === $position ? ($position === 3 && $stage === Person::APPROVED ? 'done ok' : 'current') : '');
                if ($i === 3 && $position === 3 && $stage !== Person::APPROVED) { $state .= ' ' . $final[2]; }
            @endphp
            <li class="{{ $state }}">
                <span class="dot"><i class="bi {{ $i < $position || ($i === 3 && $stage === Person::APPROVED) ? 'bi-check-lg' : $icon }}"></i></span>
                <b>{{ $label }}</b>
                <small>{{ $at ? $at->format('d M Y, H:i') : ($i === $position ? 'Now' : '—') }}</small>
                @if ($who && ($i < $position || $i === $position))<small>{{ $who }}</small>@endif
            </li>
        @endforeach
    </ol>

    @if (in_array($stage, [Person::RETURNED, Person::REJECTED], true) && $person->review_note)
        <div class="apv-note {{ $stage === Person::RETURNED ? 'warn' : 'bad' }}">
            <i class="bi {{ $stage === Person::RETURNED ? 'bi-exclamation-triangle-fill' : 'bi-x-octagon-fill' }}"></i>
            <div>
                <b>{{ $stage === Person::RETURNED ? 'What needs to be corrected' : 'Reason for rejection' }}{{ $person->reviewer ? ' — ' . $person->reviewer->name : '' }}</b>
                {{ $person->review_note }}
                @if ($stage === Person::RETURNED && $canSubmit)
                    <div style="margin-top:6px;font-size:12.5px">Correct the details with <b style="display:inline">Fix details</b>, then press <b style="display:inline">Resubmit for approval</b>.</div>
                @endif
            </div>
        </div>
    @endif

    @if ($ownWork)
        <div class="apv-note info">
            <i class="bi bi-person-lock"></i>
            <div><b>You cannot approve this record</b>You registered or submitted it. Another approver must review it.</div>
        </div>
    @elseif ($stage === Person::IN_REVIEW && ! $canReview && $me->canApprove())
        <div class="apv-note info">
            <i class="bi bi-hourglass-split"></i>
            <div><b>Being reviewed by {{ $person->reviewer?->name ?? 'another approver' }}</b>Wait for their decision{{ $canRelease ? ', or release the record to review it yourself' : '' }}.</div>
        </div>
    @elseif (in_array($stage, [Person::SUBMITTED, Person::IN_REVIEW], true) && ! $me->canApprove())
        <div class="apv-note info">
            <i class="bi bi-lock"></i>
            <div><b>Locked while waiting for approval</b>You will get a notification when an approver makes a decision.</div>
        </div>
    @endif

    {{-- Fomu ya ukaguzi (anayeidhinisha aliyechukua rekodi) --}}
    @if ($canReview && $stage === Person::IN_REVIEW)
        @php
            $old = old('checks', []);
            $hasPhoto = $person->profile_picture && Storage::disk('public')->exists($person->profile_picture);
            $hasSign = $person->signature && Storage::disk('public')->exists($person->signature);
            $hints = [
                'photo' => $hasPhoto ? ['ok', 'Photo attached'] : ['bad', 'No photo'],
                'signature' => $hasSign ? ['ok', 'Signature attached'] : ['bad', 'No signature'],
                'duplicates' => $matches->isEmpty() ? ['ok', 'No matches found'] : ['bad', $matches->count() . ' possible ' . Str::plural('match', $matches->count())],
                'location' => $person->village ? ['ok', ''] : ['bad', 'Incomplete address'],
            ];
        @endphp
        <form class="review-box" method="POST" action="{{ route('approvals.approve', $person) }}" id="reviewForm">
            @csrf
            <h4><i class="bi bi-list-check"></i> Review checklist</h4>
            <p>Check every item against the details above. All items must be ticked to approve.</p>

            @if ($matches->isNotEmpty())
                <div class="apv-matches">
                    <b><i class="bi bi-people"></i> Possible duplicates</b>
                    <ul>
                        @foreach ($matches as $m)
                            <li><a href="{{ route('people.show', $m['person']) }}" target="_blank">{{ $m['person']->full_name }}</a>
                                ({{ $m['person']->nin ?: $m['person']->stageLabel() }}) — {{ implode(', ', $m['reasons']) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="checks">
                @foreach (Person::CHECKLIST as $key => $label)
                    <label>
                        <input type="checkbox" name="checks[]" value="{{ $key }}" @checked(in_array($key, $old, true))>
                        <span>{{ $label }}</span>
                        @isset($hints[$key])
                            @if ($hints[$key][1] !== '')<span class="hint {{ $hints[$key][0] }}">{{ $hints[$key][1] }}</span>@endif
                        @endisset
                    </label>
                @endforeach
            </div>

            <div class="ui-field">
                <label for="reviewNote">Comment <span class="ui-help">— required when returning or rejecting</span></label>
                <textarea class="ui-input {{ $reviewErrors->has('note') ? 'is-invalid' : '' }}" id="reviewNote" name="note" rows="3" maxlength="1000" style="height:auto;padding:10px 12px" placeholder="e.g. The photo is blurred — take a new one in good light.">{{ old('note') }}</textarea>
            </div>

            @foreach (['checks', 'note'] as $field)
                @if ($reviewErrors->has($field))<div class="ui-err" style="margin-top:8px">{{ $reviewErrors->first($field) }}</div>@endif
            @endforeach

            <div class="review-actions">
                <button class="ui-btn primary" id="approveBtn"><i class="bi bi-patch-check"></i> Approve &amp; issue NIN</button>
                <button class="ui-btn warn" formaction="{{ route('approvals.return', $person) }}" data-needs-note="return"><i class="bi bi-arrow-return-left"></i> Return for correction</button>
                <button class="ui-btn danger" formaction="{{ route('approvals.reject', $person) }}" data-needs-note="reject"><i class="bi bi-x-octagon"></i> Reject</button>
            </div>
        </form>

        @push('scripts')
        <script>
            (function () {
                const form = document.getElementById('reviewForm');
                const note = document.getElementById('reviewNote');
                let clicked = null;
                form.querySelectorAll('button').forEach(b => b.addEventListener('click', () => clicked = b));
                form.addEventListener('submit', function (e) {
                    const kind = clicked?.dataset.needsNote;
                    if (kind) {
                        if (note.value.trim().length < 5) {
                            e.preventDefault();
                            note.classList.add('is-invalid');
                            note.focus();
                            note.placeholder = 'Write the reason so the registrar knows what to do.';
                            return;
                        }
                        if (!confirm(kind === 'reject' ? 'Reject this record? This is final.' : 'Return this record to the registrar for correction?')) {
                            e.preventDefault();
                        }
                        return;
                    }
                    const boxes = form.querySelectorAll('input[name="checks[]"]');
                    if ([...boxes].some(b => !b.checked)) {
                        e.preventDefault();
                        alert('Tick every item on the checklist before approving.');
                    }
                });
            })();
        </script>
        @endpush
    @endif

    @if ($reviews->isNotEmpty())
        <details class="apv-log" @if ($stage !== Person::APPROVED) open @endif>
            <summary>Approval history ({{ $reviews->count() }})</summary>
            <ol>
                @foreach ($reviews->reverse() as $r)
                    <li>
                        <span class="ui-pill {{ $actionTone[$r->action] ?? '' }}" style="min-width:0">{{ $actionText[$r->action] ?? ucfirst($r->action) }}</span>
                        <div style="min-width:0">
                            <b>{{ $r->user?->name ?? 'System' }}</b>
                            @if ($r->note)<span class="note">{{ $r->note }}</span>@endif
                        </div>
                        <span class="when" title="{{ $r->created_at->format('d M Y H:i') }}">{{ $r->created_at->diffForHumans() }}</span>
                    </li>
                @endforeach
            </ol>
        </details>
    @endif
</section>
