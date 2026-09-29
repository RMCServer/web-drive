<?php

namespace App\Actions\Drive\Thumbnails;

use App\Models\DriveItem;
use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * Renders thumbnails for raster images with GD.
 */
class ImageThumbnailRenderer implements ThumbnailRenderer
{
    /**
     * @var array<int, string>
     */
    protected const array SUPPORTED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/x-ms-bmp'];

    public function supports(DriveItem $file): bool
    {
        return extension_loaded('gd')
            && in_array(strtolower((string) $file->mime_type), self::SUPPORTED_MIME_TYPES, true)
            && ($file->size ?? 0) <= config('drive.thumbnails.max_source_size');
    }

    public function render(DriveItem $file): ?string
    {
        $contents = Storage::disk($file->disk)->get($file->storage_path);
        $dimensions = $contents === null ? false : @getimagesizefromstring($contents);

        if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1) {
            return null;
        }

        if ($dimensions[0] * $dimensions[1] > config('drive.thumbnails.max_source_pixels')) {
            return null;
        }

        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            return null;
        }

        $source = $this->applyExifOrientation($source, $contents, $dimensions[2]);
        $thumbnail = $this->resize($source, (int) config('drive.thumbnails.size'));

        ob_start();
        $written = imagewebp($thumbnail, null, (int) config('drive.thumbnails.quality'));
        $data = (string) ob_get_clean();

        return $written && $data !== '' ? $data : null;
    }

    /**
     * Scale the image down to fit within a square of the given size, keeping transparency.
     */
    protected function resize(GdImage $source, int $size): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $size / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        imagefill($thumbnail, 0, 0, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $thumbnail;
    }

    /**
     * Rotate photos the way their camera recorded them, so thumbnails are upright.
     */
    protected function applyExifOrientation(GdImage $image, string $contents, int $imageType): GdImage
    {
        if ($imageType !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        $stream = fopen('php://memory', 'r+b');
        fwrite($stream, $contents);
        rewind($stream);
        $exif = @exif_read_data($stream);
        fclose($stream);

        $rotated = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated === false ? $image : $rotated;
    }
}
