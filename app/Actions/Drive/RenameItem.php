<?php

namespace App\Actions\Drive;

use App\Models\DriveItem;
use Illuminate\Validation\ValidationException;

class RenameItem
{
    /**
     * Rename the item. Only metadata changes; the stored object stays where it is.
     */
    public function handle(DriveItem $item, string $name): DriveItem
    {
        $name = trim($name);

        if ($name === $item->name) {
            return $item;
        }

        $nameIsTaken = DriveItem::query()
            ->where('user_id', $item->user_id)
            ->where('parent_id', $item->parent_id)
            ->whereKeyNot($item->id)
            ->where('name', $name)
            ->exists();

        if ($nameIsTaken) {
            throw ValidationException::withMessages([
                'name' => __('An item named ":name" already exists in this folder.', ['name' => $name]),
            ]);
        }

        $item->update(['name' => $name]);

        return $item;
    }
}
