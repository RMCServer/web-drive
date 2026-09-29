<?php

namespace App\Console\Commands;

use App\Actions\Drive\GenerateThumbnail;
use App\Jobs\GenerateDriveItemThumbnail;
use App\Models\DriveItem;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('drive:generate-thumbnails {--force : Regenerate thumbnails that already exist}')]
#[Description('Queue thumbnail generation for Drive files that do not have a thumbnail yet')]
class GenerateDriveThumbnails extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(GenerateThumbnail $generateThumbnail): int
    {
        $queued = 0;

        DriveItem::files()
            ->unless($this->option('force'), fn ($query) => $query->whereNull('thumbnail_path'))
            ->lazyById()
            ->filter(fn (DriveItem $file) => $generateThumbnail->supports($file))
            ->each(function (DriveItem $file) use (&$queued) {
                GenerateDriveItemThumbnail::dispatch($file);
                $queued++;
            });

        $this->components->info("Queued thumbnail generation for {$queued} file(s).");

        return self::SUCCESS;
    }
}
