<?php

namespace App\Rules;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

class ImageUpload
{
    /** Application cap in kilobytes. The effective limit may be lower if PHP's upload limit is. */
    public const MAX_KB = 5120;

    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tiff', 'tif', 'avif', 'ico'];

    /** Browsers shrink larger photos to this many pixels on the longest side before uploading. */
    public const RESIZE_TO_PX = 1600;

    /**
     * Effective maximum in kilobytes: the app cap or PHP's upload_max_filesize, whichever is lower.
     * Files over PHP's limit never reach Laravel, so validating above it would be misleading.
     */
    public static function maxKilobytes(): int
    {
        $phpLimitKb = (int) floor(UploadedFile::getMaxFilesize() / 1024);

        return $phpLimitKb > 0 ? min(self::MAX_KB, $phpLimitKb) : self::MAX_KB;
    }

    /**
     * Rules for an optional image upload. The type is checked against the file's
     * detected content (MIME sniffing), not only the client-supplied extension.
     *
     * @return list<mixed>
     */
    public static function rules(): array
    {
        return [
            'nullable',
            File::image()->max(self::maxKilobytes()),
        ];
    }

    /**
     * Friendlier messages, especially for files PHP rejected before validation ran.
     *
     * @return array<string, string>
     */
    public static function messages(string $field): array
    {
        $size = self::maxMegabytesLabel();

        return [
            "{$field}.uploaded" => __('The image could not be uploaded. It is probably larger than the :size MB limit. Try a smaller photo.', ['size' => $size]),
            "{$field}.max" => __('The image must not be larger than :size MB.', ['size' => $size]),
            "{$field}.mimetypes" => __('The image must be a JPG, PNG or WEBP file.'),
            "{$field}.image" => __('The file must be a JPG, PNG or WEBP image.'),
            "{$field}.mimes" => __('The image must be a JPG, PNG or WEBP file.'),
        ];
    }

    public static function hint(): string
    {
        return __('Any image format (JPG, PNG, WEBP, BMP, GIF…), up to :size MB.', ['size' => self::maxMegabytesLabel()]);
    }

    private static function maxMegabytesLabel(): string
    {
        return rtrim(rtrim(number_format(self::maxKilobytes() / 1024, 1), '0'), '.');
    }
}
