<?php

namespace App\Http\Controllers\Drive;

use App\Actions\Drive\UploadFile;
use App\Enums\DrivePreviewType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Drive\StoreFileRequest;
use App\Http\Resources\DriveItemResource;
use App\Models\DriveItem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    /**
     * Upload a file into the root or one of the user's folders.
     */
    public function store(StoreFileRequest $request, UploadFile $uploadFile): RedirectResponse|JsonResponse
    {
        $file = $uploadFile->handle($request->user(), $request->file('file'), $request->parentFolder());

        if ($request->wantsJson()) {
            return DriveItemResource::make($file)->response()->setStatusCode(201);
        }

        return back(fallback: route('drive.index'))
            ->with('status', __('":name" uploaded.', ['name' => $file->name]));
    }

    /**
     * Stream the stored file to the user under its visible name.
     */
    public function download(DriveItem $driveItem): StreamedResponse
    {
        abort_unless($driveItem->isFile() && $driveItem->disk && $driveItem->storage_path, 404);

        $disk = Storage::disk($driveItem->disk);

        abort_unless($disk->exists($driveItem->storage_path), 404);

        return $disk->download($driveItem->storage_path, $driveItem->name, [
            'Content-Type' => $driveItem->mime_type ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Serve a previewable file inline for the Drive viewer.
     *
     * Only allow-listed types are served inline, text is always served as
     * plain text, and a sandboxing CSP keeps active content such as SVG
     * scripts from running on the application's origin.
     */
    public function preview(DriveItem $driveItem): Response|BinaryFileResponse|StreamedResponse|RedirectResponse
    {
        $previewType = $driveItem->previewType();

        abort_unless($previewType->isPreviewable() && $driveItem->disk && $driveItem->storage_path, 404);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($driveItem->disk);

        abort_unless($disk->exists($driveItem->storage_path), 404);

        if ($previewType === DrivePreviewType::Text) {
            return $this->textPreview($disk, $driveItem);
        }

        $headers = $this->previewHeaders($previewType, $driveItem->mime_type ?? 'application/octet-stream');
        $disposition = $this->inlineDisposition($driveItem->name);

        // Local files support range requests, which lets video and audio seek.
        if ($disk->getAdapter() instanceof LocalFilesystemAdapter) {
            return response()->file($disk->path($driveItem->storage_path), $headers + ['Content-Disposition' => $disposition]);
        }

        // Object storage serves the bytes itself through a short-lived signed URL.
        if ($disk->providesTemporaryUrls()) {
            return redirect()->away($disk->temporaryUrl(
                $driveItem->storage_path,
                now()->addMinutes(config('drive.preview.temporary_url_minutes')),
                ['ResponseContentType' => $headers['Content-Type'], 'ResponseContentDisposition' => $disposition],
            ));
        }

        return $disk->response($driveItem->storage_path, $driveItem->name, $headers, 'inline');
    }

    /**
     * Serve the generated thumbnail of a file.
     */
    public function thumbnail(DriveItem $driveItem): StreamedResponse
    {
        abort_unless($driveItem->hasThumbnail(), 404);

        $disk = Storage::disk($driveItem->disk);

        abort_unless($disk->exists($driveItem->thumbnail_path), 404);

        return $disk->response($driveItem->thumbnail_path, 'thumbnail.webp', [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    /**
     * Serve the start of a text file as plain UTF-8 text.
     */
    protected function textPreview(FilesystemAdapter $disk, DriveItem $driveItem): Response
    {
        $maxBytes = (int) config('drive.preview.max_text_bytes');
        $stream = $disk->readStream($driveItem->storage_path);
        $contents = (string) stream_get_contents($stream, $maxBytes + 1);
        fclose($stream);

        $isTruncated = strlen($contents) > $maxBytes;
        $contents = mb_scrub(substr($contents, 0, $maxBytes), 'UTF-8');

        return response($contents, 200, $this->previewHeaders(DrivePreviewType::Text, 'text/plain; charset=UTF-8') + [
            'X-Drive-Preview-Truncated' => $isTruncated ? '1' : '0',
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function previewHeaders(DrivePreviewType $previewType, string $contentType): array
    {
        $headers = [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
        ];

        // Browsers refuse to render PDFs in a sandboxed document, and their PDF viewers do not run page scripts.
        if ($previewType !== DrivePreviewType::Pdf) {
            $headers['Content-Security-Policy'] = "default-src 'none'; img-src 'self' data:; media-src 'self'; style-src 'unsafe-inline'; sandbox";
        }

        return $headers;
    }

    protected function inlineDisposition(string $name): string
    {
        $fallback = (string) preg_replace('/[^\x20-\x7E]|[\/\\\\%"]/', '_', Str::ascii($name));

        return HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $name, $fallback !== '' ? $fallback : 'preview');
    }
}
