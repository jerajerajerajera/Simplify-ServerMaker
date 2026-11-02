<x-filament-panels::page>
    @include('simplify-servermaker::wizard.styles')
    <div class="smk">
        <form wire:submit="save" data-simplify-unsaved>
            {{ $this->form }}
        </form>
    </div>
</x-filament-panels::page>
