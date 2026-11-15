<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\BackupManager;
use RuntimeException;

class BackupController extends Controller
{
    public function __construct(private BackupManager $backups)
    {
    }

    public function index()
    {
        return view('backups.index', [
            'backups' => $this->backups->list(),
            'keep' => (int) config('services.nin.backup_keep', 14),
        ]);
    }

    public function store()
    {
        try {
            $backup = $this->backups->create(auth()->user()->email);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Backup failed: ' . $e->getMessage());
        }

        ActivityLog::record('backup.created', "Created backup {$backup['name']}");

        return back()->with('success', "Backup {$backup['name']} created.");
    }

    public function download(string $name)
    {
        try {
            $path = $this->backups->path($name);
        } catch (RuntimeException) {
            abort(404);
        }

        ActivityLog::record('backup.downloaded', "Downloaded backup {$name}");

        return response()->download($path, $name, ['Content-Type' => 'application/gzip']);
    }

    public function destroy(string $name)
    {
        try {
            $this->backups->delete($name);
        } catch (RuntimeException) {
            abort(404);
        }

        ActivityLog::record('backup.deleted', "Deleted backup {$name}");

        return back()->with('success', "Backup {$name} deleted.");
    }
}
