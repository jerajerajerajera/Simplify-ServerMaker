<x-filament-panels::page>
    @include('simplify-server-maker::wizard.styles')
    <form wire:submit="create" class="smk">
        {{ $this->form }}
    </form>
</x-filament-panels::page>
