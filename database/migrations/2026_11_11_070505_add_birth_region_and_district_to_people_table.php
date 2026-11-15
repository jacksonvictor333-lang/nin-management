<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            if (!Schema::hasColumn('people', 'birth_region')) {
                $table->string('birth_region')->nullable();
            }

            if (!Schema::hasColumn('people', 'birth_district')) {
                $table->string('birth_district')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn(['birth_region', 'birth_district']);
        });
    }
};