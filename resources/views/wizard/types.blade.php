{{-- Minecraft step: the server type again, as cards with an icon, so it can be changed here (Proxy is only here). --}}
@php
    $t = fn (string $key) => __('simplify-servermaker::maker.' . $key);
    $icons = [
        'vanilla' => 'tabler-cube', 'plugins' => 'tabler-puzzle', 'mods' => 'tabler-tool',
        'modpack' => 'tabler-packages', 'bedrock' => 'tabler-device-mobile', 'proxy' => 'tabler-sitemap',
    ];
@endphp
<div class="smk-types" role="radiogroup" aria-label="{{ $t('minecraft.type') }}">
    @foreach ($types as $type)
        @php($picked = ($this->data['type'] ?? null) === $type)
        <button type="button" role="radio" aria-checked="{{ $picked ? 'true' : 'false' }}" @class(['smk-type', 'is-picked' => $picked])
            wire:click="pickGame('minecraft', @js($type))" wire:loading.attr="disabled" wire:target="pickGame">
            <span class="smk-tile" aria-hidden="true"><x-dynamic-component :component="$icons[$type]" /></span>
            <span class="smk-type-text">
                <strong>{{ $t('minecraft.types.' . $type . '.label') }}</strong>
                <small>{{ $t('minecraft.types.' . $type . '.help') }}</small>
            </span>
        </button>
    @endforeach
</div>
