<?php

namespace App\Http\Controllers\Drive;

use App\Actions\Drive\CreateFolder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Drive\StoreFolderRequest;
use App\Http\Resources\DriveItemResource;
use App\Models\DriveItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class FolderController extends Controller
{
    /**
     * Show the root of the user's Drive.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $items = DriveItem::ownedBy($request->user())->root()->orderedForListing()->get();

        return $this->listing($request, null, collect(), $items);
    }

    /**
     * Show a folder with its direct children and the path leading to it.
     */
    public function show(Request $request, DriveItem $folder): Response|JsonResponse
    {
        abort_unless($folder->isFolder(), 404);

        return $this->listing($request, $folder, $folder->ancestors(), $folder->children()->orderedForListing()->get());
    }

    /**
     * Create a folder in the root or in one of the user's folders.
     */
    public function store(StoreFolderRequest $request, CreateFolder $createFolder): RedirectResponse
    {
        $folder = $createFolder->handle($request->user(), $request->validated('name'), $request->parentFolder());

        return back(fallback: route('drive.index'))
            ->with('status', __('Folder ":name" created.', ['name' => $folder->name]));
    }

    /**
     * Render a folder listing, or return its items as JSON so the browser can refresh without a page load.
     *
     * Both representations share a URL, so they vary on the Accept header
     * to keep the browser from showing cached JSON on back navigation.
     *
     * @param  Collection<int, DriveItem>  $breadcrumbs
     * @param  EloquentCollection<int, DriveItem>  $items
     */
    protected function listing(Request $request, ?DriveItem $folder, Collection $breadcrumbs, EloquentCollection $items): Response|JsonResponse
    {
        if ($request->wantsJson()) {
            return DriveItemResource::collection($items)->response()->header('Vary', 'Accept');
        }

        return response()->view('drive.index', [
            'folder' => $folder,
            'breadcrumbs' => $breadcrumbs,
            'items' => $items,
            'itemsPayload' => DriveItemResource::collection($items)->resolve($request),
            'moveTargets' => $this->moveTargets($request->user()),
        ])->header('Vary', 'Accept');
    }

    /**
     * Get the user's folders labelled with their full path, for the move dialog.
     *
     * @return array<string, string>
     */
    protected function moveTargets(User $user): array
    {
        $folders = DriveItem::ownedBy($user)->folders()->get(['id', 'uuid', 'parent_id', 'name'])->keyBy('id');

        $pathOf = function (DriveItem $folder) use ($folders): string {
            $segments = [$folder->name];
            $visited = [$folder->id];

            while ($folder->parent_id && ($folder = $folders->get($folder->parent_id)) && ! in_array($folder->id, $visited, true)) {
                array_unshift($segments, $folder->name);
                $visited[] = $folder->id;
            }

            return implode(' / ', $segments);
        };

        $targets = $folders->mapWithKeys(fn (DriveItem $folder) => [$folder->uuid => $pathOf($folder)])->all();

        natcasesort($targets);

        return $targets;
    }
}
