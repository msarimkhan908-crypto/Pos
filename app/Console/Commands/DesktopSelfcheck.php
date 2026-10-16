<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Run a series of self-checks and emit the results as JSON.
 * Used by the Electron startup checker and by the Diagnostics settings page.
 */
class DesktopSelfcheck extends Command
{
    protected $signature = 'desktop:selfcheck';

    protected $description = 'Run desktop self-checks (storage, extensions, upload round-trip) and emit JSON.';

    public function handle(): int
    {
        $checks = [];

        // 1. Storage dirs writable
        $storageDirs = [
            'app/public',
            'framework/cache/data',
            'framework/sessions',
            'framework/views',
            'logs',
        ];

        foreach ($storageDirs as $sub) {
            $dir = storage_path($sub);
            $ok = is_dir($dir) && is_writable($dir);
            $checks[] = ['name' => "storage_{$sub}", 'ok' => $ok, 'detail' => $dir];
        }

        // 2. SQLite file writable
        $dbPath = config('database.connections.sqlite.database');
        $checks[] = [
            'name' => 'sqlite_writable',
            'ok' => is_file($dbPath) && is_writable($dbPath),
            'detail' => $dbPath,
        ];

        // 3. Migrations ran (check for a known table)
        try {
            \DB::select('SELECT 1 FROM settings LIMIT 1');
            $checks[] = ['name' => 'migrations_ran', 'ok' => true];
        } catch (\Throwable $e) {
            $checks[] = ['name' => 'migrations_ran', 'ok' => false, 'detail' => $e->getMessage()];
        }

        // 4. Required PHP extensions
        foreach (['fileinfo', 'gd', 'mbstring', 'pdo_sqlite', 'openssl', 'bcmath', 'ctype'] as $ext) {
            $checks[] = ['name' => "ext_{$ext}", 'ok' => extension_loaded($ext)];
        }

        // 5. Upload limits
        $checks[] = [
            'name' => 'file_uploads_enabled',
            'ok' => filter_var(ini_get('file_uploads'), FILTER_VALIDATE_BOOLEAN),
            'detail' => 'file_uploads='.ini_get('file_uploads'),
        ];
        $checks[] = [
            'name' => 'upload_max_filesize',
            'ok' => $this->parseBytes(ini_get('upload_max_filesize')) >= 2 * 1024 * 1024,
            'detail' => ini_get('upload_max_filesize'),
        ];

        // 6. Upload round-trip: store a tiny PNG, verify it can be read back
        $testFile = 'selfcheck-test-'.uniqid().'.png';
        try {
            $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
            Storage::disk('public')->put($testFile, $png);
            $read = Storage::disk('public')->get($testFile);
            Storage::disk('public')->delete($testFile);
            $checks[] = ['name' => 'upload_roundtrip', 'ok' => $read === $png];
        } catch (\Throwable $e) {
            try {
                Storage::disk('public')->delete($testFile);
            } catch (\Throwable) {
            }
            $checks[] = ['name' => 'upload_roundtrip', 'ok' => false, 'detail' => $e->getMessage()];
        }

        $result = [
            'timestamp' => now()->toISOString(),
            'checks' => $checks,
            'critical_failures' => array_values(
                array_map(fn ($c) => $c['name'], array_filter($checks, fn ($c) => $c['ok'] === false))
            ),
        ];

        $this->line(json_encode($result));

        return self::SUCCESS;
    }

    private function parseBytes(string $val): int
    {
        $val = trim($val);
        $last = strtolower(substr($val, -1));
        $num = (int) $val;

        return match ($last) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => $num,
        };
    }
}
