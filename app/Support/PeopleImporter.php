<?php

namespace App\Support;

use App\Models\District;
use App\Models\Region;
use App\Models\Village;
use App\Models\Ward;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Kusoma CSV ya watu wengi na kukagua kila safu kabla ya kuhifadhi.
 * Maeneo yanaandikwa kwa majina (Mkoa, Wilaya, Kata, Kijiji) na yanaunganishwa
 * na orodha rasmi; jina lisilopatikana ni kosa.
 */
class PeopleImporter
{
    public const MAX_ROWS = 1000;

    /** Safu za template, kwa mpangilio. */
    public const COLUMNS = [
        'first_name', 'middle_name', 'last_name', 'date_of_birth', 'gender', 'nationality',
        'birth_region', 'birth_district',
        'region', 'district', 'ward', 'village',
        'phone', 'email',
        'father_name', 'father_phone', 'mother_name', 'mother_phone', 'guardian_name', 'guardian_phone',
        'education_level', 'primary_school', 'primary_completion_year', 'secondary_school', 'secondary_completion_year',
        'higher_institution', 'course', 'qualification',
    ];

    public const REQUIRED = ['first_name', 'last_name', 'date_of_birth', 'gender'];

    private array $regionCache = [];

    public function __construct(private DuplicateFinder $duplicates)
    {
    }

    /**
     * @return list<array{line: int, data: array, errors: list<string>, warnings: list<string>}>
     */
    public function parse(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new RuntimeException('Could not read the file.');
        }

