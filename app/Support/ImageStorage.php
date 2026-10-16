<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores uploaded images on the public disk. Only the relative path is saved in the database.
 */
class ImageStorage
{
    public const DISK = 'public';

    /**
     * Store a new upload, deleting the previous file. Returns the path to save.
     * With no upload, returns the current path, or null when $remove is true.
     */
    public static function replace(?UploadedFile $file, ?string $currentPath, string $directory, bool $remove = false): ?string
    {
        if ($file) {
            self::delete($currentPath);

            // hashName() derives the extension from the detected MIME type, not the client filename.
            return $file->store($directory, self::DISK);
        }

        if ($remove) {
            self::delete($currentPath);

            return null;
        }

        return $currentPath;
    }

    public static function delete(?string $path): void
    {
        if ($path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public static function url(?string $path): ?string
    {
        return $path ? '/storage/'.ltrim($path, '/') : null;
    }
}
