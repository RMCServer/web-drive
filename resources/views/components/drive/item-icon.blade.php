@props([
    'kind' => 'item.kind',
])

@php
$colors = [
    'folder' => 'text-zinc-500 dark:text-zinc-400',
    'image' => 'text-rose-500',
    'pdf' => 'text-red-600 dark:text-red-400',
    'video' => 'text-violet-500',
    'audio' => 'text-fuchsia-500',
    'document' => 'text-blue-600 dark:text-blue-400',
    'spreadsheet' => 'text-emerald-600 dark:text-emerald-400',
    'presentation' => 'text-amber-500',
    'archive' => 'text-zinc-500 dark:text-zinc-400',
    'code' => 'text-sky-600 dark:text-sky-400',
    'text' => 'text-zinc-500 dark:text-zinc-400',
    'other' => 'text-zinc-400',
];
@endphp

{{-- Renders only the icon matching the Alpine expression, so client-rendered listings stay light. --}}
@foreach (App\Enums\DriveFileKind::cases() as $fileKind)
    <template x-if="{{ $kind }} === '{{ $fileKind->value }}'">
        <flux:icon :icon="$fileKind->icon()" variant="solid" :attributes="$attributes->class(['shrink-0', $colors[$fileKind->value]])" />
    </template>
@endforeach
