<?php

namespace App\Concerns;

use Illuminate\Support\Str;

trait GeneratesUniqueDriveItemNames
{
    /**
     * Generate a name that does not collide with the active siblings in the given folder.
     *
     * "Holiday Photo.png" becomes "Holiday Photo (1).png", "Projects" becomes "Projects (1)".
     */
    public static function generateUniqueName(int $teamId, ?int $parentId, string $name, bool $keepExtension, ?int $excludeId = null): string
    {
        [$base, $extension] = static::splitName($name, $keepExtension);

        $existingNames = static::query()
            ->where('team_id', $teamId)
            ->where('parent_id', $parentId)
            ->when($excludeId, fn ($query) => $query->whereKeyNot($excludeId))
            ->where(function ($query) use ($name, $base) {
                $query->where('name', $name)
                    ->orWhere('name', 'like', $base.' (%');
            })
            ->pluck('name');

        if (! $existingNames->contains($name)) {
            return $name;
        }

        $pattern = '/^'.preg_quote($base, '/').' \((\d+)\)'.preg_quote($extension, '/').'$/';

        $maxSuffix = $existingNames
            ->map(fn (string $existingName): int => preg_match($pattern, $existingName, $matches) ? (int) $matches[1] : 0)
            ->max();

        return $base.' ('.($maxSuffix + 1).')'.$extension;
    }

    /**
     * Split a name into its base and its extension (including the dot).
     *
     * @return array{0: string, 1: string}
     */
    protected static function splitName(string $name, bool $keepExtension): array
    {
        $extension = $keepExtension ? pathinfo($name, PATHINFO_EXTENSION) : '';

        if ($extension === '' || Str::beforeLast($name, '.') === '') {
            return [$name, ''];
        }

        return [Str::beforeLast($name, '.'), '.'.$extension];
    }
}
