<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            // Rekodi "zilizohifadhiwa" (archived) badala ya kufutwa kabisa.
            $table->softDeletes();
            $table->string('archive_reason', 255)->nullable()->after('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn('archive_reason');
            $table->dropSoftDeletes();
        });
    }
};
