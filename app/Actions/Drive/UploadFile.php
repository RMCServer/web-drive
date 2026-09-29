<?php

namespace App\Actions\Drive;

use App\Enums\DriveItemType;
use App\Jobs\GenerateDriveItemThumbnail;
use App\Models\DriveItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class UploadFile
{
    public function __construct(protected GenerateThumbnail $generateThumbnail) {}

    /**
     * Store an uploaded file and record it in the given folder, or at the root.
     *
     * The file is streamed to a UUID-based path; the visible name only lives in the database.
     */
    public function handle(User $user, UploadedFile $file, ?DriveItem $parent = null): DriveItem
    {
        if ($parent && ! $parent->canContainItemsOf($user)) {
            throw ValidationException::withMessages(['parent' => __('The selected folder is invalid.')]);
        }

        $uuid = (string) Str::uuid7();
        $diskName = config('drive.disk');
        $disk = Storage::disk($diskName);
        $directory = DriveItem::storageDirectoryFor($uuid);
        $storagePath = "{$directory}/original";

        if ($disk->exists($storagePath)) {
            throw new RuntimeException("Refusing to overwrite the existing Drive object [{$storagePath}].");
        }

        $disk->putFileAs($directory, $file, 'original');

        try {
            $item = DB::transaction(function () use ($user, $file, $parent, $uuid, $diskName, $storagePath) {
                $item = new DriveItem([
                    'user_id' => $user->id,
                    'parent_id' => $parent?->id,
                    'type' => DriveItemType::File,
                    'name' => DriveItem::generateUniqueName($user->id, $parent?->id, $this->visibleName($file), keepExtension: true),
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'extension' => Str::lower($file->getClientOriginalExtension()) ?: null,
                    'size' => $file->getSize(),
                    'disk' => $diskName,
                    'storage_path' => $storagePath,
                ]);

                $item->uuid = $uuid;
                $item->save();

                return $item;
            });
        } catch (Throwable $exception) {
            $disk->deleteDirectory($directory);

            throw $exception;
        }

        $this->queueThumbnail($item);

        return $item;
    }

    /**
     * Queue thumbnail generation. A failing queue must never fail the upload itself.
     */
    protected function queueThumbnail(DriveItem $item): void
    {
        if (! $this->generateThumbnail->supports($item)) {
            return;
        }

        try {
            GenerateDriveItemThumbnail::dispatch($item);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Turn the client-provided filename into a safe visible name.
     */
    protected function visibleName(UploadedFile $file): string
    {
        $name = (string) preg_replace('/[\/\\\\\x00-\x1F\x7F]/', '', basename($file->getClientOriginalName()));
        $name = trim($name);

        if ($name === '' || $name === '.' || $name === '..') {
            return __('Untitled');
        }

        if (mb_strlen($name) <= 255) {
            return $name;
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $suffix = $extension !== '' && mb_strlen($extension) < 32 ? '.'.$extension : '';

        return mb_substr($name, 0, 255 - mb_strlen($suffix)).$suffix;
    }
}
