<?php

namespace App\Actions\Drive\Thumbnails;

use App\Models\DriveItem;

/**
 * Turns a stored file into a small preview image.
 */
interface ThumbnailRenderer
{
    /**
     * Determine whether the renderer can create a thumbnail for the file.
     */
    public function supports(DriveItem $file): bool;

    /**
     * Render the thumbnail as WebP image data, or return null when the file cannot be rendered.
     */
    public function render(DriveItem $file): ?string;
}
