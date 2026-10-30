{{-- RAM, CPU or disk as a grid of cards of one size: a small icon next to the amount, for RAM with what it suits. --}}
@php
    $icon = ['ram' => 'tabler-device-sd-card', 'cpu' => 'tabler-cpu', 'disk' => 'tabler-database'][$resource] ?? 'tabler-cube';
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="smk-amounts" role="radiogroup" aria-label="{{ $getLabel() }}">
        @foreach ($getOptions() as $value => $label)
            <label @class(['smk-amount', 'is-picked' => (string) $getState() === (string) $value]) wire:key="{{ $getStatePath() }}-{{ $value }}">
                <input type="radio" class="smk-sr-only" name="{{ $getStatePath() }}" value="{{ $value }}"
                    {{ $applyStateBindingModifiers('wire:model') }}="{{ $getStatePath() }}" />
                <x-dynamic-component :component="$value === 'custom' ? 'tabler-adjustments' : $icon" class="smk-amount-icon" aria-hidden="true" />
                <span class="smk-amount-text">
                    <strong>{{ $label }}</strong>
                    @if (is_array($hints) && filled($hints[(string) $value] ?? null))
                        <small>{{ $hints[(string) $value] }}</small>
                    @endif
                </span>
            </label>
        @endforeach
    </div>
</x-dynamic-component>
