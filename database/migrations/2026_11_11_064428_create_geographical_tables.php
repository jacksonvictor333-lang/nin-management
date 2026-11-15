
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->string('id', 20)->primary();
            $table->string('name', 150);
            $table->string('name_en', 150)->nullable();
            $table->string('slug', 180)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->string('id', 20)->primary();

            $table->string('region_id', 20);
            $table->string('name', 150);
            $table->string('name_en', 150)->nullable();
            $table->string('slug', 180)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->index('region_id');

            $table->foreign('region_id')
                ->references('id')
                ->on('regions')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        Schema::create('wards', function (Blueprint $table) {
            $table->string('id', 20)->primary();

            $table->string('district_id', 20);
            $table->string('name', 150);
            $table->string('name_en', 150)->nullable();
            $table->string('slug', 180)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->index('district_id');

            $table->foreign('district_id')
                ->references('id')
                ->on('districts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        // Table is ready for verified village/street data later.
        Schema::create('villages', function (Blueprint $table) {
            $table->id();

            $table->string('ward_id', 20);
            $table->string('name', 180);
            $table->string('name_en', 180)->nullable();
            $table->enum('type', [
                'Village',
                'Street',
                'Hamlet',
                'Other'
            ])->default('Other');

            $table->timestamps();

            $table->index('ward_id');

            $table->foreign('ward_id')
                ->references('id')
                ->on('wards')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('villages');
        Schema::dropIfExists('wards');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('regions');
    }
};
