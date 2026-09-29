<?php

namespace App\Actions\Drive;

use App\Actions\Drive\Thumbnails\ThumbnailRenderer;
use App\Models\DriveItem;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateThumbnail
{
    /**
     * Determine whether a thumbnail can be generated for the item.
     */
    public function supports(DriveItem $item): bool
    {
        return $this->rendererFor($item) !== null;
    }

    /**
     * Generate and store a thumbnail next to the file's original.
     *
     * The original is never modified. Failures are reported but never
     * thrown, because a missing thumbnail only means a generic icon.
     */
    public function handle(DriveItem $item): bool
    {
        $renderer = $this->rendererFor($item);

        if ($renderer === null || $item->trashed()) {
            return false;
        }

        try {
            $thumbnail = $renderer->render($item);

            if ($thumbnail === null) {
                return false;
            }

            $path = DriveItem::storageDirectoryFor($item->uuid).'/thumbnail.webp';

            Storage::disk($item->disk)->put($path, $thumbnail);

            $item->forceFill(['thumbnail_path' => $path])->save();

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    protected function rendererFor(DriveItem $item): ?ThumbnailRenderer
    {
        if (! $item->isFile() || ! $item->disk || ! $item->storage_path) {
            return null;
        }

        foreach (config('drive.thumbnails.renderers', []) as $rendererClass) {
            $renderer = app($rendererClass);

            if ($renderer instanceof ThumbnailRenderer && $renderer->supports($item)) {
                return $renderer;
            }
        }

        return null;
    }
}
