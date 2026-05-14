<x-filament-panels::page>
    <form wire:submit="save" class="grid gap-y-6">
        <x-filament::section>
            {{ $this->form }}
        </x-filament::section>

        <div class="mt-4 flex items-center gap-3">
            {{ $this->saveAction }}
        </div>
    </form>
</x-filament-panels::page>
