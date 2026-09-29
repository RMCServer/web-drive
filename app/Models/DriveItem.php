<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueDriveItemNames;
use App\Enums\DriveFileKind;
use App\Enums\DriveItemType;
use App\Enums\DrivePreviewType;
use Closure;
use Database\Factories\DriveItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A file or folder in a team's Drive.
 *
 * Trashing a folder soft-deletes its whole subtree. Only the item the user
 * trashed is flagged as "explicitly trashed"; its descendants are trashed
 * implicitly and are restored together with it.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int|null $team_id
 * @property int|null $parent_id
 * @property DriveItemType $type
 * @property string $name
 * @property string|null $mime_type
 * @property string|null $extension
 * @property int|null $size
 * @property string|null $disk
 * @property string|null $storage_path
 * @property string|null $thumbnail_path
 * @property bool $explicitly_trashed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $owner
 * @property-read Team|null $team
 * @property-read DriveItem|null $parent
 * @property-read Collection<int, DriveItem> $children
 */
#[Fillable(['user_id', 'team_id', 'parent_id', 'type', 'name', 'mime_type', 'extension', 'size', 'disk', 'storage_path'])]
class DriveItem extends Model
{
    /** @use HasFactory<DriveItemFactory> */
    use GeneratesUniqueDriveItemNames, HasFactory, HasUuids, SoftDeletes;

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Get the user that owns the item.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the team containing this item.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the folder containing the item.
     *
     * @return BelongsTo<DriveItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(DriveItem::class, 'parent_id');
    }

    /**
     * Get the items directly inside this folder.
     *
     * @return HasMany<DriveItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(DriveItem::class, 'parent_id');
    }

    public function isFile(): bool
    {
        return $this->type === DriveItemType::File;
    }

    public function isFolder(): bool
    {
        return $this->type === DriveItemType::Folder;
    }

    /**
     * Get the category used for the item's icon and type label.
     */
    public function kind(): DriveFileKind
    {
        return DriveFileKind::for($this);
    }

    /**
     * Get how the viewer can render the item.
     */
    public function previewType(): DrivePreviewType
    {
        return DrivePreviewType::for($this);
    }

    public function hasThumbnail(): bool
    {
        return $this->isFile() && $this->disk !== null && $this->thumbnail_path !== null;
    }

    /**
     * Determine whether new items from the given team may be placed inside this item.
     */
    public function canContainItemsOf(Team $team): bool
    {
        return $this->isFolder() && $this->team_id === $team->id && ! $this->trashed();
    }

    /**
     * Get the storage directory reserved for a file's stored objects.
     *
     * The path is derived from the UUID instead of the visible name, so
     * renaming or moving an item never touches the stored object.
     */
    public static function storageDirectoryFor(string $uuid): string
    {
        return "drive/{$uuid}";
    }

    /**
     * Scope the query to items belonging to the given team.
     *
     * @param  Builder<DriveItem>  $query
     */
    #[Scope]
    protected function forTeam(Builder $query, Team $team): void
    {
        $query->where('team_id', $team->id);
    }

    /**
     * Scope the query to items at the root of a Drive.
     *
     * @param  Builder<DriveItem>  $query
     */
    #[Scope]
    protected function root(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * @param  Builder<DriveItem>  $query
     */
    #[Scope]
    protected function folders(Builder $query): void
    {
        $query->where('type', DriveItemType::Folder);
    }

    /**
     * @param  Builder<DriveItem>  $query
     */
    #[Scope]
    protected function files(Builder $query): void
    {
        $query->where('type', DriveItemType::File);
    }

    /**
     * Scope the query to the items the user sent to the trash themselves.
     *
     * @param  Builder<DriveItem>  $query
     */
    #[Scope]
    protected function trashRoots(Builder $query): void
    {
        $query->onlyTrashed()->where('explicitly_trashed', true);
    }

    /**
     * Order items the way a folder listing shows them: folders first, then by name.
     *
     * @param  Builder<DriveItem>  $query
     */
    #[Scope]
    protected function orderedForListing(Builder $query): void
    {
        $query->orderByRaw('case when type = ? then 0 else 1 end', [DriveItemType::Folder->value])
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * Get the folders above this item, starting at the root.
     *
     * Walks up one level per query, so only the item's own path is loaded.
     *
     * @return Collection<int, DriveItem>
     */
    public function ancestors(): Collection
    {
        $ancestors = new Collection;
        $visitedIds = [$this->id];
        $parentId = $this->parent_id;

        while ($parentId !== null && ! in_array($parentId, $visitedIds, true)) {
            $parent = static::withTrashed()->find($parentId);

            if ($parent === null) {
                break;
            }

            $ancestors->prepend($parent);
            $visitedIds[] = $parent->id;
            $parentId = $parent->parent_id;
        }

        return $ancestors;
    }

    /**
     * Determine whether this item is the given item or sits somewhere inside it.
     */
    public function isWithin(DriveItem $folder): bool
    {
        return $this->is($folder) || $this->ancestors()->contains($folder);
    }

    /**
     * Get every item below this folder, including trashed items.
     *
     * The tree is walked breadth first, one query per level. The optional
     * constraint limits which children are included and descended into.
     *
     * @param  (Closure(Builder<DriveItem>): mixed)|null  $constraint
     * @return Collection<int, DriveItem>
     */
    public function descendants(?Closure $constraint = null): Collection
    {
        $descendants = new Collection;
        $parentIds = [$this->id];

        while ($parentIds !== []) {
            $children = new Collection;

            foreach (array_chunk($parentIds, 500) as $chunk) {
                $children = $children->merge(
                    static::withTrashed()
                        ->whereIn('parent_id', $chunk)
                        ->when($constraint, $constraint)
                        ->get(),
                );
            }

            $descendants = $descendants->merge($children);
            $parentIds = $children->where('type', DriveItemType::Folder)->modelKeys();
        }

        return $descendants;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DriveItemType::class,
            'size' => 'integer',
            'explicitly_trashed' => 'boolean',
        ];
    }
}
