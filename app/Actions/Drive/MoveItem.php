<?php

namespace App\Actions\Drive;

use App\Models\DriveItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoveItem
{
    /**
     * Move the item into the destination folder, or to the root when no destination is given.
     */
    public function handle(DriveItem $item, ?DriveItem $destination = null): DriveItem
    {
        if ($destination && ! $destination->canContainItemsOf($item->team)) {
            throw ValidationException::withMessages(['destination' => __('The selected folder is invalid.')]);
        }

        if ($destination && $item->isFolder() && $destination->isWithin($item)) {
            throw ValidationException::withMessages([
                'destination' => __('A folder cannot be moved into itself or one of its subfolders.'),
            ]);
        }

        if ($item->parent_id === $destination?->id) {
            return $item;
        }

        return DB::transaction(function () use ($item, $destination) {
            $item->update([
                'parent_id' => $destination?->id,
                'name' => DriveItem::generateUniqueName($item->team_id, $destination?->id, $item->name, $item->isFile(), $item->id),
            ]);

            return $item;
        });
    }
}
