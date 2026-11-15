<?php

namespace App\Http\Controllers;

use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Ukurasa wa umma: mwombaji anaangalia hali ya ombi lake kwa namba ya ombi
 * na tarehe ya kuzaliwa. Hauonyeshi NIN wala taarifa nyingine binafsi.
 */
class ApplicationStatusController extends Controller
{
    public function show()
    {
        return view('status.check', ['result' => null]);
    }

    public function check(Request $request)
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:20'],
            'date_of_birth' => ['required', 'date'],
        ], [], ['reference' => 'application number']);

        $reference = strtoupper(preg_replace('/\s+/', '', $data['reference']));

        $person = Person::anyStage()
            ->where('reference', $reference)
            ->whereDate('date_of_birth', Carbon::parse($data['date_of_birth'])->toDateString())
            ->first();

        if (! $person) {
            return back()->withInput()->withErrors([
                'reference' => 'No application matches that number and date of birth. Check both and try again.',
            ]);
        }

        return view('status.check', ['result' => $this->describe($person), 'person' => $person])
            ->with('reference', $reference);
    }

    /** Maelezo rahisi kwa mwombaji (Kiswahili + Kiingereza). */
    private function describe(Person $person): array
    {
        $office = config('services.sms.office_name');

        if ($person->isDeactivated()) {
            return ['tone' => 'bad', 'icon' => 'bi-slash-circle', 'step' => 4,
                'title' => 'Imesitishwa / Deactivated',
                'text' => "Namba hii haitumiki tena. Fika {$office} kwa maelezo."];
        }

        return match ($person->stage) {
            Person::DRAFT => ['tone' => 'info', 'icon' => 'bi-pencil', 'step' => 1,
                'title' => 'Inaandaliwa / Being prepared',
                'text' => 'Ombi lako bado linakamilishwa na afisa wa usajili.'],
            Person::SUBMITTED, Person::IN_REVIEW => ['tone' => 'info', 'icon' => 'bi-hourglass-split', 'step' => 2,
                'title' => 'Linakaguliwa / Under review',
                'text' => 'Ombi lako limepokelewa na linasubiri kuidhinishwa. Utapata SMS likiidhinishwa.'],
            Person::RETURNED => ['tone' => 'warn', 'icon' => 'bi-exclamation-triangle', 'step' => 2,
                'title' => 'Linahitaji marekebisho / Needs correction',
                'text' => "Tafadhali fika {$office} ili kukamilisha taarifa zako."],
            Person::REJECTED => ['tone' => 'bad', 'icon' => 'bi-x-octagon', 'step' => 2,
                'title' => 'Halikukubaliwa / Not approved',
                'text' => "Kwa maelezo zaidi fika {$office}."],
            default => match ($person->card_status) {
                Person::CARD_READY => ['tone' => 'ok', 'icon' => 'bi-box-seam', 'step' => 4,
                    'title' => 'Kadi iko tayari / Card ready for collection',
                    'text' => "Chukua kadi yako {$office} ukiwa na namba hii ya ombi."],
                Person::CARD_COLLECTED => ['tone' => 'ok', 'icon' => 'bi-person-check', 'step' => 5,
                    'title' => 'Kadi imekabidhiwa / Card collected',
                    'text' => 'Kadi ilikabidhiwa tarehe ' . $person->card_collected_at?->format('d/m/Y') . '.'],
                default => ['tone' => 'ok', 'icon' => 'bi-patch-check', 'step' => 3,
                    'title' => 'Imeidhinishwa / Approved',
                    'text' => 'Ombi lako limeidhinishwa na kadi yako inaandaliwa. Utapata SMS ikiwa tayari.'],
            },
        };
    }
}
