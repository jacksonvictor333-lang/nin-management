
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jedwali jipya tayari lina 'nin' (create migration ilisasishwa): ruka.
        if (Schema::hasColumn('nin_verifications', 'nin')) {
            return;
        }

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
