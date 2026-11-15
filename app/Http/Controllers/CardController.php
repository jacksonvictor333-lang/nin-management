<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Person;
use App\Support\QrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Kuchapisha kadi za vitambulisho kwa ukubwa halisi (85.6 × 54 mm),
 * upande wa mbele na wa nyuma, kwenye karatasi ya A4.
 * "Save as PDF" ya kivinjari inatoa PDF moja kwa moja.
 */
class CardController extends Controller
{
    private const MAX = 40;

    public function show(Person $person)
    {
        abort_unless($person->isApproved() && ! $person->isDeactivated(), 404);

        return $this->render(collect([$person]));
    }

    public function bulk(Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($id) => (int) $id)->filter()->unique()->take(self::MAX);

        abort_if($ids->isEmpty(), 404);

        $people = Person::whereIn('id', $ids)->whereNull('deactivation_reason')->get()->sortBy(fn ($p) => $ids->search($p->id))->values();
        abort_if($people->isEmpty(), 404);

        return $this->render($people);
    }

    private function render(Collection $people)
    {
        $cards = $people->map(fn (Person $p) => [
            'person' => $p,
            'qr' => QrCode::svg(CardVerificationController::urlFor($p), 240, '#14532d'),
            'nin' => preg_replace('/^(\d{8})(\d{4})(\d{4})(\d{4})$/', '$1-$2-$3-$4', $p->nin),
        ]);

        ActivityLog::record(
            'card.printed',
            $people->count() === 1
                ? "Opened card print for {$people->first()->full_name}"
                : "Opened card print for {$people->count()} people",
            $people->count() === 1 ? $people->first() : null,
            ['nins' => $people->pluck('nin')->all()]
        );

        return view('cards.print', ['cards' => $cards]);
    }
}
