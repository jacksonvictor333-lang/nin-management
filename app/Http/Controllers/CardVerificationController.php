<?php

namespace App\Http\Controllers;

use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Ukurasa wa umma unaofunguliwa kwa kuskani QR code ya kitambulisho.
 * Unaonyesha taarifa chache tu za kuthibitisha kadi (si rekodi nzima).
 * Kiungo kimesainiwa (signed URL), kwa hiyo hakiwezi kubuniwa kwa ID nyingine.
 */
class CardVerificationController extends Controller
{
    /**
     * Sahihi ya kiungo inahusu njia (path) tu, si host. Host inatoka APP_URL
     * (au CARD_VERIFY_URL), ili QR ifanye kazi kwenye simu iliyo kwenye mtandao.
     */
    public static function urlFor(Person $person): string
    {
        $base = rtrim((string) (config('services.nin.card_verify_url') ?: config('app.url')), '/');

        // v = toleo la kadi: kadi iliyopotea na kubadilishwa haithibitishwi tena.
        $params = ['person' => $person->id];
        if (($person->card_version ?? 1) > 1) {
            $params['v'] = $person->card_version;
        }

        return $base . URL::signedRoute('card.verify', $params, null, false);
    }

    public function show(Request $request, Person $person)
    {
        // Rekodi inayosubiri idhini (au iliyokataliwa) haina kadi halali.
        abort_unless($person->isApproved() && $person->nin, 404);

        $nin = (string) $person->nin;

        return response()
            ->view('cards.verify', [
                'person' => $person,
                'maskedNin' => substr($nin, 0, 4) . str_repeat('•', max(0, strlen($nin) - 8)) . substr($nin, -4),
                'photo' => $person->profile_picture && Storage::disk('public')->exists($person->profile_picture)
                    ? asset('storage/' . $person->profile_picture)
                    : null,
                'initials' => mb_strtoupper(mb_substr($person->first_name, 0, 1) . mb_substr($person->last_name, 0, 1)),
                'isActive' => ($person->status ?? 'Active') === 'Active',
                'deactivated' => $person->deactivationLabel(),
                'replaced' => (int) $request->query('v', 1) !== (int) ($person->card_version ?? 1),
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store');
    }
}
