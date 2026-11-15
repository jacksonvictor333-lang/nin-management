<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Ingiza Vijiji/Mitaa kwa kila Kata, kulingana na Mkoa na Wilaya husika.
 *
 * Chanzo: HackEAC/tanzania-locations-db (CSV moja kwa kila mkoa, Tanzania Bara)
 * https://github.com/HackEAC/tanzania-locations-db  (GPL-3.0, angalia data/villages/LICENSE)
 *
 * Safu za CSV: REGION, POSTCODE, DISTRICT, POSTCODE, WARD, POSTCODE, STREET, PLACES
 *
 * Mtiririko wa kulinganisha:
 *  1. Mkoa: kwa jina.
 *  2. Kata: jina kamili ndani ya mkoa (wilaya ikitumika kutofautisha majina yanayojirudia).
 *  3. Wilaya ya CSV → wilaya ya DB kwa "kura" za kata zilizolingana.
 *  4. Kata zisizolingana: tahajia karibu (Levenshtein) ndani ya wilaya husika.
 *  5. Bado hazipatikani: ni kata/wilaya mpya → zinaongezwa chini ya wilaya/mkoa sahihi.
 *
 * Seeder hii inaweza kuendeshwa tena bila kurudia rekodi.
 * Endesha: php artisan db:seed --class=TanzaniaVillagesSeeder
 */
class TanzaniaVillagesSeeder extends Seeder
{
    private array $stats = [
        'villages' => 0,
        'skipped_existing' => 0,
        'wards_exact' => 0,
        'wards_fuzzy' => 0,
        'wards_created' => 0,
        'districts_created' => 0,
    ];

    public function run(): void
    {
        $files = glob(database_path('seeders/data/villages/*.csv'));

        if (!$files) {
            throw new RuntimeException('No village CSV files found in database/seeders/data/villages.');
        }

        if (DB::table('wards')->count() === 0) {
            throw new RuntimeException('Run TanzaniaAdministrativeDivisionsSeeder first (wards table is empty).');
        }

        $regions = DB::table('regions')->get()->keyBy(fn ($r) => $this->key($r->name));

        DB::transaction(function () use ($files, $regions) {
            foreach ($files as $file) {
                $this->importRegionFile($file, $regions);
            }
        });

        foreach ($this->stats as $label => $count) {
            $this->command?->info(str_pad(str_replace('_', ' ', ucfirst($label)), 20) . ': ' . number_format($count));
        }
    }

    /**
     * Soma CSV ya mkoa mmoja na upange: [wilaya][kata] => [mitaa...]
     */
    private function readCsv(string $file): array
    {
        $handle = fopen($file, 'r');
        fgetcsv($handle); // header

        $regionName = null;
        $tree = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 7) {
                continue;
            }

            [$region, , $district, , $ward, , $street] = $row;

            $street = $this->clean($street);

            if ($street === '' || $this->clean($ward) === '') {
                continue;
            }

