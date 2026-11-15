<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\District;
use App\Models\Person;
use App\Models\Region;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DataQualityController extends Controller
{
    public function index()
    {
        $people = Person::orderBy('first_name')->get();
        $regions = $this->regionIndex();

        $issues = [
            'no_photo' => [
                'title' => 'No profile picture',
                'hint' => 'Add a photo from each person\'s profile (camera button).',
                'icon' => 'bi-camera',
                'severity' => 'warning',
                'people' => $people->filter(fn ($p) => blank($p->profile_picture)),
            ],
            'missing_file' => [
                'title' => 'Photo file missing on disk',
                'hint' => 'The record points to a picture that no longer exists. Upload a new one.',
                'icon' => 'bi-file-earmark-x',
                'severity' => 'serious',
                'people' => $people->filter(fn ($p) => filled($p->profile_picture) && ! Storage::disk('public')->exists($p->profile_picture)),
            ],
            'region_mismatch' => [
                'title' => 'Region name not linked to official list',
                'hint' => 'Typed differently (e.g. "Dar-es-salaam") or not linked to a region ID. Auto-fix links matching names.',
                'icon' => 'bi-geo-alt',
                'severity' => 'warning',
                'fix' => 'regions',
                'people' => $people->filter(fn ($p) => $this->regionNeedsFix($p, $regions)),
            ],
            'name_case' => [
                'title' => 'Names not properly capitalised',
                'hint' => 'All lowercase or ALL CAPS. Auto-fix converts to "Title Case".',
                'icon' => 'bi-type',
                'severity' => 'info',
                'fix' => 'names',
                'people' => $people->filter(fn ($p) => $this->nameNeedsFix($p)),
            ],
            'no_residence' => [
                'title' => 'No residential address',
                'hint' => 'Region, district, ward and village are empty.',
                'icon' => 'bi-house-x',
                'severity' => 'info',
                'people' => $people->filter(fn ($p) => blank($p->region)),
            ],
            'unlinked_village' => [
                'title' => 'Village typed manually (not from list)',
                'hint' => 'Village/street text exists but is not linked to the official village list. Edit the person and reselect it.',
                'icon' => 'bi-signpost',
                'severity' => 'info',
                'people' => $people->filter(fn ($p) => filled($p->village) && blank($p->village_id)),
            ],
            'no_contact' => [
                'title' => 'No phone or email',
                'hint' => 'The person cannot be contacted.',
                'icon' => 'bi-telephone-x',
                'severity' => 'info',
                'people' => $people->filter(fn ($p) => blank($p->phone) && blank($p->email)),
            ],
        ];

        $total = $people->count();
        $affected = collect($issues)->flatMap(fn ($i) => $i['people']->pluck('id'))->unique()->count();
        $score = $total ? (int) round(($total - $affected) / $total * 100) : 100;

        return view('data-quality.index', compact('issues', 'total', 'affected', 'score'));
    }

    /**
     * Unganisha majina ya mikoa (makazi na kuzaliwa) na orodha rasmi.
     */
    public function fixRegions()
    {
        $regions = $this->regionIndex();
        $fixed = 0;

        DB::transaction(function () use ($regions, &$fixed) {
            foreach (Person::all() as $person) {
                $changes = [];

                foreach ([['region', 'region_id', 'district', 'district_id'], ['birth_region', 'birth_region_id', 'birth_district', 'birth_district_id']] as [$name, $id, $dName, $dId]) {
                    $region = $regions[$this->key($person->{$name})] ?? null;

                    if (! $region) {
                        continue;
                    }

                    if ($person->{$name} !== $region->name) {
                        $changes[$name] = $region->name;
                    }
                    if ($person->{$id} !== $region->id) {
                        $changes[$id] = $region->id;
                    }

                    // Unganisha wilaya pia kama jina lake linapatikana ndani ya mkoa huo.
                    if (filled($person->{$dName}) && blank($person->{$dId})) {
                        $district = District::where('region_id', $region->id)->get()
                            ->first(fn ($d) => $this->key($d->name) === $this->key($person->{$dName}));

                        if ($district) {
                            $changes[$dName] = $district->name;
                            $changes[$dId] = $district->id;
                        }
                    }
                }

                if ($changes) {
                    $person->update($changes);
                    $fixed++;
                }
            }
        });

        ActivityLog::record('data.regions_fixed', "Linked region names to the official list for {$fixed} records", null, ['records' => $fixed]);

        return back()->with('success', "Region names fixed for {$fixed} " . str('record')->plural($fixed) . '.');
    }

    /**
     * Rekebisha herufi za majina: "juma" / "JUMA" -> "Juma".
     */
    public function fixNames()
    {
        $fixed = 0;

        DB::transaction(function () use (&$fixed) {
            foreach (Person::all() as $person) {
                if (! $this->nameNeedsFix($person)) {
                    continue;
                }

                $changes = [];
                foreach (['first_name', 'middle_name', 'last_name'] as $field) {
                    if (filled($person->{$field})) {
                        $changes[$field] = $this->titleCase($person->{$field});
                    }
                }

                $person->update($changes);
                $fixed++;
            }
        });

        ActivityLog::record('data.names_fixed', "Fixed name capitalisation for {$fixed} records", null, ['records' => $fixed]);

        return back()->with('success', "Name capitalisation fixed for {$fixed} " . str('record')->plural($fixed) . '.');
    }

    // ------------------------------------------------------------------

    private function regionIndex(): Collection
    {
        $regions = Region::all()->keyBy(fn ($r) => $this->key($r->name));

        // Majina mbadala yanayotumika mara kwa mara.
        foreach (['dar' => 'daressalaam', 'dsm' => 'daressalaam', 'pwanicoast' => 'pwani', 'coast' => 'pwani'] as $alias => $canonical) {
            if (isset($regions[$canonical])) {
                $regions[$alias] = $regions[$canonical];
            }
        }

        return $regions;
    }

    private function regionNeedsFix(Person $person, Collection $regions): bool
    {
        foreach ([['region', 'region_id'], ['birth_region', 'birth_region_id']] as [$name, $id]) {
            if (blank($person->{$name})) {
                continue;
            }

            $region = $regions[$this->key($person->{$name})] ?? null;

            if (! $region || $person->{$name} !== $region->name || $person->{$id} !== $region->id) {
                return true;
            }
        }

        return false;
    }

    private function nameNeedsFix(Person $person): bool
    {
        foreach (['first_name', 'middle_name', 'last_name'] as $field) {
            $value = (string) $person->{$field};

            if ($value !== '' && $value !== $this->titleCase($value)
                && ($value === mb_strtolower($value) || $value === mb_strtoupper($value))) {
                return true;
            }
        }

        return false;
    }

    private function titleCase(string $value): string
    {
        return mb_convert_case(mb_strtolower(trim($value)), MB_CASE_TITLE, 'UTF-8');
    }

    private function key(?string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $value));
    }
}
