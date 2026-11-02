<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Filament\Server\Pages;

use App\Models\Server;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Simplify\ServerMaker\Access;
use Simplify\ServerMaker\Creation\ChangeResources;
use Simplify\ServerMaker\Creation\ExtraPorts;
use Simplify\ServerMaker\Creation\Resources;
use Simplify\ServerMaker\Creation\StartingResources;
use Simplify\ServerMaker\Filament\Wizard\ResourcesStep;

/**
 * Performance: the server's CPU, RAM and disk limits, and new ones with the same choices as the Server Maker, as in
 * Simplify 0.7.0. Administrators freely; owners with the Server Maker's self-service within their allowance.
 *
 * @property Schema $form
 */
final class Performance extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-cpu';

    protected static ?int $navigationSort = 14;

    protected static ?string $slug = 'performance';

    protected string $view = 'simplify-servermaker::pages.performance';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Filament::getTenant() instanceof Server && user() !== null;
    }

    /** Administrators, and the owner when they may make servers themselves. */
    public static function canChange(): bool
    {
        $server = Filament::getTenant();

        return $server instanceof Server
            && (Access::admin() || ((int) $server->owner_id === (int) user()?->id && Access::selfService()));
    }

    public static function getNavigationLabel(): string
    {
        return __('simplify-servermaker::maker.performance.title');
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

    /** @return array{ram: int, cpu: int, disk: int} the current limits; unlimited (0) starts at the usual amount */
    private function current(): array
    {
        $server = $this->server();

        return [
            'ram' => (int) $server->memory > 0 ? max(1, intdiv((int) $server->memory, 1024)) : StartingResources::OTHER['ram'],
            'cpu' => (int) $server->cpu > 0 ? (int) $server->cpu : StartingResources::OTHER['cpu'],
            'disk' => (int) $server->disk > 0 ? max(1024, (int) $server->disk) : StartingResources::OTHER['disk'],
        ];
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        $state = [];
        foreach ($this->current() as $resource => $amount) {
            $state[$resource] = Resources::choice($amount, Resources::presets($resource, $amount));
            $state[$resource.'_custom'] = $amount;
        }
        $this->form->fill([...$state, 'extra_ports' => ExtraPorts::count($this->server())]);
    }

    public function form(Schema $schema): Schema
    {
        $t = fn (string $key, array $replace = []) => __('simplify-servermaker::maker.'.$key, $replace);
        $limits = fn () => Access::resourceLimits($this->server());

        return $schema->statePath('data')->components([
            View::make('simplify-servermaker::wizard.limits')->viewData(['server' => $this->server()]),
            Group::make(ResourcesStep::amountFields(
                fn (Get $get, string $resource): int => $this->current()[$resource],
                fn (string $resource): ?int => $limits()[$resource] ?? null,
                $t,
            ))->visible(fn () => self::canChange()),
            Text::make(fn () => trim(ResourcesStep::left($limits(), $t).' '.$t('performance.restart')))
                ->color('gray')
                ->visible(fn () => self::canChange()),
            View::make('simplify-servermaker::wizard.ports')->viewData(['ports' => fn () => ExtraPorts::of($this->server())]),
            TextInput::make('extra_ports')
                ->label($t('performance.extra_ports'))
                ->helperText(fn () => $t('performance.ports_help', ['count' => self::maxPorts()]))
                ->integer()
                ->minValue(0)
                ->maxValue(fn () => self::maxPorts())
                ->required()
                ->visible(fn () => self::canChange()),
            Text::make($t('performance.ask_admin'))->color('gray')->visible(fn () => ! self::canChange()),
        ]);
    }

    /** Extra ports this server may have: the setting for owners, the plain maximum for administrators. */
    private static function maxPorts(): int
    {
        return Access::admin() ? ExtraPorts::MAX : min(ExtraPorts::MAX, Access::limits()['max_ports']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(__('simplify-servermaker::maker.settings.save'))
                ->icon('tabler-device-floppy')
                ->visible(fn () => self::canChange())
                ->action(fn () => $this->save())
                ->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        abort_unless(self::canChange(), 403);
        $server = $this->server();
        $state = $this->form->getState();
        $check = fn () => Resources::payload($state, Access::resourceLimits($server));
        ChangeResources::apply($server, $check(), Access::admin() ? null : $check);
        $ports = (int) $state['extra_ports'];
        if ($ports !== ExtraPorts::count($server)) {
            abort_unless($ports >= 0 && $ports <= self::maxPorts(), 422);
            ExtraPorts::set($server, $ports);
        }
        Notification::make()->title(__('simplify-servermaker::maker.performance.saved'))->success()->send();
    }
}
