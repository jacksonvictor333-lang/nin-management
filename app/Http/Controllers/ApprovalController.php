<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\Region;
use App\Models\User;
use App\Support\ApprovalFlow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Hatua za idhini ya usajili:
 *
 *   Rasimu → Imewasilishwa → Inakaguliwa → Imeidhinishwa (NIN inatolewa)
 *                                    ├→ Imerudishwa → (rekebisha) → Imewasilishwa
 *                                    └→ Imekataliwa (mwisho)
 *
 * Aliyesajili au aliyewasilisha rekodi hawezi kuiidhinisha mwenyewe.
 */
class ApprovalController extends Controller
{
    /** Foleni ya anayeidhinisha (Administrator / Approver). */
    public function index(Request $request)
    {
        $user = $request->user();
        $tab = $request->query('tab', 'pending');
        $tabs = [
            'pending' => [Person::SUBMITTED, Person::IN_REVIEW],
            'mine' => [Person::IN_REVIEW],
            'returned' => [Person::RETURNED],
            'rejected' => [Person::REJECTED],
            'approved' => [Person::APPROVED],
        ];
        $tab = array_key_exists($tab, $tabs) ? $tab : 'pending';

        $regionId = $request->integer('region_id') ?: null;
        $waited = (int) $request->query('waited', 0);

        $base = fn () => Person::anyStage()->when($regionId, fn ($q) => $q->where('region_id', $regionId));

        $counts = [];
        foreach ($tabs as $key => $stages) {
            $counts[$key] = $base()->whereIn('stage', $stages)
                ->when($key === 'mine', fn ($q) => $q->where('reviewer_id', $user->id))
                ->when($key === 'approved', fn ($q) => $q->whereNotNull('reviewed_at'))
                ->count();
        }

        $people = $base()
            ->with(['creator:id,name', 'submitter:id,name', 'reviewer:id,name'])
            ->whereIn('stage', $tabs[$tab])
            ->when($tab === 'mine', fn ($q) => $q->where('reviewer_id', $user->id))
            ->when($tab === 'approved', fn ($q) => $q->whereNotNull('reviewed_at'))
            ->when($waited > 0 && $tab === 'pending', fn ($q) => $q->where('submitted_at', '<=', now()->subDays($waited)))
            ->when(in_array($tab, ['pending', 'mine'], true),
                fn ($q) => $q->orderBy('submitted_at'),          // waliosubiri muda mrefu kwanza
                fn ($q) => $q->orderByDesc('reviewed_at'))
            ->paginate(15)
            ->withQueryString();

        $regions = Region::orderBy('name')->get(['id', 'name']);

        return view('approvals.index', compact('people', 'tab', 'counts', 'regions', 'regionId', 'waited', 'user'));
    }

    /** Rekodi za mtumiaji: alizosajili au kuwasilisha, kwa kila hatua. */
    public function mine(Request $request)
    {
        $user = $request->user();
        $stage = $request->query('stage');
        $stage = array_key_exists((string) $stage, Person::STAGES) ? $stage : null;

        $own = fn () => Person::anyStage()->where(fn ($q) => $q->where('created_by', $user->id)->orWhere('submitted_by', $user->id));

        $counts = $own()->selectRaw('stage, COUNT(*) as c')->groupBy('stage')->pluck('c', 'stage');

        $people = $own()
            ->with('reviewer:id,name')
            ->when($stage, fn ($q) => $q->where('stage', $stage))
            // Zinazohitaji kazi yangu (rasimu, zilizorudishwa) zinakuja juu.
            ->orderByRaw("CASE stage WHEN 'returned' THEN 0 WHEN 'draft' THEN 1 WHEN 'submitted' THEN 2 WHEN 'in_review' THEN 3 ELSE 4 END")
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('approvals.mine', compact('people', 'stage', 'counts'));
    }

    /** Registrar anawasilisha rasimu (au rekodi iliyorudishwa) kwa idhini. */
    public function submit(Request $request, Person $person)
    {
        abort_unless($person->canBeSubmittedBy($request->user()), 403, 'This record cannot be submitted right now.');

        $again = $person->stage === Person::RETURNED;
        ApprovalFlow::move($person, Person::SUBMITTED, $again ? 'resubmitted' : 'submitted', $request->input('note'), [
            'submitted_by' => $request->user()->id,
            'submitted_at' => now(),
            'reviewer_id' => null,
            'review_note' => null,
        ]);

        return redirect()->route('people.show', $person)
            ->with('success', $again ? 'Corrections sent back for approval.' : 'Submitted for approval.');
    }

