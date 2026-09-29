<?php

namespace App\Http\Controllers\Drive;

use App\Actions\Drive\DeleteItemPermanently;
use App\Actions\Drive\RestoreItem;
use App\Http\Controllers\Controller;
use App\Models\DriveItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TrashController extends Controller
{
    /**
     * List the items the user moved to the trash.
     */
    public function index(Request $request): View
    {
        return view('drive.trash', [
            'items' => DriveItem::ownedBy($request->user())
                ->trashRoots()
                ->with(['parent' => fn ($query) => $query->withTrashed()])
                ->orderByDesc('deleted_at')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    /**
     * Restore an item, and whatever was trashed with it, from the trash.
     */
    public function restore(DriveItem $driveItem, RestoreItem $restoreItem): RedirectResponse
    {
        $hadParent = $driveItem->parent_id !== null;

        $restoredItem = $restoreItem->handle($driveItem);

        $status = $hadParent && $restoredItem->parent_id === null
            ? __('":name" restored to My Drive because its original folder is no longer available.', ['name' => $restoredItem->name])
            : __('":name" restored.', ['name' => $restoredItem->name]);

        return back(fallback: route('drive.trash.index'))->with('status', $status);
    }

    /**
     * Permanently delete a trashed item and its stored files.
     */
    public function destroy(DriveItem $driveItem, DeleteItemPermanently $deleteItemPermanently): RedirectResponse
    {
        $deleteItemPermanently->handle($driveItem);

        return back(fallback: route('drive.trash.index'))
            ->with('status', __('":name" deleted permanently.', ['name' => $driveItem->name]));
    }
}
