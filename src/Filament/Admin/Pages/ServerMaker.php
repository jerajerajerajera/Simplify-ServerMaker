<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Filament\Admin\Pages;

use App\Filament\Server\Pages\Console;
use App\Models\Egg;
use App\Models\Server;
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
use Simplify\ServerMaker\Creation\Creator;
use Simplify\ServerMaker\Creation\EggData;
use Simplify\ServerMaker\Creation\Payload;
use Simplify\ServerMaker\Creation\Ports;
use Simplify\ServerMaker\Creation\Resources;
use Simplify\ServerMaker\Filament\Wizard\GameStep;
use Simplify\ServerMaker\Filament\Wizard\GameVariables;
use Simplify\ServerMaker\Filament\Wizard\ResourcesStep;
use Simplify\ServerMaker\Filament\Wizard\ReviewStep;
use Simplify\ServerMaker\Filament\Wizard\ServerStep;
use Simplify\ServerMaker\Filament\Wizard\SetupStep;
use Simplify\ServerMaker\Games\Java;
use Simplify\ServerMaker\Games\Minecraft;
use Simplify\ServerMaker\Games\MinecraftEggs;
use Simplify\ServerMaker\Games\MinecraftVersions;
use Simplify\ServerMaker\Games\OtherGames;
use Simplify\ServerMaker\Models\Draft;
use Simplify\ServerMaker\Modpacks\Sources;
use Throwable;

