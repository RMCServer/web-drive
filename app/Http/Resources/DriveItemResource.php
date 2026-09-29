<?php

namespace App\Http\Resources;

use App\Models\DriveItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Number;

/**
 * The listing metadata the Drive browser needs for an item.
 *
 * Only metadata is exposed; file contents are fetched through the
 * authorized preview, thumbnail and download routes when needed.
 *
 * @mixin DriveItem
 */
class DriveItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $kind = $this->kind();
        $previewType = $this->previewType();

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'type' => $this->type->value,
            'isFolder' => $this->isFolder(),
            'kind' => $kind->value,
            'kindLabel' => $kind->label(),
            'mimeType' => $this->mime_type,
            'extension' => $this->extension,
            'size' => $this->size,
            'sizeLabel' => $this->isFile() ? Number::fileSize($this->size ?? 0, precision: 1) : null,
            'previewType' => $previewType->value,
            'modifiedAt' => $this->updated_at?->toIso8601String(),
            'modifiedLabel' => $this->updated_at?->diffForHumans(),
            'urls' => [
                'open' => $this->isFolder() ? route('drive.folders.show', $this->resource) : null,
                'preview' => $previewType->isPreviewable() ? route('drive.items.preview', $this->resource) : null,
                'thumbnail' => $this->hasThumbnail()
                    ? route('drive.items.thumbnail', ['driveItem' => $this->resource, 'v' => $this->updated_at?->timestamp])
                    : null,
                'download' => $this->isFile() ? route('drive.items.download', $this->resource) : null,
                'rename' => route('drive.items.rename', $this->resource),
                'move' => route('drive.items.move', $this->resource),
                'destroy' => route('drive.items.destroy', $this->resource),
            ],
        ];
    }
}
