<?php

namespace App\Actions\Drive;

use App\Models\DriveItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RestoreItem
{
    /**
     * Restore the item together with the descendants that were trashed along with it.
     *
     * Descendants the user trashed separately stay in the trash. When the
     * original folder no longer exists or is itself in the trash, the item
     * is restored to the root of the Drive instead.
     */
    public function handle(DriveItem $item): DriveItem
    {
        if (! $item->trashed()) {
            return $item;
        }

        return DB::transaction(function () use ($item) {
            $parent = $item->parent_id ? DriveItem::withTrashed()->find($item->parent_id) : null;
            $parentId = $parent && ! $parent->trashed() ? $parent->id : null;

            if ($item->isFolder()) {
                $descendantIds = $item->descendants(fn (Builder $query) => $query
                    ->whereNotNull('deleted_at')
                    ->where('explicitly_trashed', false)
                )->modelKeys();

                foreach (array_chunk($descendantIds, 500) as $chunk) {
                    DriveItem::withTrashed()->whereKey($chunk)->update(['deleted_at' => null]);
                }
            }

            $item->forceFill([
                'parent_id' => $parentId,
                'name' => DriveItem::generateUniqueName($item->user_id, $parentId, $item->name, $item->isFile(), $item->id),
                'deleted_at' => null,
                'explicitly_trashed' => false,
            ])->save();

            return $item;
        });
    }
}