            $regionName ??= $this->clean($region);
            $tree[$this->clean($district)][$this->clean($ward)][$this->key($street)] = $street;
        }

        fclose($handle);

        return [$regionName, $tree];
    }

    private function importRegionFile(string $file, $regions): void
    {
        [$regionName, $tree] = $this->readCsv($file);

        $region = $regions[$this->key($regionName)] ?? null;

        if (!$region) {
            $this->command?->warn("Region not found, skipped: {$regionName}");

            return;
        }

        $districts = DB::table('districts')->where('region_id', $region->id)->get()->keyBy('id');

        $wards = DB::table('wards')
            ->whereIn('district_id', $districts->keys())
            ->get(['id', 'district_id', 'name']);

        $wardsByKey = $wards->groupBy(fn ($w) => $this->key($w->name));

        // HATUA A: kata zinazolingana moja kwa moja, na kura za wilaya.
        $resolved = [];   // [csvDistrict][csvWard] => ward_id
        $votes = [];      // [csvDistrict][db_district_id] => count

        foreach ($tree as $csvDistrict => $csvWards) {
            foreach ($csvWards as $csvWard => $streets) {
                $candidates = $wardsByKey[$this->key($csvWard)] ?? collect();

                if ($candidates->count() === 1) {
                    $ward = $candidates->first();
                } elseif ($candidates->count() > 1) {
                    // Jina linajirudia ndani ya mkoa: tumia jina la wilaya kutofautisha.
                    $ward = $candidates->first(fn ($w) => $this->districtNameMatches(
                        $districts[$w->district_id]->name,
                        $csvDistrict
                    ));
                } else {
                    $ward = null;
                }

                if ($ward) {
                    $resolved[$csvDistrict][$csvWard] = $ward->id;
                    $votes[$csvDistrict][$ward->district_id] = ($votes[$csvDistrict][$ward->district_id] ?? 0) + 1;
                    $this->stats['wards_exact']++;
                }
            }
        }

        // HATUA B: tambua wilaya ya DB kwa kila wilaya ya CSV.
        $districtMap = [];

        foreach (array_keys($tree) as $csvDistrict) {
            $districtMap[$csvDistrict] = $this->resolveDistrict(
                $csvDistrict,
                $votes[$csvDistrict] ?? [],
                $districts,
                $region
            );
        }

        // HATUA C: kata zilizobaki: fuzzy ndani ya wilaya, vinginevyo unda mpya.
        foreach ($tree as $csvDistrict => $csvWards) {
            $districtId = $districtMap[$csvDistrict];

            foreach ($csvWards as $csvWard => $streets) {
                if (isset($resolved[$csvDistrict][$csvWard])) {
                    continue;
                }

                $districtWards = DB::table('wards')->where('district_id', $districtId)->get(['id', 'name']);

                $match = $this->closest($csvWard, $districtWards);

                if ($match) {
                    $resolved[$csvDistrict][$csvWard] = $match->id;
                    $this->stats['wards_fuzzy']++;

                    continue;
                }

                $resolved[$csvDistrict][$csvWard] = $this->createWard($districtId, $csvWard);
            }
        }

        // HATUA D: ingiza vijiji/mitaa (bila kurudia vilivyopo).
        $now = now();

        foreach ($tree as $csvDistrict => $csvWards) {
            $type = $this->isUrban($csvDistrict, $districts[$districtMap[$csvDistrict]]->name ?? '')
                ? 'Street'
                : 'Village';

            foreach ($csvWards as $csvWard => $streets) {
                $wardId = $resolved[$csvDistrict][$csvWard];

                $existing = DB::table('villages')
                    ->where('ward_id', $wardId)
                    ->pluck('name')
                    ->mapWithKeys(fn ($n) => [$this->key($n) => true]);

                $rows = [];

                foreach ($streets as $key => $street) {
                    if (isset($existing[$key])) {
                        $this->stats['skipped_existing']++;

                        continue;
                    }

                    $rows[] = [
                        'ward_id' => $wardId,
                        'name' => $this->titleCase($street),
                        'name_en' => null,
                        'type' => $type,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('villages')->insert($chunk);
                }

                $this->stats['villages'] += count($rows);
            }
        }

        $this->command?->line("  ✓ {$region->name}");
    }

    /**
     * Wilaya ya DB yenye kura nyingi; kama hakuna kura, linganisha kwa jina;
     * kama bado haipo, ni wilaya mpya → iunde.
     */
    private function resolveDistrict(string $csvDistrict, array $votes, $districts, $region): string
    {
        if ($votes) {
            arsort($votes);

            return (string) array_key_first($votes);
        }

        $byName = $districts->first(fn ($d) => $this->districtNameMatches($d->name, $csvDistrict));

        if ($byName) {
            return $byName->id;
        }

        $name = $this->titleCase($csvDistrict);

        $existing = DB::table('districts')
            ->where('region_id', $region->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->value('id');

        if ($existing) {
            return $existing;
        }

        $id = $this->nextId('districts', $region->id . 'N', 2);

        DB::table('districts')->insert([
            'id' => $id,
            'region_id' => $region->id,
            'name' => $name,
            'name_en' => $name,
            'slug' => Str::slug($name),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $districts[$id] = (object) ['id' => $id, 'name' => $name];

        $this->stats['districts_created']++;

        return $id;
    }

    private function createWard(string $districtId, string $csvWard): string
    {
        $name = $this->titleCase($csvWard);

        $id = $this->nextId('wards', $districtId . 'N', 3);

        DB::table('wards')->insert([
            'id' => $id,
            'district_id' => $districtId,
            'name' => $name,
            'name_en' => $name,
            'slug' => Str::slug($name),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->stats['wards_created']++;

        return $id;
    }

    /**
     * Kata yenye tahajia iliyo karibu zaidi ndani ya wilaya (tofauti ndogo tu).
     */
    private function closest(string $csvWard, $wards): ?object
    {
        $target = $this->key($csvWard);
        $allowed = strlen($target) >= 8 ? 2 : 1;

        $best = null;
        $bestDistance = PHP_INT_MAX;
        $tie = false;

        foreach ($wards as $ward) {
            $distance = levenshtein($target, $this->key($ward->name));

            if ($distance < $bestDistance) {
                [$best, $bestDistance, $tie] = [$ward, $distance, false];
            } elseif ($distance === $bestDistance) {
                $tie = true;
            }
        }

        return ($best && !$tie && $bestDistance <= $allowed) ? $best : null;
    }

    private function nextId(string $table, string $prefix, int $pad): string
    {
        $i = DB::table($table)->where('id', 'like', $prefix . '%')->count() + 1;

        do {
            $id = $prefix . str_pad((string) $i++, $pad, '0', STR_PAD_LEFT);
        } while (DB::table($table)->where('id', $id)->exists());

        return $id;
    }

    /**
     * "ARUSHA CBD" ≈ "Arusha Urban", "KOROGWE" ≈ "Korogwe".
     */
    private function districtNameMatches(string $dbName, string $csvName): bool
    {
        $strip = fn ($s) => preg_replace('/(cbd|urban|mji|township|authority|municipal|city|tc|mc|dc)$/', '', $this->key($s));

        return $strip($dbName) !== '' && $strip($dbName) === $strip($csvName);
    }

    private function isUrban(string $csvDistrict, string $dbDistrict): bool
    {
        return (bool) preg_match('/\b(CBD|URBAN|MJI|TOWNSHIP|MUNICIPAL|CITY)\b/i', $csvDistrict . ' ' . $dbDistrict)
            || in_array($this->key($csvDistrict), ['ilala', 'kinondoni', 'temeke', 'ubungo', 'kigamboni'], true);
    }

    private function clean(?string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $value));
    }

    private function key(?string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($this->clean($value)));
    }

    /**
     * "MJI MPYA" → "Mji Mpya", "DARAJA II" → "Daraja II".
     */
    private function titleCase(string $value): string
    {
        $words = explode(' ', mb_convert_case(mb_strtolower($this->clean($value)), MB_CASE_TITLE, 'UTF-8'));

        return implode(' ', array_map(
            fn ($w) => preg_match('/^(i{1,3}|iv|vi{0,3}|ix|x|cbd)$/i', $w) ? mb_strtoupper($w) : $w,
            $words
        ));
    }
}
