<x-filament-panels::page>
    @include('simplify-servermaker::wizard.styles')
    <form wire:submit="create" class="smk">
        {{ $this->form }}
    </form>
</x-filament-panels::page>
