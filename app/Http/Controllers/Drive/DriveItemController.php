<?php

namespace App\Http\Controllers\Drive;

use App\Actions\Drive\MoveItem;
use App\Actions\Drive\RenameItem;
use App\Actions\Drive\TrashItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Drive\MoveDriveItemRequest;
use App\Http\Requests\Drive\RenameDriveItemRequest;
use App\Http\Resources\DriveItemResource;
use App\Models\DriveItem;
use Illuminate\Http\RedirectResponse;

class DriveItemController extends Controller
{
    /**
     * Rename a file or folder.
     */
    public function rename(RenameDriveItemRequest $request, DriveItem $driveItem, RenameItem $renameItem): RedirectResponse
    {
        $renameItem->handle($driveItem, $request->validated('name'));

        return back(fallback: route('drive.index'))->with('status', __('Renamed.'));
    }

    /**
     * Move a file or folder into another folder or to the root.
     */
    public function move(MoveDriveItemRequest $request, DriveItem $driveItem, MoveItem $moveItem): RedirectResponse|DriveItemResource
    {
        $moveItem->handle($driveItem, $request->destinationFolder());

        if ($request->wantsJson()) {
            return DriveItemResource::make($driveItem);
        }

        return back(fallback: route('drive.index'))
            ->with('status', __('":name" moved.', ['name' => $driveItem->name]));
    }

    /**
     * Move a file or folder to the trash.
     */
    public function destroy(DriveItem $driveItem, TrashItem $trashItem): RedirectResponse
    {
        $trashItem->handle($driveItem);

        return back(fallback: route('drive.index'))
            ->with('status', __('":name" moved to the trash.', ['name' => $driveItem->name]));
    }
}
