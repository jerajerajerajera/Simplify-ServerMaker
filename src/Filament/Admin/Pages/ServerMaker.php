<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Filament\Admin\Pages;

use App\Filament\Server\Pages\Console;
use App\Models\Egg;
use App\Services\Servers\RandomWordService;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Simplify\Core\Addons\Addon;
use Simplify\ServerMaker\Access;
use Simplify\ServerMaker\Creation\ApplyFirstSettings;
use Simplify\ServerMaker\Creation\ApplySteamArt;
use Simplify\ServerMaker\Creation\BackupSchedule;
use Simplify\ServerMaker\Creation\Creator;
use Simplify\ServerMaker\Creation\EggData;
use Simplify\ServerMaker\Creation\FirstSettings;
use Simplify\ServerMaker\Creation\Payload;
use Simplify\ServerMaker\Creation\Ports;
use Simplify\ServerMaker\Creation\Resources;
use Simplify\ServerMaker\Creation\ServerColor;
use Simplify\ServerMaker\Creation\Subdomain;
use Simplify\ServerMaker\Filament\Wizard\GameStep;
use Simplify\ServerMaker\Filament\Wizard\GameVariables;
use Simplify\ServerMaker\Filament\Wizard\ResourcesStep;
use Simplify\ServerMaker\Filament\Wizard\ReviewStep;
use Simplify\ServerMaker\Filament\Wizard\ServerStep;
use Simplify\ServerMaker\Filament\Wizard\SetupStep;
use Simplify\ServerMaker\Games\BundledEggs;
use Simplify\ServerMaker\Games\EggIndex;
use Simplify\ServerMaker\Games\Java;
use Simplify\ServerMaker\Games\Minecraft;
use Simplify\ServerMaker\Games\MinecraftEggs;
use Simplify\ServerMaker\Games\MinecraftVersions;
use Simplify\ServerMaker\Games\OtherGames;
use Simplify\ServerMaker\Games\SteamArt;
use Simplify\ServerMaker\Models\Draft;
use Simplify\ServerMaker\Modpacks\Sources;
use Throwable;