/** The Server Maker: a wizard that creates a game server in a few steps. */
class ServerMaker extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'server-maker';

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-wand';

    protected static ?int $navigationSort = 2;

    protected string $view = 'simplify-server-maker::pages.server-maker';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** The step to open on: 1, or where a saved draft left off. */
    #[Locked]
    public int $step = 1;

    #[Locked]
    public bool $restored = false;

    public static function getNavigationLabel(): string
    {
        return __('simplify-server-maker::maker.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return trans('admin/dashboard.server');
    }

    public function getTitle(): string
    {
        return __('simplify-server-maker::maker.title');
    }

    public static function canAccess(): bool
    {
        return (bool) user()?->can('create', Server::class);
    }

    /** Whether the person may change everything: the egg, the image, every variable. */
    public function isAdmin(): bool
    {
        return (bool) user()?->can('create', Server::class);
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
            'variables' => [],
            'modpack' => null,
            'modpack_version' => null,
            'name' => '',
            'node_id' => $node,
            'allocation_id' => Ports::first($node),
            'backups' => 2,
            'skip_scripts' => false,
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
                Notification::make()->title(__('simplify-server-maker::maker.draft.restored'))->info()->send();
            } catch (Throwable $exception) {
                // A draft that cannot be read (for example after a new APP_KEY) is dropped.
                report($exception);
                $draft->delete();
            }
        }
        $this->form->fill($fresh);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('start_over')
                ->label(__('simplify-server-maker::maker.draft.start_over'))
                ->icon('tabler-restore')
                ->color('gray')
                ->visible(fn () => $this->restored)
                ->requiresConfirmation()
                ->modalDescription(__('simplify-server-maker::maker.draft.start_over_help'))
                ->action(function (): void {
                    Draft::query()->where('user_id', user()->id)->delete();
                    $this->redirect(static::getUrl());
                }),
        ];
    }

    /** Saves the answers so far after each step, so a closed tab or a lost connection costs nothing. */
    public function stepDone(int $step): void
    {
        abort_unless(static::canAccess(), 403);
        Draft::query()->updateOrCreate(
            ['user_id' => (int) user()->id],
            ['data' => Draft::clean($this->data ?? []), 'step' => min(5, $step + 1)],
        );
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
                    ->nextAction(fn (Action $action) => $action->label(__('simplify-server-maker::maker.next')))
                    ->previousAction(fn (Action $action) => $action->label(__('simplify-server-maker::maker.back')))
                    ->submitAction(new HtmlString(view('simplify-server-maker::wizard.create-button')->render())),
            ]);
    }

    /** The Game step: Minecraft with a server type, or another game by its egg. */
    public function pickGame(string $game, string $choice): void
    {
        abort_unless(static::canAccess(), 403);
        if ($game === 'minecraft' && Minecraft::isType($choice)) {
            $software = Minecraft::software($choice, $this->data['software'] ?? null);
            $this->data = array_replace($this->data, [
                'game' => 'minecraft',
                'type' => $choice,
                'software' => $software,
                'version' => $this->data['version'] ?? MinecraftVersions::newest(),
                'egg_id' => null,
                'variables' => [],
                'modpack' => $choice === 'modpack' ? ($this->data['modpack'] ?? null) : null,
                'modpack_version' => $choice === 'modpack' ? ($this->data['modpack_version'] ?? null) : null,
                ...$this->resourceDefaults($choice),
            ]);

            return;
        }
        $egg = $game === 'other' ? OtherGames::find($choice) : null;
        if (! $egg) {
            throw ValidationException::withMessages(['data.game' => __('simplify-server-maker::maker.game.required')]);
        }
        $this->data = array_replace($this->data, [
            'game' => 'other',
            'type' => null,
            'software' => null,
            'egg_id' => $egg->id,
            'variables' => GameVariables::defaults($egg, $this->isAdmin()),
            ...$this->resourceDefaults(null),
        ]);
    }

    /** @return array<string, int|string> the resource fields for a Minecraft type, or for other games */
    private function resourceDefaults(?string $type): array
    {
        $start = $type === null ? ['cpu' => 100, 'ram' => 4, 'disk' => 10240] : Minecraft::resources($type);
        $values = [];
        foreach ($start as $resource => $amount) {
            $values[$resource] = Resources::choice($amount, Resources::presets($resource, $amount));
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
        $mayImport = (bool) user()?->can('create', Egg::class);
        try {
            if ($type === 'modpack') {
                $key = (string) ($this->data['modpack'] ?? '');
                if (! array_key_exists((string) ($this->data['modpack_version'] ?? ''), Sources::versions($key))) {
                    throw ValidationException::withMessages(['data.modpack_version' => __('simplify-server-maker::maker.modpack.version_required')]);
                }
                $egg = Sources::egg($key, $mayImport);
            } else {
                $egg = MinecraftEggs::find($type, $software) ?? ($mayImport ? MinecraftEggs::ensure($type, $software) : null);
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['data.type' => __('simplify-server-maker::maker.minecraft.egg_failed')]);
        }
        if (! $egg) {
            throw ValidationException::withMessages(['data.type' => __('simplify-server-maker::maker.minecraft.egg_missing')]);
        }
        $this->data['egg_id'] = $egg->id;
    }

    public function egg(): ?Egg
    {
        $id = $this->data['egg_id'] ?? null;

        return is_numeric($id) ? Egg::query()->find((int) $id) : null;
    }

    /** The Java major version for a Minecraft Java server, else null. */
    public function java(): ?int
    {
        $type = (string) ($this->data['type'] ?? '');
        if (($this->data['game'] ?? null) !== 'minecraft' || ! Minecraft::isJava($type)) {
            return null;
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
            throw ValidationException::withMessages(['data.game' => __('simplify-server-maker::maker.game.required')]);
        }

        $nodeId = (int) ($state['node_id'] ?? 0);
        $allocationId = (int) ($state['allocation_id'] ?? 0) ?: Ports::first($nodeId);
        if (! $allocationId) {
            throw ValidationException::withMessages(['data.allocation_id' => __('simplify-server-maker::maker.server.no_port_short')]);
        }

        $payload = Payload::build(
            $state,
            EggData::from($egg),
            Resources::payload($state),
            (int) user()->id,
            $nodeId,
            $allocationId,
            $this->java(),
            $this->isAdmin(),
            fn (array $environment) => Sources::environment(
                (string) ($state['modpack'] ?? ''),
                (string) ($state['modpack_version'] ?? ''),
                $environment,
            ),
        );

        try {
            $server = Creator::create($payload);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()
                ->title(__('simplify-server-maker::maker.review.failed'))
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Draft::query()->where('user_id', user()->id)->delete();
        Notification::make()
            ->title(__('simplify-server-maker::maker.review.created'))
            ->body(__('simplify-server-maker::maker.review.created_body'))
            ->success()
            ->send();
        $this->redirect(Console::getUrl(panel: 'server', tenant: $server));
    }
}
