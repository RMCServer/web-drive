{{-- Fullscreen file viewer; rendered inside the Drive browser's Alpine scope --}}
<div
    x-cloak
    x-show="viewer.open"
    x-transition.opacity.duration.150ms
    x-ref="viewer"
    tabindex="-1"
    role="dialog"
    aria-modal="true"
    x-bind:aria-label="viewer.item?.name"
    class="fixed inset-0 z-[60] flex flex-col bg-zinc-950/95 text-white outline-none"
    data-test="drive-viewer"
>
    <template x-for="item in (viewer.item ? [viewer.item] : [])" :key="item.uuid">
        <div class="flex min-h-0 flex-1 flex-col">
            <header class="flex items-center gap-2 px-3 py-2 sm:px-4">
                <flux:button variant="ghost" icon="arrow-left" class="!text-white hover:!bg-white/10" x-on:click="closeViewer()" :aria-label="__('Back to Drive')" />
                <x-drive.item-icon class="size-5" />
                <h2 class="min-w-0 flex-1 truncate text-sm font-medium sm:text-base" x-text="item.name" x-bind:title="item.name"></h2>

                <div class="flex items-center gap-1">
                    <flux:button
                        variant="ghost"
                        icon="arrow-down-tray"
                        class="!text-white hover:!bg-white/10"
                        x-on:click="download(item)"
                        :aria-label="__('Download')"
                    ><span class="hidden sm:inline">{{ __('Download') }}</span></flux:button>
                    <flux:button
                        variant="ghost"
                        icon="ellipsis-vertical"
                        class="!text-white hover:!bg-white/10"
                        x-on:click.stop="openMenuFromButton(item, $event)"
                        :aria-label="__('More actions')"
                    />
                    <flux:button variant="ghost" icon="x-mark" class="!text-white hover:!bg-white/10" x-on:click="closeViewer()" :aria-label="__('Close')" />
                </div>
            </header>

            <div class="relative flex min-h-0 flex-1 items-center justify-center px-4 pb-4 sm:px-16" x-on:click.self="closeViewer()">
                <button
                    type="button"
                    x-show="viewerFiles.length > 1"
                    x-on:click="showAdjacentFile(-1)"
                    class="absolute start-2 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/10 p-2 hover:bg-white/20 sm:start-4"
                    aria-label="{{ __('Previous file') }}"
                >
                    <flux:icon.chevron-left class="size-6" />
                </button>

                {{-- Image: fits the viewport, click to toggle full size --}}
                <template x-if="item.previewType === 'image'">
                    <div class="flex size-full items-center justify-center" x-bind:class="viewer.zoomed ? 'overflow-auto items-start justify-start' : 'overflow-hidden'">
                        <img
                            x-bind:src="item.urls.preview"
                            x-bind:alt="item.name"
                            x-on:click="viewer.zoomed = ! viewer.zoomed"
                            x-bind:class="viewer.zoomed ? 'max-w-none cursor-zoom-out' : 'max-h-full max-w-full object-contain cursor-zoom-in'"
                            class="m-auto select-none"
                            draggable="false"
                            data-test="drive-viewer-image"
                        >
                    </div>
                </template>

                <template x-if="item.previewType === 'pdf'">
                    <iframe x-bind:src="item.urls.preview" x-bind:title="item.name" class="size-full max-w-6xl rounded-lg bg-white"></iframe>
                </template>

                <template x-if="item.previewType === 'video'">
                    <video x-bind:src="item.urls.preview" controls autoplay preload="metadata" class="max-h-full max-w-full rounded-lg bg-black"></video>
                </template>

                <template x-if="item.previewType === 'audio'">
                    <div class="flex w-full max-w-md flex-col items-center gap-6 rounded-2xl bg-white/5 p-8">
                        <flux:icon.musical-note class="size-20 text-fuchsia-400" />
                        <p class="max-w-full truncate text-sm" x-text="item.name"></p>
                        <audio x-bind:src="item.urls.preview" controls autoplay preload="metadata" class="w-full"></audio>
                    </div>
                </template>

                <template x-if="item.previewType === 'text'">
                    <div class="flex size-full max-w-5xl flex-col overflow-hidden rounded-lg bg-white text-zinc-800 dark:bg-zinc-900 dark:text-zinc-100">
                        <div x-show="text.loading" class="flex flex-1 items-center justify-center">
                            <flux:icon.loading class="size-6 text-zinc-400" />
                        </div>
                        <p x-show="text.failed" class="m-auto text-sm text-zinc-500">{{ __('The preview could not be loaded.') }}</p>
                        <pre x-show="! text.loading && ! text.failed" class="flex-1 overflow-auto p-4 font-mono text-xs leading-relaxed whitespace-pre-wrap break-words sm:p-6 sm:text-sm" x-text="text.content"></pre>
                        <p x-show="text.truncated" class="border-t border-zinc-200 px-4 py-2 text-xs text-zinc-500 dark:border-zinc-700">
                            {{ __('Only the beginning of this file is shown. Download it to see everything.') }}
                        </p>
                    </div>
                </template>

                {{-- Unsupported: a clean file card with a download button --}}
                <template x-if="item.previewType === 'none'">
                    <div class="flex w-full max-w-sm flex-col items-center gap-4 rounded-2xl bg-white/5 p-8 text-center" data-test="drive-viewer-fallback">
                        <x-drive.item-icon class="size-20" />
                        <div class="w-full space-y-1">
                            <p class="truncate font-medium" x-text="item.name"></p>
                            <p class="text-sm text-white/60" x-text="[item.kindLabel, item.mimeType, item.sizeLabel].filter(Boolean).join(' · ')"></p>
                        </div>
                        <p class="text-sm text-white/60">{{ __('No preview available for this file type.') }}</p>
                        <flux:button variant="primary" icon="arrow-down-tray" x-on:click="download(item)">{{ __('Download') }}</flux:button>
                    </div>
                </template>

                <button
                    type="button"
                    x-show="viewerFiles.length > 1"
                    x-on:click="showAdjacentFile(1)"
                    class="absolute end-2 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/10 p-2 hover:bg-white/20 sm:end-4"
                    aria-label="{{ __('Next file') }}"
                >
                    <flux:icon.chevron-right class="size-6" />
                </button>
            </div>

            <footer class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-t border-white/10 px-4 py-2 text-xs text-white/70 sm:text-sm">
                <span class="min-w-0 truncate" x-text="item.name"></span>
                <span class="flex items-center gap-3">
                    <span x-show="viewerFiles.length > 1" x-text="viewerPositionLabel"></span>
                    <span x-text="[item.sizeLabel, item.mimeType].filter(Boolean).join(' · ')"></span>
                </span>
            </footer>
        </div>
    </template>
</div>
