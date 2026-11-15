<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PharData;
use RuntimeException;

/**
 * Backup kamili ya mfumo kama faili moja la .tar.gz:
 *   database.sql   - muundo na data ya majedwali yote (inarejeshwa kwa `mysql`)
 *   files/...      - picha za watu na sahihi (storage/app/public)
 *   manifest.json  - tarehe, idadi ya rekodi, toleo
 *
 * Haitumii mysqldump wala ZipArchive (hazipo kila mahali): SQL inatengenezwa kwa PDO
 * na kumbukumbu inafungwa kwa PharData.
 */
class BackupManager
{
    public const DIR = 'backups';

    /** Folda za disk ya public zinazowekwa kwenye backup. */
    private const FILE_DIRS = ['profile-pictures', 'signatures'];

    public function create(?string $by = null): array
    {
        @set_time_limit(600);

        $dir = storage_path('app/' . self::DIR);
        File::ensureDirectoryExists($dir);

        $name = 'nin-backup-' . now()->format('Ymd-His');
        $work = storage_path('app/' . self::DIR . '/.tmp-' . $name);
        File::ensureDirectoryExists($work);

        try {
            $tables = $this->dumpDatabase($work . '/database.sql');

            $files = 0;
            foreach (self::FILE_DIRS as $folder) {
                foreach (Storage::disk('public')->allFiles($folder) as $path) {
                    $target = $work . '/files/' . $path;
                    File::ensureDirectoryExists(dirname($target));
                    File::copy(Storage::disk('public')->path($path), $target);
                    $files++;
                }
            }

            file_put_contents($work . '/manifest.json', json_encode([
                'app' => config('app.name'),
                'created_at' => now()->toIso8601String(),
                'created_by' => $by,
                'database' => DB::connection()->getDatabaseName(),
                'tables' => $tables,
                'files' => $files,
                'restore' => 'Import database.sql with: mysql -u root DATABASE < database.sql, then copy files/* into storage/app/public/',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $tar = $dir . '/' . $name . '.tar';
            $archive = new PharData($tar);
            $archive->buildFromDirectory($work);
            $archive->compress(\Phar::GZ);
            unset($archive);
            @unlink($tar);
        } finally {
            File::deleteDirectory($work);
        }

        $this->prune((int) config('services.nin.backup_keep', 14));

        return $this->info($name . '.tar.gz');
    }

    /** @return Collection<int, array{name: string, size: int, created_at: \Carbon\Carbon}> */
    public function list(): Collection
    {
        $dir = storage_path('app/' . self::DIR);

        return collect(File::exists($dir) ? File::files($dir) : [])
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.tar.gz'))
            ->map(fn ($f) => $this->info($f->getFilename()))
            ->sortByDesc('created_at')
            ->values();
    }

    public function path(string $name): string
    {
        // Jina tu (hakuna ../): kuzuia kupakua faili nje ya folda ya backup.
        if (! preg_match('/^nin-backup-\d{8}-\d{6}\.tar\.gz$/', $name)) {
            throw new RuntimeException('Invalid backup name.');
        }

        $path = storage_path('app/' . self::DIR . '/' . $name);
        if (! is_file($path)) {
            throw new RuntimeException('Backup not found.');
        }

        return $path;
    }

    public function delete(string $name): void
    {
        @unlink($this->path($name));
    }

    private function info(string $name): array
    {
        $path = storage_path('app/' . self::DIR . '/' . $name);

        return [
            'name' => $name,
            'size' => filesize($path),
            'created_at' => \Carbon\Carbon::createFromTimestamp(filemtime($path)),
        ];
    }

    private function prune(int $keep): void
    {
        if ($keep < 1) {
            return;
        }

        $this->list()->slice($keep)->each(fn ($b) => @unlink(storage_path('app/' . self::DIR . '/' . $b['name'])));
    }

    /**
     * Andika SQL ya majedwali yote. Inarudisha [jedwali => idadi ya safu].
     */
    private function dumpDatabase(string $file): array
    {
        $pdo = DB::connection()->getPdo();
        $driver = DB::connection()->getDriverName();
        $out = fopen($file, 'w');
        $counts = [];

        fwrite($out, "-- NIN Management backup\n-- Created: " . now()->toDateTimeString() . "\n\n");

        if ($driver === 'mysql' || $driver === 'mariadb') {
            fwrite($out, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
            $tables = array_map(fn ($r) => array_values((array) $r)[0], DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"'));
            $create = fn ($t) => array_values((array) DB::selectOne("SHOW CREATE TABLE `{$t}`"))[1];
            $quote = fn ($t) => "`{$t}`";
        } elseif ($driver === 'sqlite') {
            $tables = array_column(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"), 'name');
            $create = fn ($t) => DB::selectOne('SELECT sql FROM sqlite_master WHERE name = ?', [$t])->sql;
            $quote = fn ($t) => "\"{$t}\"";
        } else {
            throw new RuntimeException("Backups are not supported for the {$driver} driver.");
        }

        foreach ($tables as $table) {
            fwrite($out, "-- Table {$table}\nDROP TABLE IF EXISTS {$quote($table)};\n" . $create($table) . ";\n\n");

            $counts[$table] = 0;
            $stmt = $pdo->query('SELECT * FROM ' . $quote($table));
            $batch = [];

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $batch[] = '(' . implode(', ', array_map(fn ($v) => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? $v : $pdo->quote((string) $v)), $row)) . ')';
                $counts[$table]++;

                if (count($batch) === 200) {
                    fwrite($out, 'INSERT INTO ' . $quote($table) . ' VALUES ' . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                fwrite($out, 'INSERT INTO ' . $quote($table) . ' VALUES ' . implode(",\n", $batch) . ";\n");
            }
            fwrite($out, "\n");
        }

        if ($driver !== 'sqlite') {
            fwrite($out, "SET FOREIGN_KEY_CHECKS = 1;\n");
        }
        fclose($out);

        return $counts;
    }
}
