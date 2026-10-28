{{-- Game step: Minecraft's server types on top, then every other game on the panel, searched in the browser. --}}
@php
    $t = fn (string $key, array $replace = []) => __('simplify-server-maker::maker.' . $key, $replace);
    $data = $this->data;
    $icons = [
        'vanilla' => 'tabler-cube', 'plugins' => 'tabler-plug', 'mods' => 'tabler-tools',
        'modpack' => 'tabler-package', 'bedrock' => 'tabler-device-mobile', 'proxy' => 'tabler-route',
    ];
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="smk-picker">
        @if ($types !== [])
            <section class="smk-group" aria-labelledby="smk-minecraft">
                <h3 id="smk-minecraft">Minecraft</h3>
                <div class="smk-cards" role="radiogroup" aria-labelledby="smk-minecraft">
                    @foreach ($types as $type)
                        @php($picked = ($data['game'] ?? null) === 'minecraft' && ($data['type'] ?? null) === $type)
                        <button type="button" role="radio" aria-checked="{{ $picked ? 'true' : 'false' }}"
                            @class(['smk-card', 'is-picked' => $picked])
                            wire:click="pickGame('minecraft', @js($type))" wire:loading.attr="disabled" wire:target="pickGame">
                            <x-dynamic-component :component="$icons[$type]" class="smk-card-icon" aria-hidden="true" />
                            <span class="smk-card-text">
                                <strong>{{ $t('minecraft.types.' . $type . '.label') }}</strong>
                                <small>{{ $t('minecraft.types.' . $type . '.help') }}</small>
                            </span>
                        </button>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- People who are not administrators only see this when they may pick another game. --}}
        @if ($games !== [] || $this->isAdmin())
            <section class="smk-group" aria-labelledby="smk-games"
                x-data="{
                    query: '',
                    limit: 24,
                    games: @js($games),
                    norm(text) { return String(text || '').toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, ' ').trim(); },
                    shown() {
                        const words = this.norm(this.query).split(' ').filter(Boolean);
                        return this.games.filter((game) => words.every((word) => this.norm(game.name + ' ' + game.description).includes(word)));
                    },
                }">
                <div class="smk-group-head">
                    <h3 id="smk-games">{{ $t('game.other') }}</h3>
                    @if (count($games) > 6)
                        <label class="smk-search">
                            <x-tabler-search aria-hidden="true" />
                            <input type="search" x-model.debounce.120ms="query" x-on:input="limit = 24"
                                placeholder="{{ $t('game.search', ['count' => count($games)]) }}"
                                aria-label="{{ $t('game.search_label') }}" autocomplete="off" />
                        </label>
                    @endif
                </div>
                @if ($games === [])
                    <p class="smk-empty">
                        {{ $t('game.none') }}
                        @if (user()?->can('create', \App\Models\Egg::class))
                            <a href="{{ rescue(fn () => route('filament.admin.resources.eggs.index'), '#', false) }}">{{ $t('game.import') }}</a>
                        @endif
                    </p>
                @else
                    <div class="smk-cards" role="radiogroup" aria-labelledby="smk-games">
                        <template x-for="game in shown().slice(0, limit)" :key="game.id">
                            <button type="button" role="radio" class="smk-card"
                                :class="{ 'is-picked': $wire.data.game === 'other' && Number($wire.data.egg_id) === game.id }"
                                :aria-checked="$wire.data.game === 'other' && Number($wire.data.egg_id) === game.id"
                                x-on:click="$wire.pickGame('other', String(game.id))">
                                <x-tabler-device-gamepad-2 class="smk-card-icon" aria-hidden="true" />
                                <span class="smk-card-text">
                                    <strong x-text="game.name"></strong>
                                    <small x-text="game.description"></small>
                                </span>
                            </button>
                        </template>
                    </div>
                    <p class="smk-empty" x-show="shown().length === 0" x-cloak>{{ $t('game.no_match') }}</p>
                    <button type="button" class="smk-more" x-show="shown().length > limit" x-cloak x-on:click="limit += 48"
                        x-text="@js($t('game.more')).replace(':count', shown().length - limit)"></button>
                @endif
            </section>
        @endif
    </div>
</x-dynamic-component>
