{{-- RAM, CPU or disk as a row of cards: an icon, the amount and what it is good for. --}}
@php
    $icon = ['ram' => 'tabler-device-sd-card', 'cpu' => 'tabler-cpu', 'disk' => 'tabler-database'][$resource] ?? 'tabler-cube';
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="smk-amounts" role="radiogroup" aria-label="{{ $getLabel() }}">
        @foreach ($getOptions() as $value => $label)
            <label @class(['smk-amount', 'is-picked' => (string) $getState() === (string) $value]) wire:key="{{ $getStatePath() }}-{{ $value }}">
                <input type="radio" class="smk-sr-only" name="{{ $getStatePath() }}" value="{{ $value }}"
                    {{ $applyStateBindingModifiers('wire:model') }}="{{ $getStatePath() }}" />
                <span class="smk-tile" aria-hidden="true"><x-dynamic-component :component="$icon" /></span>
                <span class="smk-amount-text">
                    <strong>{{ $label }}</strong>
                    <small>{{ $field->getDescription($value) }}</small>
                </span>
            </label>
        @endforeach
    </div>
</x-dynamic-component>
