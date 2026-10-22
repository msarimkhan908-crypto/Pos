<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

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
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.zip'))
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
        $filename = basename($filename);

        if (! str_ends_with($filename, '.zip')) {
            abort(404);
        }

        $path = storage_path('app/backups/'.$filename);
        abort_unless(File::exists($path), 404);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/zip',
        ]);
    }

    public function restore(Request $request): RedirectResponse
    {
        $request->validate([
            'backup_file' => ['required', 'file', 'max:524288'],
        ]);

        $uploadedFile = $request->file('backup_file');

        if (strtolower($uploadedFile->getClientOriginalExtension()) !== 'zip') {
            return back()->withErrors(['backup_file' => __('Please upload a .zip backup file.')]);
        }

        $zip = new ZipArchive;
        if ($zip->open($uploadedFile->getPathname()) !== true) {
            return back()->withErrors(['backup_file' => __('The file could not be opened as a ZIP archive.')]);
        }

        if ($zip->locateName('database.sqlite') === false) {
            $zip->close();

            return back()->withErrors(['backup_file' => __('This is not a valid POS backup file (missing database).')]);
        }

        $dbPath = config('database.connections.sqlite.database');
        $uploadsDir = storage_path('app/public');

        // Disconnect before replacing the SQLite file so it is not locked.
        DB::disconnect('sqlite');

        $dbContent = $zip->getFromName('database.sqlite');

        if ($dbContent === false || file_put_contents($dbPath, $dbContent) === false) {
            $zip->close();

            return back()->withErrors(['backup_file' => __('Could not write the database file. Check storage permissions.')]);
        }

        File::ensureDirectoryExists($uploadsDir);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if (! str_starts_with($name, 'uploads/') || str_ends_with($name, '/')) {
                continue;
            }

            $relativePath = substr($name, strlen('uploads/'));
            $destPath = $uploadsDir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

            File::ensureDirectoryExists(dirname($destPath));
            file_put_contents($destPath, $zip->getFromIndex($i));
        }

        $zip->close();

        return redirect()->route('backup.index')
            ->with('success', __('Backup restored successfully. Your data has been replaced.'));
    }

    public function destroy(string $filename): RedirectResponse
    {
        $filename = basename($filename);

        if (! str_ends_with($filename, '.zip')) {
            abort(404);
        }

        $path = storage_path('app/backups/'.$filename);

        if (File::exists($path)) {
            File::delete($path);
        }

        return back()->with('success', __('Backup deleted.'));
    }
}
