@if (session('status'))
    <flux:callout variant="success" icon="check-circle" :heading="session('status')" data-test="drive-status" />
@endif

@if ($errors->any())
    <flux:callout variant="danger" icon="exclamation-triangle" data-test="drive-errors">
        <flux:callout.heading>{{ __('Something went wrong') }}</flux:callout.heading>
        <flux:callout.text>
            <ul class="list-disc ps-4">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </flux:callout.text>
    </flux:callout>
@endif
