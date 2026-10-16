<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BackupCreate extends Command
{
    protected $signature = 'backup:create';

    protected $description = 'Copy the SQLite database to the local backup folder';

    public function handle(): int
    {
        $source = config('database.connections.sqlite.database');

        if (! file_exists($source)) {
            $this->error("Database file not found: {$source}");

            return self::FAILURE;
        }

        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        $filename = 'pos-backup-'.now()->format('Y-m-d_His').'.sqlite';
        $dest = $dir.DIRECTORY_SEPARATOR.$filename;

        if (! copy($source, $dest)) {
            $this->error('Failed to copy database file.');

            return self::FAILURE;
        }

        // Keep only the 30 most recent backups.
        $files = collect(File::files($dir))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->values();

        $files->slice(30)->each(fn ($f) => File::delete($f->getPathname()));

        // Record the last backup time.
        file_put_contents(
            storage_path('app/last_backup.json'),
            json_encode(['backed_up_at' => now()->toIso8601String(), 'file' => $filename])
        );

        $this->info("Backup created: {$filename}");

        return self::SUCCESS;
    }
}
