{{-- Game step: Minecraft with its server types on top, then every other game on the panel, searched in the browser.
     Picking a Minecraft type moves on to the next step by itself; another game is picked first and then Next. --}}
@php
    $t = fn (string $key, array $replace = []) => __('simplify-servermaker::maker.' . $key, $replace);
    $data = $this->data;
    // Proxy is not a game of its own: it is picked on the Minecraft step.
    $front = array_values(array_diff($types, ['proxy']));
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="smk-picker">
        @if ($front !== [])
            <section @class(['smk-minecraft', 'is-picked' => ($data['game'] ?? null) === 'minecraft']) aria-labelledby="smk-minecraft"
                x-data="{ busy: false, pick(type) {
                    if (this.busy) return;
                    this.busy = true;
                    // Moves on once Livewire has re-rendered and enabled Next again.
                    const next = (tries) => {
                        const button = [...document.querySelectorAll('.smk .fi-sc-wizard-footer .smk-next')].find((b) => b.offsetParent);
                        if (button && !button.disabled) button.click(); else if (tries > 0) setTimeout(() => next(tries - 1), 100);
                    };
                    $wire.pickGame('minecraft', type).then(() => next(30)).finally(() => this.busy = false);
                } }">
                <div class="smk-minecraft-head">
                    <span class="smk-minecraft-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="1"/><path fill="currentColor" stroke="none" d="M6 7h4v4H6zm8 0h4v4h-4zm-4 4h4v3h3v5h-3v-3h-4v3H7v-5h3z"/></svg></span>
                    <div>
                        <h3 id="smk-minecraft">Minecraft</h3>
                        <p>{{ $t('game.minecraft_help') }}</p>
                    </div>
                </div>
                <div class="smk-minecraft-types" role="group" aria-labelledby="smk-minecraft">
                    @foreach ($front as $type)
                        @php($picked = ($data['game'] ?? null) === 'minecraft' && ($data['type'] ?? null) === $type)
                        <button type="button" class="smk-minecraft-type" aria-pressed="{{ $picked ? 'true' : 'false' }}"
                            x-on:click="pick(@js($type))" :disabled="busy">
                            <strong>{{ $t('minecraft.types.' . $type . '.label') }}</strong>
                            <small>{{ $t('minecraft.types.' . $type . '.help') }}</small>
                        </button>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- People who are not administrators only see this when they may pick another game. --}}
        @if ($games !== [] || $this->isAdmin())
            @php($available = count(array_filter($games, fn (array $game) => ! $game['installed'])))
            <section class="smk-games" aria-labelledby="smk-games"
                x-data="{
                    query: '',
                    filter: 'all',
                    limit: 24,
                    games: @js($games),
                    installing: null,
                    phase: 0,
                    timer: null,
                    norm(text) { return String(text || '').toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, ' ').trim(); },
                    shown() {
                        const words = this.norm(this.query).split(' ').filter(Boolean);
                        return this.games.filter((game) => (this.filter !== 'installed' || game.installed) && (this.filter !== 'popular' || game.popular > 0)
                            && words.every((word) => this.norm(game.name + ' ' + game.description).includes(word)));
                    },
                    picked() {
                        const key = $wire.data.game !== 'other' ? null : ($wire.data.game_index ? 'index:' + $wire.data.game_index : 'egg:' + $wire.data.egg_id);
                        return this.games.find((game) => game.key === key) || null;
                    },
                    initials(name) { return name.replace(/[^A-Za-z0-9 ]/g, ' ').split(' ').filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase() || '?'; },
                    hue(name) { let h = 0; for (const ch of name) h = (h * 31 + ch.charCodeAt(0)) % 360; return h; },
                    init() {
                        // Next on a game that is not on the panel yet installs its egg first: shown as its own screen.
                        this.$root.closest('.smk')?.addEventListener('click', (event) => {
                            if (!event.target.closest('.smk-next') || !this.picked() || this.picked().installed) return;
                            this.installing = this.picked().name;
                            this.phase = 0;
                            clearInterval(this.timer);
                            this.timer = setInterval(() => this.phase = Math.min(this.phase + 1, 2), 1600);
                        }, true);
                        window.Livewire?.hook?.('commit', ({ succeed, fail }) => {
                            if (!this.installing) return;
                            const done = () => setTimeout(() => { clearInterval(this.timer); this.installing = null; }, 150);
                            succeed(done);
                            fail(done);
                        });
                    },
                    destroy() { clearInterval(this.timer); },
                }">
                <div class="smk-install" x-show="installing" x-cloak role="status" aria-live="polite">
                    <span class="smk-install-spinner" aria-hidden="true"></span>
                    <strong>{{ $t('game.installing') }} <span x-text="installing"></span></strong>
                    <ol>
                        <li :class="{ 'is-done': phase > 0, 'is-now': phase === 0 }">{{ $t('game.step_download') }}</li>
                        <li :class="{ 'is-done': phase > 1, 'is-now': phase === 1 }">{{ $t('game.step_check') }}</li>
                        <li :class="{ 'is-now': phase === 2 }">{{ $t('game.step_add') }}</li>
                    </ol>
                    <small>{{ $t('game.continues') }}</small>
                </div>
                @if ($front !== [])
                    <h3 id="smk-games" class="smk-games-heading">{{ $t('game.other') }}</h3>
                @else
                    <h3 id="smk-games" class="smk-sr-only">{{ $t('game.other') }}</h3>
                @endif
                @if ($games === [])
                    <p class="smk-empty">
                        {{ $t('game.none') }}
                        @if (user()?->can('create', \App\Models\Egg::class))
                            <a href="{{ rescue(fn () => route('filament.admin.resources.eggs.index'), '#', false) }}">{{ $t('game.import') }}</a>
                        @endif
                    </p>
                @else
                    {{-- A search only helps with a longer list; the filters only when some games are not on the panel yet. --}}
                    @if (count($games) > 6 || $available > 0)
                        <div class="smk-games-bar">
                            @if (count($games) > 6)
                                <label class="smk-search">
                                    <x-tabler-search aria-hidden="true" />
                                    <input type="search" x-model.debounce.120ms="query" x-on:input="limit = 24"
                                        placeholder="{{ $t('game.search', ['count' => count($games)]) }}"
                                        aria-label="{{ $t('game.search_label') }}" autocomplete="off" />
                                </label>
                            @endif
                            @if ($available > 0)
                                <div class="smk-chips" role="group" aria-label="{{ $t('game.filter') }}">
                                    <button type="button" :aria-pressed="filter === 'all'" x-on:click="filter = 'all'; limit = 24">{{ $t('game.all') }}</button>
                                    <button type="button" :aria-pressed="filter === 'popular'" x-on:click="filter = 'popular'; limit = 24">{{ $t('game.popular') }}</button>
                                    <button type="button" :aria-pressed="filter === 'installed'" x-on:click="filter = 'installed'; limit = 24">{{ $t('game.installed') }}</button>
                                </div>
                            @endif
                        </div>
                    @endif
                    <div class="smk-games-grid" role="radiogroup" aria-labelledby="smk-games">
                        <template x-for="game in shown().slice(0, limit)" :key="game.key">
                            <button type="button" role="radio" class="smk-game"
                                :aria-checked="picked()?.key === game.key"
                                x-on:click="$wire.pickGame('other', game.key)">
                                <span class="smk-game-icon" :style="{ '--hue': hue(game.name) }" x-text="initials(game.name)" aria-hidden="true"></span>
                                <span class="smk-game-text">
                                    <strong x-text="game.name"></strong>
                                    <small x-text="game.description"></small>
                                </span>
                                @if ($available > 0)
                                    <span class="smk-game-badge" :class="game.installed ? 'is-installed' : 'is-available'"
                                        x-text="game.installed ? @js($t('game.installed')) : @js($t('game.available'))"></span>
                                @endif
                            </button>
                        </template>
                    </div>
                    <p class="smk-empty" x-show="shown().length === 0" x-cloak>{{ $t('game.no_match') }}</p>
                    <button type="button" class="smk-more" x-show="shown().length > limit" x-cloak x-on:click="limit += 48"
                        x-text="@js($t('game.more')).replace(':count', shown().length - limit)"></button>
                    <p class="smk-games-note" x-show="picked() && !picked().installed" x-cloak>
                        <strong x-text="picked()?.name"></strong> {{ $t('game.not_here') }}
                    </p>
                @endif
            </section>
        @endif
    </div>
</x-dynamic-component>
