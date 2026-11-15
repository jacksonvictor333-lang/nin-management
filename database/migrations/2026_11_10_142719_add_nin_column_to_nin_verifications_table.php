
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nin_verifications', function (Blueprint $table) {
            $table->string('nin', 20)->index();
        });
    }

    public function down(): void
    {
        Schema::table('nin_verifications', function (Blueprint $table) {
            $table->dropIndex(['nin']);
            $table->dropColumn('nin');
        });
    }
};
