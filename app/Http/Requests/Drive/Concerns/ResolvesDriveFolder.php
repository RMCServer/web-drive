<?php

namespace App\Http\Requests\Drive\Concerns;

use App\Enums\DriveItemType;
use App\Models\DriveItem;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait ResolvesDriveFolder
{
    /**
     * Rules for a field holding the UUID of an active folder in the current team.
     *
     * An empty value means the root of the current team's Drive.
     *
     * @return array<int, Exists|string>
     */
    protected function folderRules(): array
    {
        return [
            'nullable',
            'uuid',
            Rule::exists('drive_items', 'uuid')
                ->where('team_id', $this->user()?->current_team_id)
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

        return DriveItem::query()
            ->where('team_id', $this->user()?->current_team_id)
            ->folders()
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
