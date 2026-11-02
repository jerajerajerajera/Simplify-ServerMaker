<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Filament\Server\Pages;

use App\Enums\SubuserPermission;
use App\Filament\Server\Pages\Console;
use App\Models\Egg;
use App\Models\Server;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Simplify\ServerMaker\Creation\EggData;
use Simplify\ServerMaker\Creation\Payload;
use Simplify\ServerMaker\Filament\Wizard\SetupStep;
use Simplify\ServerMaker\Games\Java;
use Simplify\ServerMaker\Games\Minecraft;
use Simplify\ServerMaker\Games\MinecraftEggs;
use Simplify\ServerMaker\Games\MinecraftVersions;
use Simplify\ServerMaker\Modpacks\Sources;
use Simplify\ServerMaker\Software\ChangeSoftware;
use Simplify\ServerMaker\Software\CurrentSoftware;
use Simplify\ServerMaker\Software\Startup;
use Throwable;

/**
 * Server software: other Minecraft Java software, version or modpack for an existing server, as in Simplify 0.7.0.
 * The same choices as the Server Maker's Minecraft step; the server is reinstalled, its worlds and files stay.
 *
 * @property Schema $form
 */
final class ServerSoftware extends Page implements HasForms
{
    use InteractsWithForms;

    /** The types a server can switch between; Bedrock and proxies have other files. */
    public const TYPES = ['vanilla', 'plugins', 'mods', 'modpack'];

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-packages';

    protected static ?int $navigationSort = 13;

    protected static ?string $slug = 'software';

    protected string $view = 'simplify-servermaker::pages.software';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** What the server runs now, as shown above the choices. */
    public string $now = '';

    /** The owner or an administrator, with the permission to reinstall. */
    public static function canAccess(): bool
    {
        $server = Filament::getTenant();
        $user = user();

        return $server instanceof Server
            && $user !== null
            && ($user->isRootAdmin() || (int) $server->owner_id === (int) $user->id)
            && $user->can(SubuserPermission::SettingsReinstall, $server)
            && CurrentSoftware::of((string) $server->egg?->name) !== null;
    }

    public static function getNavigationLabel(): string
    {
        return __('simplify-servermaker::maker.software.title');
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public function server(): Server
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $server;
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        $server = $this->server();
        $current = CurrentSoftware::of((string) $server->egg?->name);
        $version = CurrentSoftware::version(Startup::of($server)['environment']);
        $this->now = trim($server->egg?->name.' '.$version);
        $type = in_array($current['type'] ?? null, self::TYPES, true) ? $current['type'] : 'plugins';
        $this->form->fill([
            'type' => $type,
            'software' => Minecraft::software($type, $current['software'] ?? null),
            'version' => $version ?? MinecraftVersions::newest(),
            'confirm' => '',
        ]);
    }

    /** Called by the type cards (shared with the Server Maker). */
    public function pickGame(string $game, string $choice): void
    {
        abort_unless(self::canAccess() && $game === 'minecraft' && in_array($choice, self::TYPES, true), 403);
        $this->data['type'] = $choice;
        $this->data['software'] = Minecraft::software($choice, $this->data['software'] ?? null);
        $this->data['version'] ??= MinecraftVersions::newest();
    }

    public function form(Schema $schema): Schema
    {
        $t = fn (string $key, array $replace = []) => __('simplify-servermaker::maker.'.$key, $replace);

        return $schema->statePath('data')->components([
            Text::make($t('software.warning'))->color('warning'),
            Text::make(fn () => $t('software.now', ['software' => $this->now]))->color('gray'),
            View::make('simplify-servermaker::wizard.types')->viewData(['types' => self::TYPES]),
            ...SetupStep::softwareFields($t),
            TextInput::make('confirm')
                ->label($t('software.confirm', ['name' => $this->server()->name]))
                ->placeholder($this->server()->name)
                ->autocomplete('off')
                ->dehydrated(false)
                ->live(debounce: 300),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('install')
                ->label(__('simplify-servermaker::maker.software.install'))
                ->icon('tabler-download')
                ->disabled(fn () => ! hash_equals($this->server()->name, (string) ($this->data['confirm'] ?? '')))
                ->action(fn () => $this->install()),
        ];
    }

    public function install(): void
    {
        abort_unless(self::canAccess(), 403);
        $server = $this->server();
        if (! hash_equals($server->name, (string) ($this->data['confirm'] ?? ''))) {
            throw ValidationException::withMessages(['data.confirm' => __('simplify-servermaker::maker.software.wrong_name')]);
        }
        // The type comes from the cards, which are no form field.
        $data = [...$this->form->getState(), 'type' => $this->data['type'] ?? null];
        if (! in_array($data['type'] ?? null, self::TYPES, true)) {
            throw ValidationException::withMessages(['data.type' => __('simplify-servermaker::maker.minecraft.egg_missing')]);
        }
        $blocked = ChangeSoftware::blocked($server);
        if ($blocked !== null) {
            Notification::make()->title(__('simplify-servermaker::maker.software.blocked.'.$blocked))->warning()->send();

            return;
        }
        $startup = self::startup(MinecraftEggs::forChoice($data), $data);
        try {
            ChangeSoftware::apply($server, $startup);
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title(__('simplify-servermaker::maker.software.failed'))->danger()->send();

            return;
        }
        Notification::make()->title(__('simplify-servermaker::maker.software.installing'))->success()->send();
        $this->redirect(Console::getUrl(tenant: $server), navigate: true);
    }

    /**
     * The egg's own defaults with the chosen version or pack, its startup command and the image for the Java it needs.
     *
     * @param  array<string, mixed>  $data
     * @return array{egg_id: int, image: string, startup: string, environment: array<string, string>}
     */
    private static function startup(Egg $egg, array $data): array
    {
        $egg = EggData::from($egg);
        $java = MinecraftVersions::javaFor($data);
        $environment = Payload::minecraftEnvironment($data, $egg, $java);
        if ($data['type'] === 'modpack') {
            $environment = Sources::environment((string) $data['modpack'], (string) $data['modpack_version'], $environment);
        }
        $image = $java !== null ? Java::image($egg->images, $java) : $egg->firstImage();
        if ($image === null || $image === '') {
            throw ValidationException::withMessages(['data.type' => __('simplify-servermaker::maker.review.no_image')]);
        }

        return ['egg_id' => $egg->id, 'image' => $image, 'startup' => $egg->startup, 'environment' => $environment];
    }
}
