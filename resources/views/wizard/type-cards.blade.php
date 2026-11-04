{{-- Server software: the Minecraft types as cards with an icon, as a form field (the wizard's cards call the page). --}}
@php
    $t = fn (string $key) => __('simplify-servermaker::maker.' . $key);
    $icons = ['vanilla' => 'tabler-cube', 'plugins' => 'tabler-puzzle', 'mods' => 'tabler-tool', 'modpack' => 'tabler-packages'];
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="smk-types" role="radiogroup" aria-label="{{ $t('minecraft.type') }}">
        @foreach (array_keys($getOptions()) as $type)
            <label @class(['smk-type', 'is-picked' => $getState() === $type]) wire:key="{{ $getStatePath() }}-{{ $type }}">
                <input type="radio" class="smk-sr-only" name="{{ $getStatePath() }}" value="{{ $type }}"
                    {{ $applyStateBindingModifiers('wire:model') }}="{{ $getStatePath() }}" />
                <span class="smk-tile" aria-hidden="true"><x-dynamic-component :component="$icons[$type] ?? 'tabler-cube'" /></span>
                <span class="smk-type-text">
                    <strong>{{ $t('minecraft.types.' . $type . '.label') }}</strong>
                    <small>{{ $t('minecraft.types.' . $type . '.help') }}</small>
                </span>
            </label>
        @endforeach
    </div>
</x-dynamic-component>
