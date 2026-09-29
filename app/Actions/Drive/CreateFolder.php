<?php

namespace App\Actions\Drive;

use App\Enums\DriveItemType;
use App\Models\DriveItem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CreateFolder
{
    /**
     * Create a folder in the given parent folder, or at the root when no parent is given.
     */
    public function handle(User $user, Team $team, string $name, ?DriveItem $parent = null): DriveItem
    {
        if ($parent && ! $parent->canContainItemsOf($team)) {
            throw ValidationException::withMessages(['parent' => __('The selected folder is invalid.')]);
        }

        return DriveItem::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'parent_id' => $parent?->id,
            'type' => DriveItemType::Folder,
            'name' => DriveItem::generateUniqueName($team->id, $parent?->id, trim($name), keepExtension: false),
        ]);
    }
}
