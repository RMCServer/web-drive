@php
    $browserConfig = [
        'items' => $itemsPayload,
        'folderUuid' => $folder?->uuid,
        'csrfToken' => csrf_token(),
        'maxUploadBytes' => config('drive.max_upload_size') * 1024,
        'urls' => [
            'listing' => $folder ? route('drive.folders.show', $folder) : route('drive.index'),
            'upload' => route('drive.files.store'),
        ],
        'messages' => [
            'selected' => __(':count selected'),
            'uploading' => __('Uploading :current of :total'),
            'uploadsComplete' => __(':count upload(s) complete'),
            'uploadsFailed' => __(':done uploaded, :failed failed'),
            'moved' => __('":name" moved.'),
            'movedMany' => __(':count items moved.'),
            'moveFailed' => __('":name" could not be moved.'),
            'uploaded' => __(':count file(s) uploaded.'),
            'uploadFailed' => __('The upload failed.'),
            'tooLarge' => __('This file is larger than the :size upload limit.', ['size' => Number::fileSize(config('drive.max_upload_size') * 1024)]),
            'sessionExpired' => __('Your session expired. Refresh the page and try again.'),
            'foldersNotSupported' => __('Folders cannot be uploaded yet. Drop the files inside them instead.'),
            'previewFailed' => __('The preview could not be loaded.'),
            'requestFailed' => __('Something went wrong. Please try again.'),
        ],
    ];

    // Shared by the grid cards, grid folder tiles and list rows.
    $itemAttributes = <<<'HTML'
        data-drive-item
        tabindex="0"
        draggable="true"
        x-bind:data-uuid="item.uuid"
        x-bind:aria-selected="isSelected(item)"
        x-on:click="select(item, $event)"
        x-on:dblclick="open(item)"
        x-on:keydown.enter.prevent="open(item)"
        x-on:keydown.space.prevent="select(item, $event)"
        x-on:contextmenu.prevent.stop="openMenu(item, $event)"
        x-on:dragstart="onItemDragStart(item, $event)"
        x-on:dragend="onItemDragEnd()"
        x-on:dragover="item.isFolder && onTargetDragOver(item.uuid, $event)"
        x-on:dragleave="item.isFolder && onTargetDragLeave(item.uuid, $event)"
        x-on:drop="item.isFolder && onTargetDrop(item.uuid, $event)"
    HTML;

    $itemStateClasses = "{
        'bg-sky-50 ring-2 ring-sky-500 dark:bg-sky-500/15': isSelected(item) && dropTarget !== item.uuid,
        'bg-emerald-50 ring-2 ring-emerald-500 dark:bg-emerald-500/15': dropTarget === item.uuid,
        'opacity-50': isBeingDragged(item),
    }";
@endphp

