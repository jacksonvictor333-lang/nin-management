<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CardEvent;
use App\Models\Person;
use App\Support\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Mzunguko wa kadi baada ya NIN kuidhinishwa:
 *   Kuchapishwa → Imechapishwa → Iko tayari (SMS kwa mwombaji) → Imekabidhiwa
 * Kadi ikipotea / kuharibika / kuibiwa: toleo jipya (card_version + 1) linarudi "Kuchapishwa".
 */
class CardLifecycleController extends Controller
{
    /** Hatua inayoruhusiwa kutoka kila hali. */
    private const NEXT = [
        null => Person::CARD_PRINTED,
        Person::CARD_PRINTED => Person::CARD_READY,
        Person::CARD_READY => Person::CARD_COLLECTED,
    ];

    public function index(Request $request)
    {
        $tab = $request->query('tab', 'to_print');
        $tab = array_key_exists($tab, Person::CARD_STAGES) ? $tab : 'to_print';
        $search = trim((string) $request->query('q', ''));

        $base = fn () => Person::query()->where('status', 'Active')->whereNull('deactivation_reason');
        $inTab = fn ($q, string $key) => $key === 'to_print' ? $q->whereNull('card_status') : $q->where('card_status', $key);

        $counts = collect(array_keys(Person::CARD_STAGES))->mapWithKeys(fn ($k) => [$k => $inTab($base(), $k)->count()]);

        $people = $inTab($base(), $tab)
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('nin', 'like', "%{$search}%")->orWhere('reference', 'like', "%{$search}%")
                ->orWhere('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")))
            ->orderByRaw(match ($tab) {
                Person::CARD_PRINTED => 'card_printed_at',
                Person::CARD_READY => 'card_ready_at',
                Person::CARD_COLLECTED => 'card_collected_at DESC',
                default => 'reviewed_at IS NULL, reviewed_at',
            })
            ->paginate(20)
            ->withQueryString();

        return view('cards.index', compact('people', 'tab', 'counts', 'search'));
    }

    /** Hatua moja kwa kadi ya mtu mmoja (kutoka kwenye profile). */
    public function update(Request $request, Person $person)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in([Person::CARD_PRINTED, Person::CARD_READY, Person::CARD_COLLECTED])],
            'collected_by' => ['nullable', 'required_if:action,collected', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:300'],
        ], ['collected_by.required_if' => 'Write the name of the person who collected the card.']);

        abort_unless($this->canMove($person, $data['action']), 422, 'The card cannot move to that stage now.');

        $this->move($person, $data['action'], $data['collected_by'] ?? null, $data['note'] ?? null);

        return redirect()->to(route('people.show', $person) . '#id-card')->with('success', match ($data['action']) {
            Person::CARD_PRINTED => 'Card marked as printed.',
            Person::CARD_READY => 'Card is ready for collection.' . ($person->phone ? ' The holder was notified by SMS.' : ''),
            default => 'Card handed over to ' . $data['collected_by'] . '.',
        });
    }

    /** Hatua kwa kadi nyingi (ukurasa wa Cards). */
    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in([Person::CARD_PRINTED, Person::CARD_READY])],
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['integer'],
        ]);

        $moved = 0;
        foreach (Person::whereIn('id', $data['ids'])->get() as $person) {
            if ($this->canMove($person, $data['action'])) {
                $this->move($person, $data['action']);
                $moved++;
            }
        }

        return back()->with('success', ($data['action'] === Person::CARD_PRINTED ? 'Marked as printed: ' : 'Marked ready for collection: ')
            . $moved . ' ' . Str::plural('card', $moved) . '.');
    }

    /** Kadi imepotea / kuharibika / kuibiwa: toleo jipya linaingia kwenye foleni ya kuchapishwa. */
    public function replace(Request $request, Person $person)
    {
        $data = $request->validateWithBag('replace', [
            'reason' => ['required', Rule::in(array_keys(Person::CARD_LOSS_REASONS))],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        abort_unless($person->isApproved() && $person->card_status !== null && ! $person->isDeactivated(), 422);

        $old = $person->card_version;
        CardEvent::create(['person_id' => $person->id, 'user_id' => auth()->id(), 'card_version' => $old, 'event' => $data['reason'], 'note' => $data['note'] ?? null]);

        $person->forceFill([
            'card_version' => $old + 1,
            'card_status' => null,
            'card_printed_at' => null,
            'card_ready_at' => null,
            'card_collected_at' => null,
            'card_collected_by' => null,
        ])->save();

        ActivityLog::record('card.replaced', "Card #{$old} for {$person->full_name} reported " . Person::CARD_LOSS_REASONS[$data['reason']]
            . "; replacement card #{$person->card_version} queued for printing", $person, ['note' => $data['note'] ?? null]);

        return redirect()->to(route('people.show', $person) . '#id-card')
            ->with('success', "Replacement card #{$person->card_version} added to the print queue.");
    }

    // ------------------------------------------------------------------

    private function canMove(Person $person, string $action): bool
    {
        return $person->isApproved()
            && $person->nin
            && ! $person->isDeactivated()
            && ($person->status ?? 'Active') === 'Active'
            && (self::NEXT[$person->card_status] ?? null) === $action;
    }

    private function move(Person $person, string $action, ?string $collectedBy = null, ?string $note = null): void
    {
        $person->forceFill(array_filter([
            'card_status' => $action,
            'card_printed_at' => $action === Person::CARD_PRINTED ? now() : null,
            'card_ready_at' => $action === Person::CARD_READY ? now() : null,
            'card_collected_at' => $action === Person::CARD_COLLECTED ? now() : null,
            'card_collected_by' => $collectedBy,
        ]))->save();

        CardEvent::create([
            'person_id' => $person->id,
            'user_id' => auth()->id(),
            'card_version' => $person->card_version,
            'event' => $action,
            'note' => $collectedBy ? trim("Collected by {$collectedBy}. " . $note) : $note,
        ]);

        ActivityLog::record("card.{$action}", match ($action) {
            Person::CARD_PRINTED => "Card #{$person->card_version} printed for {$person->full_name}",
            Person::CARD_READY => "Card #{$person->card_version} ready for collection: {$person->full_name}",
            default => "Card #{$person->card_version} handed over to {$collectedBy} ({$person->full_name})",
        }, $person);

        if ($action === Person::CARD_READY) {
            Sms::notifyPerson($person, 'card_ready');
        }
    }
}
