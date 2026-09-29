<?php

namespace App\Actions\Drive;

use App\Models\DriveItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TrashItem
{
    /**
     * Move the item and everything inside it to the trash.
     *
     * Stored objects are kept until the item is deleted permanently.
     */
    public function handle(DriveItem $item): void
    {
        DB::transaction(function () use ($item) {
            $trashedAt = now();

            if ($item->isFolder()) {
                $descendantIds = $item->descendants(fn (Builder $query) => $query->whereNull('deleted_at'))->modelKeys();

                foreach (array_chunk($descendantIds, 500) as $chunk) {
                    DriveItem::whereKey($chunk)->update([
                        'deleted_at' => $trashedAt,
                        'explicitly_trashed' => false,
                    ]);
                }
            }

            $item->forceFill([
                'deleted_at' => $trashedAt,
                'explicitly_trashed' => true,
            ])->save();
        });
    }
}