<x-layouts::app :title="$folder?->name ?? __('My Drive')">
    <div
        x-data="driveBrowser(@js($browserConfig))"
        x-on:keydown.window="onKeydown($event)"
        x-on:dragenter="onDragEnter($event)"
        x-on:dragover="onDragOver($event)"
        x-on:dragleave="onDragLeave($event)"
        x-on:drop="onDrop($event)"
        x-on:dragover.window="onWindowDragOver($event)"
        x-on:drop.window="onWindowDrop($event)"
        class="relative flex w-full flex-1 flex-col gap-4"
        data-test="drive-browser"
    >
        {{-- Header: breadcrumbs (also drop targets for moving items up) and the New menu --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item
                    :href="$folder ? route('drive.index') : null"
                    icon="home"
                    wire:navigate
                    x-on:dragover="onTargetDragOver(null, $event)"
                    x-on:dragleave="onTargetDragLeave(null, $event)"
                    x-on:drop="onTargetDrop(null, $event)"
                    x-bind:class="dropTarget === 'root' && 'rounded-md bg-emerald-50 ring-2 ring-emerald-500 dark:bg-emerald-500/15'"
                >{{ __('My Drive') }}</flux:breadcrumbs.item>
                @foreach ($breadcrumbs as $ancestor)
                    <flux:breadcrumbs.item
                        :href="route('drive.folders.show', $ancestor)"
                        wire:navigate
                        x-on:dragover="onTargetDragOver('{{ $ancestor->uuid }}', $event)"
                        x-on:dragleave="onTargetDragLeave('{{ $ancestor->uuid }}', $event)"
                        x-on:drop="onTargetDrop('{{ $ancestor->uuid }}', $event)"
                        x-bind:class="dropTarget === '{{ $ancestor->uuid }}' && 'rounded-md bg-emerald-50 ring-2 ring-emerald-500 dark:bg-emerald-500/15'"
                    >{{ $ancestor->name }}</flux:breadcrumbs.item>
                @endforeach
                @if ($folder)
                    <flux:breadcrumbs.item>{{ $folder->name }}</flux:breadcrumbs.item>
                @endif
            </flux:breadcrumbs>

            <flux:dropdown position="bottom" align="end">
                <flux:button variant="primary" icon="plus" data-test="drive-new-button">{{ __('New') }}</flux:button>

                <flux:menu>
                    <flux:modal.trigger name="create-folder">
                        <flux:menu.item icon="folder-plus" data-test="drive-new-folder-button">{{ __('New folder') }}</flux:menu.item>
                    </flux:modal.trigger>
                    <flux:menu.item icon="arrow-up-tray" x-on:click="$refs.fileInput.click()" data-test="drive-upload-button">{{ __('File upload') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>

        @include('drive.partials.flash')

        {{-- Toolbar: search in this folder, selection summary and the view switch --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:max-w-xs">
                <flux:input
                    x-model="search"
                    icon="magnifying-glass"
                    size="sm"
                    :placeholder="__('Search in this folder')"
                    :aria-label="__('Search in this folder')"
                />
            </div>

            <div x-cloak x-show="selected.length > 0" class="flex items-center gap-2" data-drive-keep-selection>
                <flux:text size="sm" x-text="selectionLabel"></flux:text>
                <flux:button size="sm" variant="ghost" icon="x-mark" x-on:click="clearSelection()">{{ __('Clear') }}</flux:button>
            </div>

            <flux:spacer />

            <flux:button.group>
                <flux:button
                    size="sm"
                    icon="squares-2x2"
                    x-on:click="view = 'grid'"
                    x-bind:aria-pressed="view === 'grid'"
                    x-bind:class="view === 'grid' && '!bg-zinc-100 dark:!bg-zinc-600'"
                    :aria-label="__('Grid view')"
                    :tooltip="__('Grid view')"
                    data-test="drive-view-grid"
                />
                <flux:button
                    size="sm"
                    icon="list-bullet"
                    x-on:click="view = 'list'"
                    x-bind:aria-pressed="view === 'list'"
                    x-bind:class="view === 'list' && '!bg-zinc-100 dark:!bg-zinc-600'"
                    :aria-label="__('List view')"
                    :tooltip="__('List view')"
                    data-test="drive-view-list"
                />
            </flux:button.group>
        </div>

        {{-- Listing; clicking empty space clears the selection --}}
        <div x-cloak class="relative min-h-64 flex-1 pb-24" x-on:click="onBackgroundClick($event)" x-on:contextmenu="closeMenu()">
            <div x-show="visibleItems.length === 0" class="flex flex-col items-center gap-3 py-16 text-center">
                <flux:icon.cloud-arrow-up class="size-12 text-zinc-300 dark:text-zinc-600" />
                <flux:heading x-show="search === ''">{{ __('This folder is empty.') }}</flux:heading>
                <flux:heading x-show="search !== ''">{{ __('No items match your search.') }}</flux:heading>
                <flux:text x-show="search === ''">{{ __('Drop files here or use the New button.') }}</flux:text>
            </div>

            {{-- Grid view --}}
            <div x-show="view === 'grid' && visibleItems.length > 0" class="space-y-6" data-test="drive-grid">
                <section x-show="visibleFolders.length > 0" class="space-y-3">
                    <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Folders') }}</flux:heading>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-6">
                        <template x-for="item in visibleFolders" :key="item.uuid">
                            <div
                                {!! $itemAttributes !!}
                                x-bind:class="{{ $itemStateClasses }}"
                                class="group flex cursor-default items-center gap-3 rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2.5 outline-none select-none hover:bg-zinc-100 focus-visible:ring-2 focus-visible:ring-sky-500 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-700/60"
                                data-test="drive-item"
                            >
                                <x-drive.item-icon class="size-5" />
                                <span class="min-w-0 flex-1 truncate text-sm font-medium text-zinc-800 dark:text-white" x-text="item.name" x-bind:title="item.name"></span>
                                <flux:button
                                    size="xs"
                                    variant="subtle"
                                    icon="ellipsis-vertical"
                                    class="shrink-0 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100"
                                    x-on:click.stop="openMenuFromButton(item, $event)"
                                    x-on:dblclick.stop
                                    :aria-label="__('Actions')"
                                />
                            </div>
                        </template>
                    </div>
                </section>

                <section x-show="visibleFiles.length > 0" class="space-y-3">
                    <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Files') }}</flux:heading>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-6">
                        <template x-for="item in visibleFiles" :key="item.uuid">
                            <div
                                {!! $itemAttributes !!}
                                x-bind:class="{{ $itemStateClasses }}"
                                class="group flex cursor-default flex-col overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50 outline-none select-none hover:bg-zinc-100 focus-visible:ring-2 focus-visible:ring-sky-500 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-700/60"
                                data-test="drive-item"
                            >
                                <div class="flex items-center gap-2 px-3 py-2">
                                    <x-drive.item-icon class="size-4" />
                                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-zinc-800 dark:text-white" x-text="item.name" x-bind:title="item.name"></span>
                                    <flux:button
                                        size="xs"
                                        variant="subtle"
                                        icon="ellipsis-vertical"
                                        class="-me-1 shrink-0"
                                        x-on:click.stop="openMenuFromButton(item, $event)"
                                        x-on:dblclick.stop
                                        :aria-label="__('Actions')"
                                    />
                                </div>

                                <div class="relative mx-2 mb-2 flex aspect-[4/3] items-center justify-center overflow-hidden rounded-lg bg-white dark:bg-zinc-800">
                                    <template x-if="imagePreviewUrl(item)">
                                        <img
                                            x-bind:src="imagePreviewUrl(item)"
                                            x-bind:alt="item.name"
                                            x-on:error="markThumbnailFailed(item)"
                                            loading="lazy"
                                            decoding="async"
                                            draggable="false"
                                            class="max-h-32 max-w-full object-contain"
                                        >
                                    </template>
                                    <template x-if="! imagePreviewUrl(item)">
                                        <div class="flex flex-col items-center gap-2">
                                            <x-drive.item-icon class="size-12" />
                                            <span x-show="item.extension" class="rounded bg-zinc-100 px-1.5 py-0.5 text-[0.625rem] font-semibold tracking-wide text-zinc-500 uppercase dark:bg-zinc-700 dark:text-zinc-300" x-text="item.extension"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </section>
            </div>

            {{-- List view --}}
            <div x-show="view === 'list' && visibleItems.length > 0" class="overflow-x-auto" data-test="drive-list">
                <table class="w-full min-w-[36rem] text-start text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            <th class="py-2.5 ps-3 pe-3 text-start font-medium">{{ __('Name') }}</th>
                            <th class="w-40 px-3 py-2.5 text-start font-medium">{{ __('Type') }}</th>
                            <th class="w-28 px-3 py-2.5 text-start font-medium">{{ __('Size') }}</th>
                            <th class="w-40 px-3 py-2.5 text-start font-medium">{{ __('Modified') }}</th>
                            <th class="w-12 py-2.5 pe-2"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="item in visibleItems" :key="item.uuid">
                            <tr
                                {!! $itemAttributes !!}
                                x-bind:class="{{ $itemStateClasses }}"
                                class="cursor-default border-b border-zinc-100 outline-none select-none hover:bg-zinc-50 focus-visible:bg-zinc-50 dark:border-zinc-700/60 dark:hover:bg-zinc-700/40 dark:focus-visible:bg-zinc-700/40"
                                data-test="drive-item"
                            >
                                <td class="py-2 ps-3 pe-3">
                                    <div class="flex items-center gap-3">
                                        <x-drive.item-icon class="size-5" />
                                        <span class="truncate text-zinc-800 dark:text-white" x-bind:class="item.isFolder && 'font-medium'" x-text="item.name" x-bind:title="item.name"></span>
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-zinc-500 dark:text-zinc-400" x-text="item.kindLabel"></td>
                                <td class="px-3 py-2 text-zinc-500 tabular-nums dark:text-zinc-400" x-text="item.sizeLabel ?? '—'"></td>
                                <td class="px-3 py-2 text-zinc-500 dark:text-zinc-400" x-text="item.modifiedLabel" x-bind:title="formatDate(item.modifiedAt)"></td>
                                <td class="py-2 pe-2 text-end">
                                    <flux:button
                                        size="xs"
                                        variant="subtle"
                                        icon="ellipsis-vertical"
                                        x-on:click.stop="openMenuFromButton(item, $event)"
                                        x-on:dblclick.stop
                                        :aria-label="__('Actions')"
                                    />
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Drop overlay for files dragged in from the desktop --}}
        <div
            x-cloak
            x-show="isDraggingFiles"
            x-transition.opacity
            class="pointer-events-none absolute inset-0 z-30 flex items-center justify-center rounded-2xl border-2 border-dashed border-sky-500 bg-sky-50/80 dark:bg-sky-950/70"
            data-test="drive-drop-overlay"
        >
            <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-8 py-6 shadow-lg dark:bg-zinc-800">
                <flux:icon.cloud-arrow-up class="size-10 text-sky-600 dark:text-sky-400" />
                <flux:heading size="lg">{{ __('Drop files to upload') }}</flux:heading>
                <flux:text>{{ $folder ? __('Files are uploaded to ":name".', ['name' => $folder->name]) : __('Files are uploaded to My Drive.') }}</flux:text>
            </div>
        </div>

        {{-- Context menu, shared by right click and the ⋮ buttons --}}
        <div
            x-cloak
            x-ref="menu"
            x-show="menu.open"
            x-on:click.outside="closeMenu()"
            x-on:scroll.window="closeMenu()"
            x-on:resize.window="closeMenu()"
            x-bind:style="`left: ${menu.x}px; top: ${menu.y}px`"
            class="fixed z-[70] min-w-52 rounded-lg border border-zinc-200 bg-white p-[.3125rem] shadow-lg dark:border-zinc-600 dark:bg-zinc-700"
            role="menu"
            data-test="drive-context-menu"
        >
            <template x-if="menu.item">
                <div class="flex flex-col">
                    @php
                        $menuItemClasses = 'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-start text-sm font-medium text-zinc-800 hover:bg-zinc-50 focus:bg-zinc-50 focus:outline-hidden dark:text-white dark:hover:bg-zinc-600 dark:focus:bg-zinc-600';
                    @endphp
                    <button type="button" role="menuitem" class="{{ $menuItemClasses }}" x-on:click="runMenuAction('open')">
                        <flux:icon.eye variant="mini" class="text-zinc-400 dark:text-white/60" x-show="! menu.item.isFolder" />
                        <flux:icon.folder-open variant="mini" class="text-zinc-400 dark:text-white/60" x-show="menu.item.isFolder" />
                        <span x-text="menu.item.isFolder ? @js(__('Open')) : (menu.item.previewType === 'none' ? @js(__('Open')) : @js(__('Preview')))"></span>
                    </button>
                    <button type="button" role="menuitem" class="{{ $menuItemClasses }}" x-show="! menu.item.isFolder" x-on:click="runMenuAction('download')">
                        <flux:icon.arrow-down-tray variant="mini" class="text-zinc-400 dark:text-white/60" />
                        {{ __('Download') }}
                    </button>

                    <div class="-mx-[.3125rem] my-[.3125rem] h-px bg-zinc-200 dark:bg-zinc-600"></div>

                    <button type="button" role="menuitem" class="{{ $menuItemClasses }}" x-on:click="runMenuAction('rename')">
                        <flux:icon.pencil variant="mini" class="text-zinc-400 dark:text-white/60" />
                        {{ __('Rename') }}
                    </button>
                    <button type="button" role="menuitem" class="{{ $menuItemClasses }}" x-on:click="runMenuAction('move')">
                        <flux:icon.arrow-right-circle variant="mini" class="text-zinc-400 dark:text-white/60" />
                        {{ __('Move') }}
                    </button>

                    <div class="-mx-[.3125rem] my-[.3125rem] h-px bg-zinc-200 dark:bg-zinc-600"></div>

                    <button type="button" role="menuitem" class="{{ $menuItemClasses }} hover:!bg-red-50 hover:!text-red-600 dark:hover:!bg-red-400/20 dark:hover:!text-red-400" x-on:click="runMenuAction('trash')">
                        <flux:icon.trash variant="mini" class="text-zinc-400 dark:text-white/60" />
                        {{ __('Move to trash') }}
                    </button>
                </div>
            </template>
        </div>

        @include('drive.partials.viewer')
        @include('drive.partials.uploads')

        <input type="file" multiple class="hidden" x-ref="fileInput" x-on:change="uploadFiles([...$event.target.files], folderUuid); $event.target.value = ''" data-test="drive-file-input">

        <form method="POST" class="hidden" x-ref="trashForm">
            @csrf
            @method('DELETE')
        </form>

        <flux:modal name="create-folder" focusable class="max-w-lg">
            <form method="POST" action="{{ route('drive.folders.store') }}" class="space-y-6">
                @csrf
                <input type="hidden" name="parent" value="{{ $folder?->uuid }}">

                <flux:heading size="lg">{{ __('New folder') }}</flux:heading>

                <flux:input name="name" :label="__('Folder name')" required autofocus maxlength="255" />

                <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" type="submit">{{ __('Create') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="rename-item" class="max-w-lg">
            <form method="POST" x-bind:action="renameTarget?.urls.rename" class="space-y-6">
                @csrf
                @method('PATCH')

                <flux:heading size="lg">{{ __('Rename') }}</flux:heading>

                <flux:input name="name" x-model="renameName" x-ref="renameInput" :label="__('Name')" required maxlength="255" />

                <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" type="submit">{{ __('Rename') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="move-item" class="max-w-lg">
            <form method="POST" x-bind:action="moveTarget?.urls.move" class="space-y-6">
                @csrf
                @method('PATCH')

                <div>
                    <flux:heading size="lg">{{ __('Move') }} <span x-text="moveTarget ? `“${moveTarget.name}”` : ''"></span></flux:heading>
                    <flux:subheading>{{ __('Choose the folder to move this item into.') }}</flux:subheading>
                </div>

                <flux:select name="destination" x-model="moveDestination" :label="__('Destination')">
                    <flux:select.option value="">{{ __('My Drive') }}</flux:select.option>
                    @foreach ($moveTargets as $uuid => $path)
                        <flux:select.option :value="$uuid" x-bind:disabled="moveTarget?.uuid === '{{ $uuid }}'">{{ $path }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" type="submit">{{ __('Move') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    </div>
</x-layouts::app>
