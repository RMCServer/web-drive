<?php

namespace App\Http\Requests\Drive\Concerns;

use App\Enums\DriveItemType;
use App\Models\DriveItem;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait ResolvesDriveFolder
{
    /**
     * Rules for a field holding the UUID of an active folder owned by the user.
     *
     * An empty value means the root of the user's Drive.
     *
     * @return array<int, Exists|string>
     */
    protected function folderRules(): array
    {
        return [
            'nullable',
            'uuid',
            Rule::exists('drive_items', 'uuid')
                ->where('user_id', $this->user()?->id)
                ->where('type', DriveItemType::Folder->value)
                ->withoutTrashed(),
        ];
    }

    /**
     * Resolve the validated folder for the given field, or null for the root.
     */
    protected function validatedFolder(string $key): ?DriveItem
    {
        $uuid = $this->validated($key);

        if (! is_string($uuid)) {
            return null;
        }

        return DriveItem::ownedBy($this->user())->folders()->where('uuid', $uuid)->firstOrFail();
    }
}
