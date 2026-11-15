<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Support\BackupManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        // Folda ya muda ili backup halisi za mfumo zisiguswe.
        $this->dir = sys_get_temp_dir() . '/nin-backup-test-' . uniqid();
        $this->app->useStoragePath($this->dir);
        File::ensureDirectoryExists($this->dir . '/app/public');
        config(['filesystems.disks.public.root' => $this->dir . '/app/public']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_backup_contains_database_and_files(): void
    {
        Person::factory()->count(3)->create();
        File::ensureDirectoryExists($this->dir . '/app/public/signatures');
        file_put_contents($this->dir . '/app/public/signatures/a.png', 'png-bytes');

        $backup = app(BackupManager::class)->create('test');

        $this->assertMatchesRegularExpression('/^nin-backup-\d{8}-\d{6}\.tar\.gz$/', $backup['name']);

        // .tar ndani ya gzip: maudhui ya kila faili yamehifadhiwa kama yalivyo.
        $tar = gzdecode(file_get_contents($this->dir . '/app/backups/' . $backup['name']));
        $this->assertNotFalse($tar);

        $this->assertStringContainsString('database.sql', $tar);
        $this->assertStringContainsString('INSERT INTO "people"', $tar);
        $this->assertSame(3, substr_count($tar, 'INSERT INTO "people"') ? $this->peopleRows($tar) : 0);
        $this->assertStringContainsString('files/signatures/a.png', $tar);
        $this->assertStringContainsString('png-bytes', $tar);
        $this->assertMatchesRegularExpression('/"people": 3/', $tar);
        $this->assertMatchesRegularExpression('/"files": 1/', $tar);
    }

    /** Idadi ya safu za 'people' kwenye INSERT za SQL. */
    private function peopleRows(string $tar): int
    {
        preg_match_all('/INSERT INTO "people" VALUES (.*?);\n/s', $tar, $m);

        return array_sum(array_map(fn ($v) => substr_count($v, "),\n(") + 1, $m[1]));
    }

    public function test_backups_page_create_download_and_path_safety(): void
    {
        $admin = $this->user();

        $this->actingAs($admin)->post('/backups')->assertRedirect();
        $name = app(BackupManager::class)->list()->first()['name'];

        $this->actingAs($admin)->get('/backups')->assertOk()->assertSee($name);
        $this->actingAs($admin)->get("/backups/{$name}")->assertOk()->assertDownload($name);
        $this->actingAs($admin)->get('/backups/..%2F.env')->assertNotFound();

        $this->actingAs($admin)->delete("/backups/{$name}")->assertRedirect();
        $this->assertCount(0, app(BackupManager::class)->list());
    }

    public function test_old_backups_are_pruned(): void
    {
        config(['services.nin.backup_keep' => 2]);
        $dir = $this->dir . '/app/backups';
        File::ensureDirectoryExists($dir);

        foreach (['20260101-000000', '20260102-000000', '20260103-000000'] as $i => $stamp) {
            touch($dir . "/nin-backup-{$stamp}.tar.gz", strtotime('2026-01-0' . ($i + 1)));
        }

        app(BackupManager::class)->create();

        $this->assertCount(2, app(BackupManager::class)->list());
        $this->assertFileDoesNotExist($dir . '/nin-backup-20260101-000000.tar.gz');
    }
}
