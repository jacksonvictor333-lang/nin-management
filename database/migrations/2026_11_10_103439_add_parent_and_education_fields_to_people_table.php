<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            // Parent / Guardian Information
            $table->string('father_name')->nullable();
            $table->string('father_phone', 20)->nullable();
            $table->string('mother_name')->nullable();
            $table->string('mother_phone', 20)->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone', 20)->nullable();

            // Education Information
            $table->string('education_level')->nullable();
            $table->string('primary_school')->nullable();
            $table->string('primary_completion_year', 4)->nullable();
            $table->string('secondary_school')->nullable();
            $table->string('secondary_completion_year', 4)->nullable();
            $table->string('higher_institution')->nullable();
            $table->string('course')->nullable();
            $table->string('qualification')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn([
                'father_name',
                'father_phone',
                'mother_name',
                'mother_phone',
                'guardian_name',
                'guardian_phone',
                'education_level',
                'primary_school',
                'primary_completion_year',
                'secondary_school',
                'secondary_completion_year',
                'higher_institution',
                'course',
                'qualification',
            ]);
        });
    }
};