        $first = fgets($handle);
        if ($first === false) {
            throw new RuntimeException('The file is empty.');
        }
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first); // BOM ya Excel
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';

        $headers = array_map(fn ($h) => Str::snake(trim(Str::lower((string) $h))), str_getcsv($first, $delimiter));
        $missing = array_diff(self::REQUIRED, $headers);
        if ($missing) {
            throw new RuntimeException('Missing required columns: ' . implode(', ', $missing) . '. Download the template to see the expected columns.');
        }

        $rows = [];
        $seenInFile = [];
        $line = 1;

        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;
            if (count(array_filter($values, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue; // safu tupu
            }
            if (count($rows) >= self::MAX_ROWS) {
                throw new RuntimeException('The file has more than ' . self::MAX_ROWS . ' rows. Split it into smaller files.');
            }

            $raw = [];
            foreach ($headers as $i => $h) {
                if (in_array($h, self::COLUMNS, true)) {
                    $raw[$h] = trim((string) ($values[$i] ?? ''));
                }
            }

            $rows[] = $this->row($line, $raw, $seenInFile);
        }

        fclose($handle);

        if (! $rows) {
            throw new RuntimeException('The file has a header but no data rows.');
        }

        return $rows;
    }

    private function row(int $line, array $raw, array &$seenInFile): array
    {
        $errors = [];
        $warnings = [];
        $data = array_map(fn ($v) => $v === '' ? null : $v, $raw);

        $data['date_of_birth'] = $this->date($raw['date_of_birth'] ?? '');
        if (filled($raw['date_of_birth'] ?? '') && ! $data['date_of_birth']) {
            $errors[] = "Date of birth \"{$raw['date_of_birth']}\" is not a valid date (use YYYY-MM-DD or DD/MM/YYYY).";
        }
        $data['gender'] = $this->gender($raw['gender'] ?? '');
        $data['nationality'] = $data['nationality'] ?? 'Tanzanian';
        $data['status'] = 'Active';

        $year = (int) date('Y');
        $validator = Validator::make($data, [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', Rule::in(['Male', 'Female'])],
            'nationality' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'], 'father_phone' => ['nullable', 'string', 'max:20'],
            'mother_name' => ['nullable', 'string', 'max:255'], 'mother_phone' => ['nullable', 'string', 'max:20'],
            'guardian_name' => ['nullable', 'string', 'max:255'], 'guardian_phone' => ['nullable', 'string', 'max:20'],
            'education_level' => ['nullable', 'string', 'max:100'],
            'primary_school' => ['nullable', 'string', 'max:255'],
            'primary_completion_year' => ['nullable', 'integer', 'between:1950,' . $year],
            'secondary_school' => ['nullable', 'string', 'max:255'],
            'secondary_completion_year' => ['nullable', 'integer', 'between:1950,' . $year],
            'higher_institution' => ['nullable', 'string', 'max:255'],
            'course' => ['nullable', 'string', 'max:255'],
            'qualification' => ['nullable', 'string', 'max:255'],
        ], [
            'gender.in' => 'Gender must be Male or Female (M/F, Me/Ke are accepted).',
            'gender.required' => 'Gender is required (Male or Female).',
        ]);

        foreach ($validator->errors()->all() as $message) {
            if (! ($data['date_of_birth'] === null && str_contains($message, 'date of birth') && $errors)) {
                $errors[] = $message;
            }
        }

        // Maeneo kwa majina -> ID rasmi.
        $errors = array_merge($errors, $this->locations($data));

        if (! $errors) {
            $matches = $this->duplicates->candidates($data);
            foreach ($matches as $m) {
                $warnings[] = "May already be registered: {$m['person']->full_name} (NIN {$m['person']->nin}) - " . implode(', ', $m['reasons']) . '.';
            }

            $key = Str::lower(Str::ascii($data['first_name'] . '|' . $data['last_name'] . '|' . $data['date_of_birth']));
            if (isset($seenInFile[$key])) {
                $warnings[] = "Same name and date of birth as row {$seenInFile[$key]} in this file.";
            } else {
                $seenInFile[$key] = $line;
            }
        }

        return ['line' => $line, 'data' => $data, 'errors' => array_values(array_unique($errors)), 'warnings' => $warnings];
    }

    /** Huweka *_id na majina rasmi kwenye $data. Inarudisha makosa. */
    private function locations(array &$data): array
    {
        $errors = [];

        $pairs = [
            ['birth_region', 'birth_district', null, null],
            ['region', 'district', 'ward', 'village'],
        ];

        foreach ($pairs as [$rKey, $dKey, $wKey, $vKey]) {
            $region = $district = $ward = null;

            if (filled($data[$rKey] ?? null)) {
                $region = $this->findRegion($data[$rKey]);
                $this->link($data, $rKey, $region, $errors, "Region \"{$data[$rKey]}\" was not found.");
            } elseif (filled($data[$dKey] ?? null) || ($wKey && (filled($data[$wKey] ?? null) || filled($data[$vKey] ?? null)))) {
                $errors[] = 'Give the ' . str_replace('_', ' ', $rKey) . ' when a district, ward or village is given.';
            }

            if ($region && filled($data[$dKey] ?? null)) {
                $district = $this->findIn(District::where('region_id', $region->id), $data[$dKey]);
                $this->link($data, $dKey, $district, $errors, "District \"{$data[$dKey]}\" was not found in {$region->name}.");
            }

            if ($wKey && $district && filled($data[$wKey] ?? null)) {
                $ward = $this->findIn(Ward::where('district_id', $district->id), $data[$wKey]);
                $this->link($data, $wKey, $ward, $errors, "Ward \"{$data[$wKey]}\" was not found in {$district->name}.");
            } elseif ($wKey && filled($data[$wKey] ?? null) && ! $district && $region) {
                $errors[] = 'Give the district when a ward is given.';
            }

            if ($vKey && $ward && filled($data[$vKey] ?? null)) {
                $village = $this->findIn(Village::where('ward_id', $ward->id), $data[$vKey]);
                $this->link($data, $vKey, $village, $errors, "Village/street \"{$data[$vKey]}\" was not found in {$ward->name} ward.");
            } elseif ($vKey && filled($data[$vKey] ?? null) && ! $ward && $district) {
                $errors[] = 'Give the ward when a village/street is given.';
            }
        }

        return $errors;
    }

    /** Weka jina rasmi na ID ya eneo lililopatikana, au ongeza kosa. */
    private function link(array &$data, string $key, $model, array &$errors, string $notFound): void
    {
        if ($model) {
            $data[$key] = $model->name;
            $data[$key . '_id'] = $model->id;
        } else {
            $errors[] = $notFound;
        }
    }

    private function findRegion(string $name): ?Region
    {
        $this->regionCache = $this->regionCache ?: Region::get(['id', 'name'])->keyBy(fn ($r) => $this->key($r->name))->all();

        return $this->regionCache[$this->key($name)] ?? null;
    }

    private function findIn($query, string $name)
    {
        return $query->get(['id', 'name'])->first(fn ($m) => $this->key($m->name) === $this->key($name));
    }

    /** "Dar es Salaam" = "Dar-es-salaam" = "DAR ES SALAAM". */
    private function key(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($name)));
    }

    private function date(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d'] as $format) {
            $date = \DateTime::createFromFormat('!' . $format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        // Excel inaweza kuhifadhi tarehe kama namba (siku tangu 1899-12-30).
        if (ctype_digit($value) && (int) $value > 10000 && (int) $value < 80000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        return null;
    }

    private function gender(string $value): ?string
    {
        return match (Str::lower(trim($value))) {
            'm', 'male', 'me', 'mume', 'mwanaume', 'kiume' => 'Male',
            'f', 'female', 'ke', 'mke', 'mwanamke', 'kike' => 'Female',
            '' => null,
            default => $value,
        };
    }
}
