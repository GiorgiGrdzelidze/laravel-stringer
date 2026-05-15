<x-filament-panels::page>
    {{-- Inline styles, not Tailwind utility classes. The host's Tailwind
         build only scans its own resource paths, so utility classes from
         this package's blade views would be missing from the generated
         CSS bundle. Inline styles work regardless of the host's setup. --}}
    <form wire:submit="save">
        <x-filament::section style="margin-bottom: 1.25rem">
            {{ $this->form }}
        </x-filament::section>

        <div style="display: flex; align-items: center; gap: 0.75rem">
            {{ $this->saveAction }}
        </div>
    </form>
</x-filament-panels::page>
