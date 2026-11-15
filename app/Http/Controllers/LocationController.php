<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Ward;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    /**
     * Get districts belonging to a selected region.
     */
    public function districts(string $regionId): JsonResponse
    {
        $districts = District::where('region_id', $regionId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($districts);
    }

    /**
     * Get wards belonging to a selected district.
     */
    public function wards(string $districtId): JsonResponse
    {
        $wards = Ward::where('district_id', $districtId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($wards);
    }

    /**
     * Get villages and streets belonging to a selected ward.
     */
    public function villages(string $wardId): JsonResponse
    {
        $villages = Ward::findOrFail($wardId)
            ->villages()
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        return response()->json($villages);
    }
}
