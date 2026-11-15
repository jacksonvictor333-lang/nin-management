<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\District;
use App\Models\Region;
use App\Models\Village;
use App\Models\Ward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Kuvinjari na kusimamia maeneo: Mkoa › Wilaya › Kata › Kijiji/Mtaa.
 */
class GeoController extends Controller
{
    public function index(Request $request)
    {
        $region = $request->query('region') ? Region::find($request->query('region')) : null;
        $district = $request->query('district') ? District::find($request->query('district')) : null;
        $ward = $request->query('ward') ? Ward::find($request->query('ward')) : null;

        // Hakikisha ngazi zinaendana (mf. ward ya district nyingine).
        if ($ward) {
            $district = $ward->district;
            $region = $district?->region;
        } elseif ($district) {
            $region = $district->region;
        }

        $search = trim((string) $request->query('q', ''));

        $totals = [
            'regions' => Region::count(),
            'districts' => District::count(),
            'wards' => Ward::count(),
            'villages' => Village::count(),
        ];

        if ($ward) {
            $level = 'villages';
            $items = $ward->villages()
                ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->orderBy('name')->get();
        } elseif ($district) {
            $level = 'wards';
            $items = $district->wards()->withCount('villages')
                ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->orderBy('name')->get();
        } elseif ($region) {
            $level = 'districts';
            $items = $region->districts()->withCount('wards')
                ->addSelect(['villages_count' => Village::selectRaw('COUNT(*)')
                    ->join('wards', 'wards.id', '=', 'villages.ward_id')
                    ->whereColumn('wards.district_id', 'districts.id')])
                ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->orderBy('name')->get();
        } else {
            $level = 'regions';
            $items = Region::withCount('districts')
                ->addSelect([
                    'wards_count' => Ward::selectRaw('COUNT(*)')
                        ->join('districts', 'districts.id', '=', 'wards.district_id')
                        ->whereColumn('districts.region_id', 'regions.id'),
                    'villages_count' => Village::selectRaw('COUNT(*)')
                        ->join('wards', 'wards.id', '=', 'villages.ward_id')
                        ->join('districts', 'districts.id', '=', 'wards.district_id')
                        ->whereColumn('districts.region_id', 'regions.id'),
                ])
                ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->orderBy('name')->get();
        }

        // Idadi ya watu wanaoishi katika kila eneo lililoonyeshwa.
        $peopleColumn = ['regions' => 'region_id', 'districts' => 'district_id', 'wards' => 'ward_id', 'villages' => 'village_id'][$level];
        $peopleCounts = DB::table('people')
            ->whereNull('deleted_at')
            ->whereIn($peopleColumn, $items->pluck('id'))
            ->selectRaw("{$peopleColumn} as k, COUNT(*) as c")
            ->groupBy($peopleColumn)
            ->pluck('c', 'k');

        return view('geo.index', compact('region', 'district', 'ward', 'level', 'items', 'totals', 'search', 'peopleCounts'));
    }

    /**
     * Ongeza kijiji/mtaa kwenye kata (mf. vijiji vya Zanzibar ambavyo havipo kwenye data).
     */
    public function storeVillage(Request $request, Ward $ward)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:180',
                Rule::unique('villages', 'name')->where('ward_id', $ward->id),
            ],
            'type' => ['required', Rule::in(['Village', 'Street', 'Hamlet', 'Other'])],
        ], [
            'name.unique' => 'This village/street already exists in ' . $ward->name . '.',
        ]);

        $village = $ward->villages()->create([
            'name' => trim($data['name']),
            'type' => $data['type'],
        ]);

        ActivityLog::record('location.village_added', "Added {$village->type} \"{$village->name}\" to {$ward->name} ward", $village);

        return back()->with('success', "{$village->name} added to {$ward->name}.");
    }

    public function destroyVillage(Village $village)
    {
        $inUse = DB::table('people')->where('village_id', $village->id)->count();

        if ($inUse > 0) {
            return back()->with('error', "{$village->name} cannot be deleted: {$inUse} " . str('person')->plural($inUse) . ' live there.');
        }

        $name = $village->name;
        $wardName = $village->ward?->name;
        $village->delete();

        ActivityLog::record('location.village_deleted', "Deleted village/street \"{$name}\" from {$wardName} ward");

        return back()->with('success', "{$name} deleted.");
    }
}
