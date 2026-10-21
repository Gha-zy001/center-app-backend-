<x-filament-panels::page>
    <form wire:submit="save" class="grid gap-6">
        {{ $this->form }}

        <div>
            {{ $this->saveAction }}
        </div>
    </form>

    <x-filament-actions::modals />
</x-filament-panels::page>
