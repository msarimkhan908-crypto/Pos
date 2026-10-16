<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:settings.manage'),
        ];
    }

    public function index(): View
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        $backups = collect(File::files($dir))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.sqlite'))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->values()
            ->map(fn ($f) => [
                'name' => $f->getFilename(),
                'size' => $f->getSize(),
                'date' => Carbon::createFromTimestamp($f->getMTime()),
            ]);

        $lastBackupJson = storage_path('app/last_backup.json');
        $lastBackup = file_exists($lastBackupJson)
            ? (json_decode(file_get_contents($lastBackupJson), true) ?? [])
            : [];

        return view('backup.index', compact('backups', 'lastBackup'));
    }

    public function store(): RedirectResponse
    {
        Artisan::call('backup:create');

        return back()->with('success', __('Backup created successfully.'));
    }

    public function download(string $filename): BinaryFileResponse|Response
    {
        // Prevent path traversal.
        $filename = basename($filename);

        if (! str_ends_with($filename, '.sqlite')) {
            abort(404);
        }

        $path = storage_path('app/backups/'.$filename);
        abort_unless(File::exists($path), 404);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    public function destroy(string $filename): RedirectResponse
    {
        $filename = basename($filename);

        if (! str_ends_with($filename, '.sqlite')) {
            abort(404);
        }

        $path = storage_path('app/backups/'.$filename);

        if (File::exists($path)) {
            File::delete($path);
        }

        return back()->with('success', __('Backup deleted.'));
    }
}
