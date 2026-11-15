<?php

namespace App\Support;

use App\Models\Person;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Hugundua watu wanaoweza kuwa wamesajiliwa mara mbili.
 *
 * Mtu anashukiwa kuwa nakala ikiwa jina lake linafanana (makosa madogo ya
 * tahajia yanaruhusiwa, majina kugeuzwa pia) NA:
 *   - tarehe ya kuzaliwa ni ileile, au
 *   - namba ya simu ni ileile.
 */
class DuplicateFinder
{
    /** Tofauti ya juu ya herufi (Levenshtein) inayoruhusiwa kwa jina. */
    private const MAX_DISTANCE = 2;

    /**
     * Watu waliopo wanaofanana na taarifa mpya (kwa usajili au CSV).
     *
     * @return Collection<int, array{person: Person, reasons: list<string>}>
     */
    public function candidates(array $data, ?int $exceptId = null): Collection
    {
        $first = $data['first_name'] ?? '';
        $last = $data['last_name'] ?? '';
        $middle = $data['middle_name'] ?? null;
        $phone = $this->phone($data['phone'] ?? null);
        $dob = filled($data['date_of_birth'] ?? null) ? Carbon::parse($data['date_of_birth'])->toDateString() : null;

        if (blank($first) || blank($last) || (! $dob && ! $phone)) {
            return collect();
        }

        $pool = Person::anyStage()
            ->where('stage', '!=', Person::REJECTED)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->where(function ($q) use ($dob, $phone) {
                if ($dob) {
                    $q->orWhereDate('date_of_birth', $dob);
                }
                if ($phone) {
                    // Simu imehifadhiwa kwa miundo tofauti; linganisha tarakimu 9 za mwisho.
                    $q->orWhere('phone', 'like', '%' . substr($phone, -9));
                }
            })
            ->limit(200)
            ->get();

        return $pool
            ->map(function (Person $p) use ($first, $middle, $last, $dob, $phone) {
                if (! $this->namesMatch($first, $middle, $last, $p->first_name, $p->middle_name, $p->last_name)) {
                    return null;
                }

                $reasons = ['similar name'];
                if ($dob && $p->date_of_birth?->toDateString() === $dob) {
                    $reasons[] = 'same date of birth';
                }
                if ($phone && $this->phone($p->phone) === $phone) {
                    $reasons[] = 'same phone number';
                }

                return count($reasons) > 1 ? ['person' => $p, 'reasons' => $reasons] : null;
            })
            ->filter()
            ->values();
    }

    /**
     * Jozi zote za watu wanaoshukiwa kujirudia ndani ya rejista (Data Quality).
     *
     * @return Collection<int, array{a: Person, b: Person, reasons: list<string>}>
     */
    public function pairs(): Collection
    {
        $people = Person::orderBy('id')->get();
        $pairs = [];
        $seen = [];

        $groups = [
            'same date of birth' => $people->groupBy(fn ($p) => $p->date_of_birth?->toDateString()),
            'same phone number' => $people->filter(fn ($p) => $this->phone($p->phone))->groupBy(fn ($p) => $this->phone($p->phone)),
        ];

        foreach ($groups as $reason => $grouped) {
            foreach ($grouped as $key => $group) {
                if ($key === '' || $group->count() < 2) {
                    continue;
                }

                $list = $group->values();
                for ($i = 0; $i < $list->count(); $i++) {
                    for ($j = $i + 1; $j < $list->count(); $j++) {
                        [$a, $b] = [$list[$i], $list[$j]];
                        if (! $this->namesMatch($a->first_name, $a->middle_name, $a->last_name, $b->first_name, $b->middle_name, $b->last_name)) {
                            continue;
                        }

                        $k = $a->id . '-' . $b->id;
                        if (isset($seen[$k])) {
                            $pairs[$seen[$k]]['reasons'][] = $reason;
                            continue;
                        }
                        $seen[$k] = count($pairs);
                        $pairs[] = ['a' => $a, 'b' => $b, 'reasons' => ['similar name', $reason]];
                    }
                }
            }
        }

        return collect($pairs);
    }

    /**
     * Majina yanafanana ikiwa jina la kwanza na la mwisho yanakaribiana
     * (au yamegeuzwa), au jina la kati la mmoja ndilo la kwanza la mwingine.
     */
    public function namesMatch(?string $f1, ?string $m1, ?string $l1, ?string $f2, ?string $m2, ?string $l2): bool
    {
        [$f1, $m1, $l1, $f2, $m2, $l2] = array_map([$this, 'norm'], [$f1, $m1, $l1, $f2, $m2, $l2]);

        if ($f1 === '' || $l1 === '' || $f2 === '' || $l2 === '') {
            return false;
        }

        $close = fn (string $a, string $b) => $a !== '' && $b !== '' && ($a === $b || levenshtein($a, $b) <= min(self::MAX_DISTANCE, intdiv(max(strlen($a), strlen($b)), 3)));

        return ($close($f1, $f2) && $close($l1, $l2))           // Juma Mushi ~ Jumaa Mushi
            || ($close($f1, $l2) && $close($l1, $f2))           // Mushi Juma (imegeuzwa)
            || ($close($l1, $l2) && ($close($f1, $m2) || $close($m1, $f2))); // jina la kati kutumika kama la kwanza
    }

    private function norm(?string $name): string
    {
        return preg_replace('/[^a-z]/', '', Str::lower(Str::ascii((string) $name)));
    }

    /** Tarakimu 9 za mwisho za simu (0754123456 = +255754123456). */
    private function phone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return strlen($digits) >= 9 ? substr($digits, -9) : null;
    }
}
