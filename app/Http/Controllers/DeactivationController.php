<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Kusitisha NIN (kifo, udanganyifu, amri ya mahakama...). Tofauti na Archive:
 * rekodi inabaki kwenye rejista kama ushahidi, lakini NIN haithibitishwi tena
 * (API, QR ya kadi) na kadi haiwezi kuchapishwa.
 */
class DeactivationController extends Controller
{
    public function store(Request $request, Person $person)
    {
        abort_unless($person->isApproved() && ! $person->isDeactivated(), 422, 'Only an active, approved record can be deactivated.');

        $data = $request->validateWithBag('deactivate', [
            'reason' => ['required', Rule::in(array_keys(Person::DEACTIVATION_REASONS))],
            'date_of_death' => ['nullable', 'required_if:reason,deceased', 'date', 'before_or_equal:today', 'after_or_equal:' . $person->date_of_birth?->toDateString()],
            'note' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'date_of_death.required_if' => 'Enter the date of death.',
            'note.required' => 'Write the reference (e.g. death certificate number) or reason.',
        ]);

        $person->forceFill([
            'status' => 'Inactive',
            'deactivation_reason' => $data['reason'],
            'deactivation_note' => $data['note'],
            'date_of_death' => $data['reason'] === 'deceased' ? $data['date_of_death'] : null,
            'deactivated_at' => now(),
            'deactivated_by' => $request->user()->id,
        ])->save();

        ActivityLog::record('person.deactivated', "Deactivated NIN {$person->nin} ({$person->full_name}): {$person->deactivationLabel()} — {$data['note']}", $person);

        return redirect()->route('people.show', $person)->with('success', "NIN deactivated ({$person->deactivationLabel()}).");
    }

    public function destroy(Request $request, Person $person)
    {
        abort_unless($person->isDeactivated(), 422);

        $request->validateWithBag('reactivate', ['note' => ['required', 'string', 'min:5', 'max:500']]);
        $old = $person->deactivationLabel();

        $person->forceFill([
            'status' => 'Active',
            'deactivation_reason' => null,
            'deactivation_note' => null,
            'date_of_death' => null,
            'deactivated_at' => null,
            'deactivated_by' => null,
        ])->save();

        ActivityLog::record('person.reactivated', "Reactivated NIN {$person->nin} ({$person->full_name}), previously {$old}: {$request->input('note')}", $person);

        return redirect()->route('people.show', $person)->with('success', 'NIN reactivated.');
    }
}
