<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Person;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SMS kwa mwombaji. Watoa huduma:
 *   SMS_DRIVER=log            (chaguo-msingi) ujumbe unaandikwa kwenye storage/logs/laravel.log
 *   SMS_DRIVER=beem           Beem Africa (SMS_API_KEY, SMS_API_SECRET, SMS_SENDER_ID)
 *   SMS_DRIVER=africastalking Africa's Talking (SMS_USERNAME, SMS_API_KEY, SMS_SENDER_ID)
 *   SMS_DRIVER=off            hakuna SMS
 *
 * Kushindwa kutuma SMS hakusimamishi kazi ya mfumo; kunaandikwa kwenye Activity Log.
 */
class Sms
{
    /** Namba ya Tanzania kwa mfumo wa kimataifa: 0754123456 → 255754123456 */
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return match (true) {
            strlen($digits) === 12 && str_starts_with($digits, '255') => $digits,
            strlen($digits) === 10 && str_starts_with($digits, '0') => '255' . substr($digits, 1),
            strlen($digits) === 9 => '255' . $digits,
            default => null,
        };
    }

    /** Tuma ujumbe kwa mwombaji kuhusu tukio fulani (submitted, approved, returned, rejected, card_ready). */
    public static function notifyPerson(Person $person, string $event, ?string $note = null): bool
    {
        $text = self::message($person, $event, $note);

        return $text ? self::send($person->phone, $text, $person, $event) : false;
    }

    public static function message(Person $person, string $event, ?string $note = null): ?string
    {
        $name = $person->first_name;
        $ref = $person->reference;
        $office = config('services.sms.office_name');

        return match ($event) {
            'submitted' => "Habari {$name}, ombi lako la kitambulisho limepokelewa. Namba ya ombi: {$ref}. Utapata ujumbe likiidhinishwa.",
            'approved' => "Hongera {$name}! Ombi lako ({$ref}) limeidhinishwa. NIN yako ni {$person->nin}. Tutakujulisha kadi ikiwa tayari.",
            'returned' => "Habari {$name}, ombi lako ({$ref}) linahitaji marekebisho: " . str($note)->limit(90) . " Tafadhali fika {$office}.",
            'rejected' => "Habari {$name}, ombi lako ({$ref}) halikukubaliwa. Kwa maelezo zaidi fika {$office}.",
            'card_ready' => "Habari {$name}, kadi yako ya kitambulisho iko tayari. Ichukue {$office} ukiwa na namba ya ombi {$ref}.",
            default => null,
        };
    }

    public static function send(?string $phone, string $text, ?Person $person = null, string $event = 'message'): bool
    {
        $driver = config('services.sms.driver', 'log');
        $to = self::normalize($phone);

        if ($driver === 'off' || ! $to) {
            return false;
        }

        try {
            match ($driver) {
                'beem' => self::beem($to, $text),
                'africastalking' => self::africasTalking($to, $text),
                default => Log::info("[SMS to +{$to}] {$text}"),
            };

            ActivityLog::record('sms.sent', "SMS ({$event}) to +{$to}" . ($driver === 'log' ? ' [log only]' : ''), $person, ['text' => $text]);

            return true;
        } catch (Throwable $e) {
            report($e);
            ActivityLog::record('sms.failed', "SMS ({$event}) to +{$to} failed: " . str($e->getMessage())->limit(150), $person);

            return false;
        }
    }

    private static function beem(string $to, string $text): void
    {
        Http::timeout(10)
            ->withBasicAuth((string) config('services.sms.key'), (string) config('services.sms.secret'))
            ->post('https://apisms.beem.africa/v1/send', [
                'source_addr' => config('services.sms.sender') ?: 'INFO',
                'encoding' => 0,
                'message' => $text,
                'recipients' => [['recipient_id' => 1, 'dest_addr' => $to]],
            ])
            ->throw();
    }

    private static function africasTalking(string $to, string $text): void
    {
        $username = (string) config('services.sms.username');
        $host = $username === 'sandbox' ? 'https://api.sandbox.africastalking.com' : 'https://api.africastalking.com';

        Http::timeout(10)
            ->asForm()
            ->withHeaders(['apiKey' => (string) config('services.sms.key'), 'Accept' => 'application/json'])
            ->post($host . '/version1/messaging', array_filter([
                'username' => $username,
                'to' => '+' . $to,
                'message' => $text,
                'from' => config('services.sms.sender'),
            ]))
            ->throw();
    }
}
