{{-- Upload progress panel; rendered inside the Drive browser's Alpine scope --}}
<div
    x-cloak
    x-show="uploads.length > 0"
    x-transition
    class="fixed end-4 bottom-4 z-40 w-[calc(100%-2rem)] max-w-sm overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800"
    data-test="drive-uploads"
>
    <div class="flex items-center justify-between gap-2 border-b border-zinc-200 px-4 py-2.5 dark:border-zinc-700">
        <flux:heading x-text="uploadSummary"></flux:heading>
        <flux:button
            size="sm"
            variant="subtle"
            icon="x-mark"
            x-show="! hasPendingUploads"
            x-on:click="clearFinishedUploads()"
            :aria-label="__('Close')"
        />
    </div>

    <ul class="max-h-72 divide-y divide-zinc-100 overflow-y-auto dark:divide-zinc-700">
        <template x-for="upload in uploads" :key="upload.id">
            <li class="flex items-center gap-3 px-4 py-2.5">
                <div class="min-w-0 flex-1 space-y-1.5">
                    <p class="truncate text-sm text-zinc-800 dark:text-white" x-text="upload.name" x-bind:title="upload.name"></p>
                    <div x-show="upload.status === 'queued' || upload.status === 'uploading'" class="h-1 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700">
                        <div class="h-full rounded-full bg-sky-500 transition-[width]" x-bind:style="`width: ${upload.progress}%`"></div>
                    </div>
                    <p x-show="upload.status === 'failed'" class="text-xs text-red-600 dark:text-red-400" x-text="upload.error"></p>
                </div>

                <flux:icon.check-circle variant="mini" class="text-emerald-500" x-show="upload.status === 'done'" />
                <flux:icon.exclamation-circle variant="mini" class="text-red-500" x-show="upload.status === 'failed'" />
                <span x-show="upload.status === 'uploading'" class="text-xs text-zinc-500 tabular-nums" x-text="`${upload.progress}%`"></span>
            </li>
        </template>
    </ul>
</div>
