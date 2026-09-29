<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Drive Storage Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk new Drive uploads are written to. Each stored file
    | remembers its own disk, so switching this value (for example to an
    | S3-compatible disk) only affects uploads made after the change.
    |
    */

    'disk' => env('DRIVE_DISK', 'drive'),

    /*
    |--------------------------------------------------------------------------
    | Maximum Upload Size
    |--------------------------------------------------------------------------
    |
    | The maximum size of a single uploaded file in kilobytes. Remember that
    | PHP's "upload_max_filesize" and "post_max_size" settings (and any web
    | server body limits) must be at least as large as this value.
    |
    */

    'max_upload_size' => (int) env('DRIVE_MAX_UPLOAD_SIZE', 102400),

    /*
    |--------------------------------------------------------------------------
    | Previews
    |--------------------------------------------------------------------------
    |
    | Text previews only read the start of a file, so opening a large log
    | file never loads it completely. Disks that support temporary URLs
    | (such as S3) hand previews to the storage service for this long.
    |
    */

    'preview' => [
        'max_text_bytes' => (int) env('DRIVE_PREVIEW_MAX_TEXT_BYTES', 512 * 1024),
        'temporary_url_minutes' => (int) env('DRIVE_PREVIEW_TEMPORARY_URL_MINUTES', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Thumbnails
    |--------------------------------------------------------------------------
    |
    | Thumbnails are generated on the queue after an upload and are stored
    | next to the original, which is never modified. Each renderer decides
    | which files it supports; add renderers to support more file types.
    | Sources above the size or pixel limits are skipped to protect memory.
    |
    */

    'thumbnails' => [
        'size' => (int) env('DRIVE_THUMBNAIL_SIZE', 480),
        'quality' => (int) env('DRIVE_THUMBNAIL_QUALITY', 80),
        'max_source_size' => (int) env('DRIVE_THUMBNAIL_MAX_SOURCE_SIZE', 25 * 1024 * 1024),
        'max_source_pixels' => (int) env('DRIVE_THUMBNAIL_MAX_SOURCE_PIXELS', 40_000_000),
        'renderers' => [
            App\Actions\Drive\Thumbnails\ImageThumbnailRenderer::class,
        ],
    ],

];
