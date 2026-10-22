<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class BackupCreate extends Command
{
    protected $signature = 'backup:create';

    protected $description = 'Create a ZIP backup of the database and uploaded files';

    public function handle(): int
    {
        $source = config('database.connections.sqlite.database');

        if (! file_exists($source)) {
            $this->error("Database file not found: {$source}");

            return self::FAILURE;
        }

        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        $filename = 'pos-backup-'.now()->format('Y-m-d_His').'.zip';
        $dest = $dir.DIRECTORY_SEPARATOR.$filename;

        $zip = new ZipArchive;
        if ($zip->open($dest, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error('Failed to create backup ZIP.');

            return self::FAILURE;
        }

        $zip->addFile($source, 'database.sqlite');

        $uploadsDir = storage_path('app/public');
        if (is_dir($uploadsDir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($files as $file) {
                $relPath = 'uploads/'.str_replace('\\', '/', substr($file->getPathname(), strlen($uploadsDir) + 1));
                $zip->addFile($file->getPathname(), $relPath);
            }
        }

        $zip->close();

        // Keep only the 30 most recent backups.
        collect(File::files($dir))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.zip'))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->values()
            ->slice(30)
            ->each(fn ($f) => File::delete($f->getPathname()));

        file_put_contents(
            storage_path('app/last_backup.json'),
            json_encode(['backed_up_at' => now()->toIso8601String(), 'file' => $filename])
        );

        $this->info("Backup created: {$filename}");

        return self::SUCCESS;
    }
}
