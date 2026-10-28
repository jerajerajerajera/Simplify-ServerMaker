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
use Simplify\ServerMaker\Games\Minecraft;
use Simplify\ServerMaker\Games\MinecraftEggs;
use Simplify\ServerMaker\Games\MinecraftVersions;
use Simplify\ServerMaker\Games\OtherGames;
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
        $this->form->fill([
            'game' => null,
            'type' => null,
            'software' => null,
            'version' => null,
            'egg_id' => null,
            'variables' => [],
            'name' => '',
            'node_id' => $node,
            'allocation_id' => Ports::first($node),
            'backups' => 2,
            'skip_scripts' => false,
            'image' => '',
            'startup' => '',
            'environment' => [],
            ...$this->resourceDefaults('vanilla'),
        ]);
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

    /** Leaving the setup step: the egg for the chosen Minecraft type, added from the bundled eggs when missing. */
    public function prepareEgg(): void
    {
        if (($this->data['game'] ?? null) !== 'minecraft' || ($this->data['type'] ?? null) === 'modpack') {
            return;
        }
        $type = (string) $this->data['type'];
        $software = $this->data['software'] ?? null;
        $egg = MinecraftEggs::find($type, $software);
        if (! $egg && ! user()?->can('create', Egg::class)) {
            throw ValidationException::withMessages(['data.type' => __('simplify-server-maker::maker.minecraft.egg_missing')]);
        }
        try {
            $egg ??= MinecraftEggs::ensure($type, $software);
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['data.type' => __('simplify-server-maker::maker.minecraft.egg_failed')]);
        }
        $this->data['egg_id'] = $egg?->id;
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

        Notification::make()
            ->title(__('simplify-server-maker::maker.review.created'))
            ->body(__('simplify-server-maker::maker.review.created_body'))
            ->success()
            ->send();
        $this->redirect(Console::getUrl(panel: 'server', tenant: $server));
    }
}
