<x-filament-panels::page>
    <form wire:submit="send" class="grid gap-6">
        {{ $this->form }}

        <div>
            {{ $this->sendAction }}
        </div>
    </form>

    <x-filament-actions::modals />

    @push('scripts')
        <style>
            .fi-fo-radio-group {
                gap: 1.5rem !important;
            }
        </style>
    @endpush
</x-filament-panels::page>
