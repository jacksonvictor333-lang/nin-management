<?php

namespace App\Http\Controllers;

use App\Models\Person;
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

        return $base . URL::signedRoute('card.verify', ['person' => $person->id], null, false);
    }

    public function show(Person $person)
    {
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
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store');
    }
}
