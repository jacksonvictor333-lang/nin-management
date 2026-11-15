<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ongeza foreign keys za maeneo kwenye jedwali la people.
 *
 * Safu za majina (region, district, ...) zinabaki kwa ajili ya kuonyesha,
 * lakini ID ndizo chanzo cha kuaminika kwa ripoti na uchujaji.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('birth_region_id', 20)->nullable()->after('birth_district');
            $table->string('birth_district_id', 20)->nullable()->after('birth_region_id');

            $table->string('region_id', 20)->nullable()->after('village');
            $table->string('district_id', 20)->nullable()->after('region_id');
            $table->string('ward_id', 20)->nullable()->after('district_id');
            $table->unsignedBigInteger('village_id')->nullable()->after('ward_id');

            $table->foreign('birth_region_id')->references('id')->on('regions')->nullOnDelete();
            $table->foreign('birth_district_id')->references('id')->on('districts')->nullOnDelete();
            $table->foreign('region_id')->references('id')->on('regions')->nullOnDelete();
            $table->foreign('district_id')->references('id')->on('districts')->nullOnDelete();
            $table->foreign('ward_id')->references('id')->on('wards')->nullOnDelete();
            $table->foreign('village_id')->references('id')->on('villages')->nullOnDelete();
        });

        $this->backfill();
    }

    /**
     * Jaza ID kwa rekodi za zamani kwa kulinganisha majina kwa mpangilio
     * (mkoa → wilaya ndani ya mkoa → kata ndani ya wilaya → kijiji ndani ya kata).
     * Jina lisilolingana linaacha ID kuwa NULL.
     */
    private function backfill(): void
    {
        $find = function (string $table, ?string $name, ?string $parentColumn = null, $parentId = null) {
            if ($name === null || trim($name) === '' || ($parentColumn && $parentId === null)) {
                return null;
            }

            $query = DB::table($table)->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))]);

            if ($parentColumn) {
                $query->where($parentColumn, $parentId);
            }

            return $query->value('id');
        };

        DB::table('people')->orderBy('id')->each(function ($person) use ($find) {
            $birthRegionId = $find('regions', $person->birth_region);
            $regionId = $find('regions', $person->region);
            $districtId = $find('districts', $person->district, 'region_id', $regionId);
            $wardId = $find('wards', $person->ward, 'district_id', $districtId);

            DB::table('people')->where('id', $person->id)->update([
                'birth_region_id' => $birthRegionId,
                'birth_district_id' => $find('districts', $person->birth_district, 'region_id', $birthRegionId),
                'region_id' => $regionId,
                'district_id' => $districtId,
                'ward_id' => $wardId,
                'village_id' => $find('villages', $person->village, 'ward_id', $wardId),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $columns = ['birth_region_id', 'birth_district_id', 'region_id', 'district_id', 'ward_id', 'village_id'];

            foreach ($columns as $column) {
                $table->dropForeign([$column]);
            }

            $table->dropColumn($columns);
        });
    }
};
