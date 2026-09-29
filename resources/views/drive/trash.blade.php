<x-layouts::app :title="__('Trash')">
    <div class="flex w-full flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('Trash') }}</flux:heading>
            <flux:subheading>{{ __('Restoring a folder also restores everything that was trashed with it.') }}</flux:subheading>
        </div>

        @include('drive.partials.flash')

        @if ($items->isEmpty())
            <flux:text class="py-12 text-center">{{ __('The trash is empty.') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Original location') }}</flux:table.column>
                    <flux:table.column>{{ __('Trashed') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($items as $item)
                        <flux:table.row :key="$item->uuid" data-test="trash-item">
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <flux:icon :name="$item->isFolder() ? 'folder' : 'document'" variant="mini" class="text-zinc-400" />
                                    <span>{{ $item->name }}</span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $item->parent?->name ?? __('My Drive') }}</flux:table.cell>
                            <flux:table.cell>{{ $item->deleted_at?->diffForHumans() }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="flex justify-end gap-2">
                                    <form method="POST" action="{{ route('drive.trash.restore', $item) }}">
                                        @csrf
                                        <flux:button type="submit" size="sm" icon="arrow-uturn-left">{{ __('Restore') }}</flux:button>
                                    </form>

                                    <flux:modal.trigger :name="'delete-'.$item->uuid">
                                        <flux:button size="sm" variant="danger" icon="trash">{{ __('Delete forever') }}</flux:button>
                                    </flux:modal.trigger>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>

    @foreach ($items as $item)
        <flux:modal :name="'delete-'.$item->uuid" focusable class="max-w-lg">
            <form method="POST" action="{{ route('drive.trash.destroy', $item) }}" class="space-y-6">
                @csrf
                @method('DELETE')

                <div>
                    <flux:heading size="lg">{{ __('Delete ":name" forever?', ['name' => $item->name]) }}</flux:heading>
                    <flux:subheading>
                        {{ $item->isFolder()
                            ? __('The folder and everything inside it will be deleted permanently. This cannot be undone.')
                            : __('The file will be deleted permanently. This cannot be undone.') }}
                    </flux:subheading>
                </div>

                <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" type="submit">{{ __('Delete forever') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endforeach
</x-layouts::app>
