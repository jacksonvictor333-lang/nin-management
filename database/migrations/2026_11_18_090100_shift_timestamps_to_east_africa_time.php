<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mfumo ulikuwa unahifadhi muda kwa UTC. Sasa unatumia saa za Afrika Mashariki
 * (Africa/Dar_es_Salaam, UTC+3), kwa hiyo muda wote uliohifadhiwa unasogezwa
 * mbele saa 3 ili usomeke sawa. Tarehe pekee (mf. tarehe ya kuzaliwa) haziguswi.
 */
return new class extends Migration
{
    private const SKIP = ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'];

    public function up(): void
    {
        $this->shift('+');
    }

    public function down(): void
    {
        $this->shift('-');
    }

    private function shift(string $sign): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return; // SQLite ya majaribio huundwa upya kila mara.
        }

        $columns = DB::select(
            "SELECT TABLE_NAME AS t, COLUMN_NAME AS c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE IN ('datetime', 'timestamp')"
        );

        foreach ($columns as $col) {
            if (in_array($col->t, self::SKIP, true)) {
                continue;
            }

            $fn = $sign === '+' ? 'DATE_ADD' : 'DATE_SUB';
            DB::statement("UPDATE `{$col->t}` SET `{$col->c}` = {$fn}(`{$col->c}`, INTERVAL 3 HOUR) WHERE `{$col->c}` IS NOT NULL");
        }
    }
};
