<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TanzaniaAdministrativeDivisionsSeeder extends Seeder
{
    public function run(): void
    {
        $file = database_path(
            'seeders/data/tanzania-administrative-divisions.json'
        );

        if (!file_exists($file)) {
            throw new RuntimeException(
                'Tanzania administrative divisions JSON file was not found.'
            );
        }

        $json = json_decode(
            file_get_contents($file),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $data = $json['data'] ?? [];

        $regions = $data['region'] ?? [];
        $districts = $data['district'] ?? [];
        $wards = $data['ward'] ?? [];

        if (
            count($regions) !== 31 ||
            count($districts) !== 170 ||
            count($wards) !== 3643
        ) {
            throw new RuntimeException(
                'Dataset counts do not match the expected 31 regions, 170 districts and 3643 wards. Import cancelled.'
            );
        }

        $now = now();

        // 1. REGIONS
        $regionRows = [];

        foreach ($regions as $region) {
            $regionRows[] = [
                'id' => $region['id'],
                'name' => $region['name_local'],
                'name_en' => $region['name_en'] ?? null,
                'slug' => $region['slug'] ?? null,
                'latitude' => $region['lat'] ?? null,
                'longitude' => $region['lon'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('regions')->upsert(
            $regionRows,
            ['id'],
            [
                'name',
                'name_en',
                'slug',
                'latitude',
                'longitude',
                'updated_at',
            ]
        );

        // 2. DISTRICTS
        $districtRows = [];

        foreach ($districts as $district) {
            $districtRows[] = [
                'id' => $district['id'],
                'region_id' => $district['parent_id'],
                'name' => $district['name_local'],
                'name_en' => $district['name_en'] ?? null,
                'slug' => $district['slug'] ?? null,
                'latitude' => $district['lat'] ?? null,
                'longitude' => $district['lon'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($districtRows, 500) as $chunk) {
            DB::table('districts')->upsert(
                $chunk,
                ['id'],
                [
                    'region_id',
                    'name',
                    'name_en',
                    'slug',
                    'latitude',
                    'longitude',
                    'updated_at',
                ]
            );
        }

        // 3. WARDS
        $wardRows = [];

        foreach ($wards as $ward) {
            $wardRows[] = [
                'id' => $ward['id'],
                'district_id' => $ward['parent_id'],
                'name' => $ward['name_local'],
                'name_en' => $ward['name_en'] ?? null,
                'slug' => $ward['slug'] ?? null,
                'latitude' => $ward['lat'] ?? null,
                'longitude' => $ward['lon'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($wardRows, 500) as $chunk) {
            DB::table('wards')->upsert(
                $chunk,
                ['id'],
                [
                    'district_id',
                    'name',
                    'name_en',
                    'slug',
                    'latitude',
                    'longitude',
                    'updated_at',
                ]
            );
        }

        $this->command?->info('Tanzania administrative divisions imported successfully.');
        $this->command?->info('Regions: ' . count($regionRows));
        $this->command?->info('Districts: ' . count($districtRows));
        $this->command?->info('Wards: ' . count($wardRows));
        $this->command?->warn(
            'Village and street records have not been imported because they are not included in this dataset.'
        );
    }
}
