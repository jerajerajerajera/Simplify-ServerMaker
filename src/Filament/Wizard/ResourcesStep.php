<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Filament\Wizard;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Radio;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Simplify\ServerMaker\Access;
use Simplify\ServerMaker\Creation\Resources;
use Simplify\ServerMaker\Filament\Admin\Pages\ServerMaker;
use Simplify\ServerMaker\Games\Minecraft;

/** Step 4: RAM, CPU and disk, as presets that start at what the chosen game needs, or an amount of your own. */
final class ResourcesStep
{
    public static function make(ServerMaker $page): Step
    {
        $t = fn (string $key, array $replace = []) => __('simplify-servermaker::maker.'.$key, $replace);
        $start = fn (Get $get, string $resource): int => $get('game') === 'minecraft'
            ? Minecraft::resources((string) $get('type'))[$resource]
            : ['cpu' => 100, 'ram' => 4, 'disk' => 10240][$resource];

        // The help sits above the fields: no box in the box (Simplify's style rules).
        $fields = [Text::make(fn (Get $get) => self::help($get, $t))->color('gray')];
        foreach (['ram', 'cpu', 'disk'] as $resource) {
            $fields[] = Radio::make($resource)
                ->label($t('resources.'.$resource))
                ->view('simplify-servermaker::wizard.resources')
                ->viewData(['resource' => $resource])
                ->options(function (Get $get) use ($resource, $start, $t): array {
                    $limit = Access::resourceLimits()[$resource] ?? null;
                    $options = [];
                    foreach (Resources::presets($resource, $start($get, $resource), $limit) as $value) {
                        $options[(string) $value] = self::label($resource, $value, $t);
                    }

                    return $options + ['custom' => $t('resources.custom')];
                })
                ->required()
                ->live();
            $fields[] = TextInput::make($resource.'_custom')
                ->hiddenLabel()
                ->integer()
                ->minValue($resource === 'disk' ? 1024 : 1)
                ->maxValue(fn () => Access::resourceLimits()[$resource] ?? Resources::MAX[$resource])
                ->suffix($t('resources.unit.'.$resource))
                ->helperText($t('resources.custom_help.'.$resource))
                ->visible(fn (Get $get) => $get($resource) === 'custom')
                ->required(fn (Get $get) => $get($resource) === 'custom');
        }

        return Step::make('resources')
            ->label($t('steps.resources'))
            ->schema($fields)
            ->afterValidation(function () use ($page): void {
                Resources::payload($page->data, Access::resourceLimits());
                $page->stepDone(4);
            });
    }

    /** What the step is about, plus what is left of the allowance for people who are not administrators. */
    private static function help(Get $get, callable $t): string
    {
        $help = $get('game') === 'minecraft' ? $t('resources.help_type') : $t('resources.help_game');
        $limits = Access::resourceLimits();
        if ($limits === null) {
            return $help;
        }

        return $help.' '.$t('access.left', [
            'ram' => $limits['ram'],
            'disk' => Resources::gb($limits['disk']),
            'cpu' => rtrim(rtrim(number_format($limits['cpu'] / 100, 1, '.', ''), '0'), '.'),
        ]);
    }

    private static function label(string $resource, int $value, callable $t): string
    {
        return match ($resource) {
            'ram' => $t('resources.gb', ['amount' => $value]),
            'cpu' => $value === 100
                ? $t('resources.one_core')
                : $t('resources.cores', ['amount' => rtrim(rtrim(number_format($value / 100, 1, '.', ''), '0'), '.')]),
            default => $t('resources.gb', ['amount' => Resources::gb($value)]),
        };
    }
}