    /** Anayeidhinisha anachukua rekodi ili wengine wasiikague kwa wakati mmoja. */
    public function claim(Request $request, Person $person)
    {
        $user = $request->user();

        if ($person->stage === Person::IN_REVIEW && $person->reviewer_id !== $user->id) {
            return back()->with('error', "{$person->reviewer?->name} is already reviewing this record.");
        }
        abort_unless($person->canBeReviewedBy($user), 403, $this->whyNot($person, $user));

        if ($person->stage === Person::SUBMITTED) {
            ApprovalFlow::move($person, Person::IN_REVIEW, 'claimed', null, ['reviewer_id' => $user->id]);
        }

        return redirect()->to(route('people.show', $person) . '#review');
    }

    /** Achia rekodi irudi kwenye foleni (au Administrator achukue kutoka kwa mwingine). */
    public function release(Request $request, Person $person)
    {
        abort_unless($person->canBeReleasedBy($request->user()), 403);

        ApprovalFlow::move($person, Person::SUBMITTED, 'released', null, ['reviewer_id' => null]);

        return back()->with('success', 'The record is back in the approval queue.');
    }

    public function approve(Request $request, Person $person)
    {
        $user = $request->user();
        abort_unless($person->canBeReviewedBy($user), 403, $this->whyNot($person, $user));

        $data = $request->validateWithBag('review', [
            'checks' => ['required', 'array'],
            'checks.*' => [Rule::in(array_keys(Person::CHECKLIST))],
            'note' => ['nullable', 'string', 'max:1000'],
        ], ['checks.required' => 'Tick every item on the checklist before approving.']);

        $missing = array_diff(array_keys(Person::CHECKLIST), $data['checks']);
        if ($missing) {
            return back()->withErrors(['checks' => 'Tick every item on the checklist before approving.'], 'review')->withFragment('review');
        }

        $this->approveOne($person, $user, $data['checks'], $data['note'] ?? null);

        return redirect()->route('people.show', $person)
            ->with('success', "{$person->full_name} approved. NIN {$person->nin} issued.");
    }

    /** Kuidhinisha rekodi nyingi kwa mpigo kutoka kwenye foleni. */
    public function bulkApprove(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:50'],
            'ids.*' => ['integer'],
            'confirm_checked' => ['accepted'],
        ], ['confirm_checked.accepted' => 'Confirm that you checked every selected record.']);

        $approved = 0;
        $skipped = 0;

        foreach (Person::anyStage()->whereIn('id', $data['ids'])->get() as $person) {
            if ($person->canBeReviewedBy($user)) {
                $this->approveOne($person, $user, array_keys(Person::CHECKLIST), 'Bulk approval');
                $approved++;
            } else {
                $skipped++;
            }
        }

        return back()->with('success', "Approved {$approved} " . Str::plural('record', $approved) . '.'
            . ($skipped ? " Skipped {$skipped} (your own registrations or being reviewed by someone else)." : ''));
    }

    public function returnForCorrection(Request $request, Person $person)
    {
        return $this->decline($request, $person, Person::RETURNED);
    }

    public function reject(Request $request, Person $person)
    {
        return $this->decline($request, $person, Person::REJECTED);
    }

    // ------------------------------------------------------------------

    private function decline(Request $request, Person $person, string $stage)
    {
        $user = $request->user();
        abort_unless($person->canBeReviewedBy($user), 403, $this->whyNot($person, $user));

        $data = $request->validateWithBag('review', [
            'note' => ['required', 'string', 'min:5', 'max:1000'],
        ], ['note.required' => 'Write the reason so the registrar knows what to do.']);

        ApprovalFlow::move($person, $stage, $stage, $data['note'], [
            'reviewer_id' => $user->id,
            'reviewed_at' => now(),
            'review_note' => $data['note'],
        ]);
        ApprovalFlow::notify($person, $stage, $data['note'], $user);

        return redirect()->route('people.show', $person)->with('success', $stage === Person::RETURNED
            ? "{$person->full_name} was returned to the registrar for correction."
            : "{$person->full_name} was rejected.");
    }

    private function approveOne(Person $person, User $user, array $checks, ?string $note): void
    {
        DB::transaction(function () use ($person, $user, $checks, $note) {
            // NIN inatolewa sasa; rekodi iliyoidhinishwa awali (mabadiliko nyeti) inabaki na NIN yake.
            $nin = $person->nin ?: Person::generateNin($person->date_of_birth);

            ApprovalFlow::move($person, Person::APPROVED, 'approved', $note, [
                'nin' => $nin,
                'reviewer_id' => $user->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ], $checks);
        });

        ApprovalFlow::notify($person, 'approved', $note, $user);
    }

    private function whyNot(Person $person, User $user): string
    {
        return match (true) {
            ! $user->canApprove() => 'Only an Approver or Administrator can review records.',
            $person->isOwnWork($user) => 'You cannot approve a record you registered or submitted. Another approver must review it.',
            $person->stage === Person::IN_REVIEW => 'Another approver is reviewing this record.',
            default => 'This record is not waiting for approval.',
        };
    }
}
