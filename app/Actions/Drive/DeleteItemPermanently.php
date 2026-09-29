<?php

namespace App\Actions\Drive;

use App\Models\DriveItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class DeleteItemPermanently
{
    /**
     * Permanently delete a trashed item, everything inside it, and their stored objects.
     */
    public function handle(DriveItem $item): void
    {
        if (! $item->trashed()) {
            throw ValidationException::withMessages([
                'item' => __('Only items in the trash can be deleted permanently.'),
            ]);
        }

        $items = $item->isFolder()
            ? $item->descendants()->prepend($item)
            : new Collection([$item]);

        DB::transaction(function () use ($items) {
            foreach (array_chunk(array_reverse($items->modelKeys()), 500) as $chunk) {
                DriveItem::withTrashed()->whereKey($chunk)->forceDelete();
            }
        });

        $items
            ->filter(fn (DriveItem $item) => $item->isFile() && $item->disk && $item->storage_path)
            ->each(fn (DriveItem $file) => $this->deleteStoredObject($file));
    }

    /**
     * Delete a file's stored objects. Failures are reported rather than
     * thrown, because the database records are already gone.
     */
    protected function deleteStoredObject(DriveItem $file): void
    {
        try {
            $disk = Storage::disk($file->disk);

            $disk->delete($file->storage_path);
            $disk->deleteDirectory(DriveItem::storageDirectoryFor($file->uuid));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
