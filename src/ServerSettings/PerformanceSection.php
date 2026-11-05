<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\ServerSettings;

use App\Models\Server;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Simplify\ServerMaker\Access;
use Simplify\ServerMaker\Creation\ChangeResources;
use Simplify\ServerMaker\Creation\ExtraPorts;
use Simplify\ServerMaker\Creation\Resources;
use Simplify\ServerMaker\Creation\StartingResources;
use Simplify\ServerMaker\Filament\Wizard\ResourcesStep;

/**
 * Performance: the server's CPU, RAM and disk limits, and new ones with the same choices as the Server Maker, as in
 * Simplify 0.7.0, plus its extra ports. Administrators freely; owners with the Server Maker's self-service within
 * their allowance; everyone else sees the limits only. One section, on the Performance page or in Simplify Toolkit's
 * server settings.
 */
final class PerformanceSection
{
    private const FIELDS = ['ram', 'ram_custom', 'cpu', 'cpu_custom', 'disk', 'disk_custom', 'extra_ports'];

    /** Administrators, and the owner when they may make servers themselves. */
    public static function canChange(Server $server): bool
    {
        return Access::admin() || ((int) $server->owner_id === (int) user()?->id && Access::selfService());
    }

    /** Extra ports this server may have: the setting for owners, the plain maximum for administrators. */
    public static function maxPorts(): int
    {
        return Access::admin() ? ExtraPorts::MAX : min(ExtraPorts::MAX, Access::limits()['max_ports']);
    }

    /** @return array{ram: int, cpu: int, disk: int} the current limits; unlimited (0) starts at the usual amount */
    private static function current(Server $server): array
    {
        return [
            'ram' => (int) $server->memory > 0 ? max(1, intdiv((int) $server->memory, 1024)) : StartingResources::OTHER['ram'],
            'cpu' => (int) $server->cpu > 0 ? (int) $server->cpu : StartingResources::OTHER['cpu'],
            'disk' => (int) $server->disk > 0 ? max(1024, (int) $server->disk) : StartingResources::OTHER['disk'],
        ];
    }

    public static function make(Server $server): Section
    {
        $t = fn (string $key, array $replace = []) => __('simplify-servermaker::maker.'.$key, $replace);
        $limits = fn () => Access::resourceLimits($server);
        $canChange = fn () => self::canChange($server);

        // The head: when new limits apply, as the subtitle.
        return Section::make($t('performance.title'))
            ->description($t('performance.restart'))
            ->icon('tabler-cpu')
            ->schema([
                View::make('simplify-servermaker::wizard.styles'),
                Group::make([
                    View::make('simplify-servermaker::wizard.limits')->viewData(['server' => $server]),
                    Group::make(ResourcesStep::amountFields(
                        fn (Get $get, string $resource): int => self::current($server)[$resource],
                        fn (string $resource): ?int => $limits()[$resource] ?? null,
                        $t,
                    ))->visible($canChange),
                    Text::make(fn () => ResourcesStep::left($limits(), $t))
                        ->color('gray')
                        ->visible(fn () => $canChange() && ResourcesStep::left($limits(), $t) !== ''),
                    View::make('simplify-servermaker::wizard.ports')->viewData(['ports' => fn () => ExtraPorts::of($server)]),
                    TextInput::make('extra_ports')
                        ->label($t('performance.extra_ports'))
                        ->helperText(fn () => $t('performance.ports_help', ['count' => self::maxPorts()]))
                        ->integer()
                        ->minValue(0)
                        ->maxValue(fn () => self::maxPorts())
                        ->required()
                        ->visible($canChange),
                    Text::make($t('performance.ask_admin'))->color('gray')->visible(fn () => ! $canChange()),
                ])->extraAttributes(['class' => 'smk']),
            ]);
    }

    /** @return array<string, mixed> */
    public static function fill(Server $server): array
    {
        $state = [];
        foreach (self::current($server) as $resource => $amount) {
            $state[$resource] = Resources::choice($amount, Resources::presets($resource, $amount));
            $state[$resource.'_custom'] = $amount;
        }

        return [...$state, 'extra_ports' => ExtraPorts::count($server)];
    }

    /** @param  array<string, mixed>  $state */
    public static function changed(Server $server, array $state): bool
    {
        $fill = self::fill($server);
        foreach (self::FIELDS as $key) {
            if (array_key_exists($key, $state) && (string) $state[$key] !== (string) $fill[$key]) {
                return true;
            }
        }

        return false;
    }

    /**
     * The new limits (checked against the allowance again when they are applied) and extra ports. Errors are on
     * data.<field>.
     *
     * @param  array<string, mixed>  $state
     */
    public static function save(Server $server, array $state): void
    {
        abort_unless(self::canChange($server), 403);
        $check = function () use ($state, $server): array {
            return Resources::payload($state, Access::resourceLimits($server));
        };
        $recheck = function () use ($check): void {
            $check();
        };
        ChangeResources::apply($server, $check(), Access::admin() ? null : $recheck);
        $ports = (int) $state['extra_ports'];
        if ($ports !== ExtraPorts::count($server)) {
            abort_unless($ports >= 0 && $ports <= self::maxPorts(), 422);
            ExtraPorts::set($server, $ports);
        }
    }
}
