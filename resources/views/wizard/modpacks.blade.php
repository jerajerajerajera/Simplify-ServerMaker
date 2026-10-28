{{-- The modpacks found for the search, as cards with the pack's own icon and a link to its page. --}}
@php
    $packs = collect(\Simplify\ServerMaker\Modpacks\Sources::results((string) ($this->data['modpack_search'] ?? ''), $getState()))->keyBy('key');
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @if ($packs->isEmpty())
        <p class="smk-empty">{{ __('simplify-server-maker::maker.modpack.none') }}</p>
    @else
        <div class="smk-cards" role="radiogroup" aria-label="{{ __('simplify-server-maker::maker.modpack.search') }}">
            @foreach ($packs as $key => $pack)
                <label @class(['smk-card', 'is-picked' => $getState() === $key]) wire:key="smk-pack-{{ $key }}">
                    <input type="radio" class="smk-sr-only" name="{{ $getStatePath() }}" value="{{ $key }}"
                        {{ $applyStateBindingModifiers('wire:model') }}="{{ $getStatePath() }}" />
                    @if ($pack['icon'])
                        <img class="smk-card-image" src="{{ $pack['icon'] }}" alt="" width="40" height="40" loading="lazy" referrerpolicy="no-referrer" />
                    @else
                        <x-tabler-package class="smk-card-icon" aria-hidden="true" />
                    @endif
                    <span class="smk-card-text">
                        <strong>{{ $pack['title'] }}</strong>
                        <small>{{ $pack['description'] }}</small>
                        <span class="smk-card-meta">
                            {{ \Simplify\ServerMaker\Modpacks\Sources::all()[$pack['source']]->name() }} ·
                            <a href="{{ $pack['url'] }}" target="_blank" rel="noopener noreferrer" x-on:click.stop>{{ __('simplify-server-maker::maker.modpack.open') }}</a>
                        </span>
                    </span>
                </label>
            @endforeach
        </div>
    @endif
</x-dynamic-component>