/** The Server Maker: a wizard that creates a game server in a few steps. */
class ServerMaker extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'server-maker';

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-wand';

    protected static ?int $navigationSort = 80;

    protected string $view = 'simplify-servermaker::pages.server-maker';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** The step to open on: 1, or where a saved draft left off. */
    #[Locked]
    public int $step = 1;

    #[Locked]
    public bool $restored = false;

    /** Review: the advanced settings are open (Edit settings), as in 0.7.0. */
    public bool $editingAdvanced = false;

    /** Whether there is a saved draft, shown in the bar at the bottom with Discard. */
    public bool $hasDraft = false;

    public static function getNavigationLabel(): string
    {
        return __('simplify-servermaker::maker.title');
    }

    /** In the Simplify group of Pelican's menu, with the other Simplify add-ons (Simplify's ADDONS.md, "Admin menu"). */
    public static function getNavigationGroup(): ?string
    {
        return 'Simplify';
    }

    public function getTitle(): string
    {
        return __('simplify-servermaker::maker.title');
    }

    public static function canAccess(): bool
    {
        return Access::canUse();
    }

    /** Whether the person may change everything: the egg, the image, every variable. */
    public function isAdmin(): bool
    {
        return Access::admin();
    }

    public function mount(): void
    {
        $node = Ports::nodes()->orderBy('id')->value('id');
        $fresh = [
            'game' => null,
            'type' => null,
            'software' => null,
            'version' => null,
            'egg_id' => null,
            'game_index' => null,
            'variables' => [],
            'modpack' => null,
            'modpack_version' => null,
            ...FirstSettings::DEFAULTS,
            'server_icon' => null,
            'eula' => false,
            'steam_art' => true,
            'server_color' => null,
            'backup_schedule' => false,
            ...BackupSchedule::defaults(),
            'name' => '',
            'node_id' => $node,
            'allocation_id' => Ports::preferred($node),
            'subdomain' => '',
            'backups' => 2,
            'skip_scripts' => false,
            'java' => 'auto',
            'image' => '',
            'startup' => '',
            'environment' => [],
            ...$this->resourceDefaults('vanilla'),
        ];
        $draft = Draft::for((int) user()->id);
        if ($draft) {
            try {
                $fresh = array_replace($fresh, $draft->data);
                $this->step = max(1, min(5, $draft->step));
                $this->restored = true;
                $this->hasDraft = true;
                Notification::make()->title(__('simplify-servermaker::maker.draft.restored'))->info()->send();
            } catch (Throwable $exception) {
                // A draft that cannot be read (for example after a new APP_KEY) is dropped.
                report($exception);
                $draft->delete();
            }
        }
        $this->form->fill($fresh);
        if (! $this->isAdmin() && Access::remaining(user())['servers'] < 1) {
            Notification::make()
                ->title(__('simplify-servermaker::maker.access.no_servers_left'))
                ->warning()
                ->persistent()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('settings')
                ->label(__('simplify-servermaker::maker.settings.title'))
                ->icon('tabler-settings')
                ->color('gray')
                // With Simplify the settings open from Simplify, and this page always opens in its popup.
                ->visible(fn () => MakerSettings::canAccess() && ! class_exists(Addon::class))
                ->url(fn () => MakerSettings::getUrl(panel: 'admin')),
        ];
    }

    /** Saves the answers so far after each step, so a closed tab or a lost connection costs nothing. */
    /** Leaving the game step: a game from the egg index gets its egg first (downloaded, checked, imported). */
    public function gameDone(): void
    {
        abort_unless(static::canAccess(), 403);
        if (($this->data['game'] ?? null) === 'other' && blank($this->data['egg_id'] ?? null) && filled($this->data['game_index'] ?? null)) {
            $egg = EggIndex::install((string) $this->data['game_index']);
            $this->data = array_replace($this->data, [
                'egg_id' => $egg->id,
                'game_index' => null,
                'variables' => GameVariables::defaults($egg, $this->isAdmin()),
            ]);
        }
        $this->stepDone(1);
    }

    public function stepDone(int $step): void
    {
        abort_unless(static::canAccess(), 403);
        Draft::query()->updateOrCreate(
            ['user_id' => (int) user()->id],
            ['data' => [...Draft::clean($this->data ?? []), 'eula' => false], 'step' => min(5, $step + 1)],
        );
        $this->hasDraft = true;
    }

    public function toggleAdvanced(): void
    {
        abort_unless($this->isAdmin(), 403);
        $this->editingAdvanced = ! $this->editingAdvanced;
    }

    /** Discard in the bar at the bottom: the answers so far are removed and the Server Maker starts empty. */
    public function discardDraft(): void
    {
        abort_unless(static::canAccess(), 403);
        Draft::query()->where('user_id', user()->id)->delete();
        $this->redirect(static::getUrl());
    }

    /**
     * The step names for the bar at the bottom. The second step is Minecraft, or the game's settings.
     *
     * @return list<string>
     */
    public function stepLabels(): array
    {
        $t = fn (string $key) => __('simplify-servermaker::maker.steps.'.$key);

        return [
            $t('game'),
            ($this->data['game'] ?? null) === 'other' ? $t('settings') : $t('minecraft'),
            $t('server'),
            $t('resources'),
            $t('review'),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Wizard::make([
                    GameStep::make($this),
                    SetupStep::make($this),
                    ServerStep::make($this),
                    ResourcesStep::make($this),
                    ReviewStep::make($this),
                ])
                    ->columnSpanFull()
                    ->startOnStep(fn () => $this->step)
                    ->nextAction(fn (Action $action) => $action->label(__('simplify-servermaker::maker.next'))
                        ->view(Action::LINK_VIEW)->extraAttributes(['class' => 'smk-btn smk-next']))
                    ->previousAction(fn (Action $action) => $action->label(__('simplify-servermaker::maker.back'))
                        ->view(Action::LINK_VIEW)->extraAttributes(['class' => 'smk-btn smk-back']))
                    ->submitAction(new HtmlString(view('simplify-servermaker::wizard.create-button')->render())),
            ]);
    }

    /** The Game step: Minecraft with a server type, or another game by its egg. */
    public function pickGame(string $game, string $choice): void
    {
        abort_unless(static::canAccess(), 403);
        if ($game === 'minecraft' && Minecraft::isType($choice) && Access::minecraft()) {
            $software = Minecraft::software($choice, $this->data['software'] ?? null);
            $this->data = array_replace($this->data, [
                'game' => 'minecraft',
                'type' => $choice,
                'software' => $software,
                'version' => $this->data['version'] ?? MinecraftVersions::newest(),
                'egg_id' => null,
                'game_index' => null,
                'variables' => [],
                'modpack' => $choice === 'modpack' ? ($this->data['modpack'] ?? null) : null,
                'modpack_version' => $choice === 'modpack' ? ($this->data['modpack_version'] ?? null) : null,
                ...$this->resourceDefaults($choice),
            ]);

            return;
        }
        // A game that is not on the panel yet: its egg is installed when the game step is done.
        $index = $game === 'other' && str_starts_with($choice, 'index:') ? EggIndex::find(substr($choice, 6)) : null;
        if ($index && EggIndex::canInstall()) {
            $this->data = array_replace($this->data, [
                'game' => 'other',
                'type' => null,
                'software' => null,
                'egg_id' => null,
                'game_index' => (string) $index['id'],
                'variables' => [],
                ...$this->resourceDefaults(null),
            ]);

            return;
        }
        $egg = $game === 'other' ? OtherGames::find($choice) : null;
        if (! $egg) {
            throw ValidationException::withMessages(['data.game' => __('simplify-servermaker::maker.game.required')]);
        }
        $this->data = array_replace($this->data, [
            'game' => 'other',
            'type' => null,
            'software' => null,
            'egg_id' => $egg->id,
            'game_index' => null,
            'variables' => GameVariables::defaults($egg, $this->isAdmin()),
            ...$this->resourceDefaults(null),
        ]);
    }

    /** @return array<string, int|string> the resource fields for a Minecraft type, or for other games */
    private function resourceDefaults(?string $type): array
    {
        $start = $type === null ? ['cpu' => 100, 'ram' => 4, 'disk' => 10240] : Minecraft::resources($type);
        $limits = Access::resourceLimits();
        $values = [];
        foreach ($start as $resource => $amount) {
            if ($limits !== null) {
                $amount = max(1, min($amount, $limits[$resource]));
            }
            $values[$resource] = Resources::choice($amount, Resources::presets($resource, $amount, $limits[$resource] ?? null));
            $values[$resource.'_custom'] = $amount;
        }

        return $values;
    }

    /** Leaving the setup step: the egg for the chosen Minecraft type or modpack, added from the bundled eggs when missing. */
    public function prepareEgg(): void
    {
        if (($this->data['game'] ?? null) !== 'minecraft') {
            return;
        }
        $type = (string) $this->data['type'];
        $software = $this->data['software'] ?? null;
        $mayImport = Access::mayImportEggs();
        try {
            if ($type === 'modpack') {
                $key = (string) ($this->data['modpack'] ?? '');
                if (! array_key_exists((string) ($this->data['modpack_version'] ?? ''), Sources::versions($key))) {
                    throw ValidationException::withMessages(['data.modpack_version' => __('simplify-servermaker::maker.modpack.version_required')]);
                }
                $egg = Sources::egg($key, $mayImport);
            } else {
                $egg = MinecraftEggs::find($type, $software) ?? ($mayImport ? MinecraftEggs::ensure($type, $software) : null);
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['data.type' => __('simplify-servermaker::maker.minecraft.egg_failed')]);
        }
        if (! $egg) {
            throw ValidationException::withMessages(['data.type' => __('simplify-servermaker::maker.minecraft.egg_missing')]);
        }
        $this->data['egg_id'] = $egg->id;
    }

    /** The chosen egg. For people who are not administrators only an egg they may use, whatever the browser sends. */
    public function egg(): ?Egg
    {
        $id = $this->data['egg_id'] ?? null;
        if (! is_numeric($id)) {
            return null;
        }
        if ($this->isAdmin()) {
            return Egg::query()->find((int) $id);
        }
        if (($this->data['game'] ?? null) !== 'minecraft') {
            return OtherGames::find($id);
        }
        $egg = Egg::query()->find((int) $id);
        $bundled = array_map(fn (array $entry) => mb_strtolower((string) $entry['name']), BundledEggs::manifest());

        return $egg && Access::minecraft() && (Minecraft::isMinecraftEgg($egg->name) || in_array(mb_strtolower($egg->name), $bundled, true))
            ? $egg
            : null;
    }

    /** The Steam art of the chosen other game, when the setting is on and the store knows it. */
    public function steamArt(): ?array
    {
        $egg = ($this->data['game'] ?? null) === 'other' ? $this->egg() : null;

        return $egg ? SteamArt::find((string) $egg->name) : null;
    }

    /** The Java major version for a Minecraft Java server, else null. */
    public function java(): ?int
    {
        $type = (string) ($this->data['type'] ?? '');
        if (($this->data['game'] ?? null) !== 'minecraft' || ! Minecraft::isJava($type)) {
            return null;
        }
        // Review → Edit settings: an administrator may pick the Java version for packs with special needs.
        $chosen = (int) ($this->data['java'] ?? 0);
        if ($this->isAdmin() && in_array($chosen, Java::MAJORS, true)) {
            return $chosen;
        }
        if ($type === 'proxy') {
            return 21;
        }
        if ($type === 'modpack') {
            $runtime = Sources::runtime($this->data['modpack'] ?? null, $this->data['modpack_version'] ?? null);
            if (in_array($runtime['java'] ?? null, Java::MAJORS, true)) {
                return $runtime['java'];
            }

            return isset($runtime['minecraft']) ? MinecraftVersions::java($runtime['minecraft'], $type) : null;
        }
        $version = (string) ($this->data['version'] ?? '');

        return Minecraft::validVersion($version) ? MinecraftVersions::java($version, $type) : null;
    }

    public function randomName(): string
    {
        return (new RandomWordService)->word();
    }

    public function create(): void
    {
        abort_unless(static::canAccess(), 403);
        $state = $this->form->getState();
        $egg = $this->egg();
        if (! $egg) {
            throw ValidationException::withMessages(['data.game' => __('simplify-servermaker::maker.game.required')]);
        }

        $subdomain = strtolower(trim((string) ($state['subdomain'] ?? '')));
        if ($subdomain !== '' && ($reason = Subdomain::check($subdomain))) {
            throw ValidationException::withMessages(['data.subdomain' => $reason]);
        }
        $state['subdomain'] = $subdomain;

        $nodeId = (int) ($state['node_id'] ?? 0);
        $allocationId = (int) ($state['allocation_id'] ?? 0) ?: Ports::preferred($nodeId);
        if (! $allocationId) {
            throw ValidationException::withMessages(['data.allocation_id' => __('simplify-servermaker::maker.server.no_port_short')]);
        }

        $admin = $this->isAdmin();
        if (! $admin) {
            $state['backups'] = min(2, Access::limits()['max_backups']);
        }
        $first = FirstSettings::from([...$this->data, ...$state]);
        $payload = Payload::build(
            $state,
            EggData::from($egg),
            Resources::payload($state, Access::resourceLimits()),
            (int) user()->id,
            $nodeId,
            $allocationId,
            $this->java(),
            $admin,
            fn (array $environment) => Sources::environment(
                (string) ($state['modpack'] ?? ''),
                (string) ($state['modpack_version'] ?? ''),
                $environment,
            ),
        );

        try {
            $server = Creator::create($payload, $admin ? null : fn () => Access::check($payload, Access::remaining(user())));
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()
                ->title(__('simplify-servermaker::maker.review.failed'))
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Subdomain::request($server, (string) ($state['subdomain'] ?? ''));
        ApplyFirstSettings::afterInstall($server, $first);
        try {
            ServerColor::apply($server, $state['server_color'] ?? null);
            BackupSchedule::apply($server, $state);
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title(__('simplify-servermaker::maker.resources.backup_failed'))->warning()->send();
        }
        if (($state['steam_art'] ?? false) && ($art = $this->steamArt()) !== null) {
            ApplySteamArt::afterResponse($server, $art);
        }
        Draft::query()->where('user_id', user()->id)->delete();
        Notification::make()
            ->title(__('simplify-servermaker::maker.review.created'))
            ->body(__('simplify-servermaker::maker.review.created_body'))
            ->success()
            ->send();
        // Opened from Simplify, the Server Maker runs in its frame: the new server opens as the whole page, not inside it.
        $this->js('(u => { const top = window.frameElement?.dataset.psFrame !== undefined ? window.top : window; top.location.href = u; })('
            .json_encode(Console::getUrl(panel: 'server', tenant: $server), JSON_UNESCAPED_SLASHES).')');
    }
}
