<?php

namespace App\Support;

/**
 * View data shared by every printed receipt and KOT (print pages, POS dialog, previews).
 */
class Printing
{
    /** Maximum width for embedded receipt images (pixels). Larger images are downscaled. */
    public const MAX_IMAGE_WIDTH_PX = 600;

    /**
     * @param  array<string, mixed>|null  $config  override (live preview)
     * @return array{config: array<string, mixed>, business: array<string, mixed>, headerImage: ?string}
     */
    public static function receipt(?array $config = null, ?string $headerImage = null): array
    {
        $business = settings()->business();

        if ($headerImage === null) {
            $path = settings('receipt.header_image') ?: settings('business.logo');
            // Embed as base64 so it renders without any HTTP round-trip — works on desktop.
            $headerImage = $path ? (self::dataUrl($path) ?? ImageStorage::url($path)) : null;
        }

        return [
            'config' => $config ?? settings()->receipt(),
            'business' => $business,
            'headerImage' => $headerImage,
        ];
    }

    /**
     * Read an image from the public storage disk and return it as a base64 data URL.
     * - Downscales images wider than MAX_IMAGE_WIDTH_PX using GD (if available).
     * - Falls back gracefully when fileinfo or GD is missing.
     * - Returns null (not an exception) when the file does not exist.
     */
    public static function dataUrl(string $relativePath): ?string
    {
        $full = storage_path('app/public/'.ltrim($relativePath, '/'));

        if (! file_exists($full)) {
            \Log::warning('[Printing] logo file not found, skipping image', ['path' => $full]);

            return null;
        }

        try {
            $mime = self::sniffMime($full);
            $data = file_get_contents($full);

            if ($data === false) {
                return null;
            }

            // Downscale wide images via GD so receipts stay light and fast.
            if (extension_loaded('gd') && in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                $downscaled = self::downscale($full, $mime);
                if ($downscaled !== null) {
                    [$data, $mime] = $downscaled;
                }
            }

            return 'data:'.$mime.';base64,'.base64_encode((string) $data);
        } catch (\Throwable $e) {
            \Log::warning('[Printing] dataUrl failed, skipping image', ['path' => $full, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Detect MIME type without requiring the fileinfo extension.
     */
    private static function sniffMime(string $fullPath): string
    {
        if (extension_loaded('fileinfo') && function_exists('mime_content_type')) {
            $detected = @mime_content_type($fullPath);
            if ($detected && $detected !== 'application/octet-stream') {
                return $detected;
            }
        }

        // Fallback: read the first few bytes (magic bytes).
        $handle = @fopen($fullPath, 'rb');
        if ($handle) {
            $header = fread($handle, 12);
            fclose($handle);

            if (str_starts_with($header, "\xFF\xD8\xFF")) {
                return 'image/jpeg';
            }
            if (str_starts_with($header, "\x89PNG\r\n\x1A\n")) {
                return 'image/png';
            }
            if (str_starts_with($header, 'GIF87a') || str_starts_with($header, 'GIF89a')) {
                return 'image/gif';
            }
            if (str_starts_with($header, 'RIFF') && substr($header, 8, 4) === 'WEBP') {
                return 'image/webp';
            }
        }

        return match (strtolower(pathinfo($fullPath, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    /**
     * Downscale an image to at most MAX_IMAGE_WIDTH_PX wide using GD.
     * For PNG images with transparency, flattens onto a white background.
     * Returns [$imageData, $mime] or null if no downscale is needed.
     *
     * @return array{string, string}|null
     */
    private static function downscale(string $fullPath, string $mime): ?array
    {
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($fullPath),
            'image/png' => @imagecreatefrompng($fullPath),
            'image/gif' => @imagecreatefromgif($fullPath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($fullPath) : false,
            default => false,
        };

        if (! $src) {
            return null;
        }

        $origW = imagesx($src);
        $origH = imagesy($src);

        $needsResize = $origW > self::MAX_IMAGE_WIDTH_PX;
        $hasPng = $mime === 'image/png';
        $needsFlatten = $hasPng;  // flatten PNG transparency to white

        if (! $needsResize && ! $needsFlatten) {
            imagedestroy($src);

            return null;
        }

        $newW = $needsResize ? self::MAX_IMAGE_WIDTH_PX : $origW;
        $newH = $needsResize ? (int) round($origH * ($newW / $origW)) : $origH;

        $dst = imagecreatetruecolor($newW, $newH);

        // White background (covers PNG transparency)
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
        imagedestroy($src);

        ob_start();
        imagejpeg($dst, null, 85);
        $data = ob_get_clean();
        imagedestroy($dst);

        return [$data, 'image/jpeg'];
    }

    /**
     * @param  array<string, mixed>|null  $config  override (live preview)
     * @return array{config: array<string, mixed>, business: array<string, mixed>}
     */
    public static function kot(?array $config = null): array
    {
        return [
            'config' => $config ?? settings()->kot(),
            'business' => settings()->business(),
        ];
    }
}
