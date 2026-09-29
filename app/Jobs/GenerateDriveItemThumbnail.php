<?php

namespace App\Jobs;

use App\Actions\Drive\GenerateThumbnail;
use App\Models\DriveItem;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateDriveItemThumbnail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * Delete the job when the file was deleted before the job ran.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(public DriveItem $driveItem) {}

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->driveItem->uuid;
    }

    /**
     * Execute the job.
     */
    public function handle(GenerateThumbnail $generateThumbnail): void
    {
        $generateThumbnail->handle($this->driveItem);
    }
}
