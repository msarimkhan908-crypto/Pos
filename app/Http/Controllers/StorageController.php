<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Serves files from the public storage disk without requiring a symlink.
 * Used on desktop (Windows) where `storage:link` fails for non-admin users.
 * On a web install the normal public/storage symlink takes precedence; this
 * route is only reached when the symlink does not exist.
 */
class StorageController extends Controller
{
    public function serve(Request $request, string $path): Response
    {
        // Block path traversal: reject anything containing ..
        if (str_contains($path, '..') || str_contains($path, "\0")) {
            abort(404);
        }

        // Only serve files that live directly under the public disk root.
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            abort(404);
        }

        $content = $disk->get($path);
        $mime = $this->mime($disk->path($path));

        $etag = '"'.md5($content).'"';
        $lastMod = gmdate('D, d M Y H:i:s', $disk->lastModified($path)).' GMT';

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304);
        }

        return response($content, 200, [
            'Content-Type' => $mime,
            'Content-Length' => strlen($content),
            'ETag' => $etag,
            'Last-Modified' => $lastMod,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    private function mime(string $fullPath): string
    {
        if (extension_loaded('fileinfo') && function_exists('mime_content_type')) {
            $detected = @mime_content_type($fullPath);
            if ($detected && $detected !== 'application/octet-stream') {
                return $detected;
            }
        }

        return match (strtolower(pathinfo($fullPath, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'pdf' => 'application/pdf',
            'css' => 'text/css',
            'js' => 'application/javascript',
            default => 'application/octet-stream',
        };
    }
}
