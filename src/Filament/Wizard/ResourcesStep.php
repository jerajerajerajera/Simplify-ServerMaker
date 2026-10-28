<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Filament\Wizard;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Simplify\ServerMaker\Creation\Resources;
use Simplify\ServerMaker\Filament\Admin\Pages\ServerMaker;
use Simplify\ServerMaker\Games\Minecraft;

/** Step 4: RAM, CPU and disk, as presets that start at what the chosen game needs, or an amount of your own. */
final class ResourcesStep
{
    public static function make(ServerMaker $page): Step
    {
        $t = fn (string $key, array $replace = []) => __('simplify-server-maker::maker.'.$key, $replace);
        $start = fn (Get $get, string $resource): int => $get('game') === 'minecraft'
            ? Minecraft::resources((string) $get('type'))[$resource]
            : ['cpu' => 100, 'ram' => 4, 'disk' => 10240][$resource];

        $fields = [];
        foreach (['ram', 'cpu', 'disk'] as $resource) {
            $fields[] = ToggleButtons::make($resource)
                ->label($t('resources.'.$resource))
                ->options(function (Get $get) use ($resource, $start, $t): array {
                    $options = [];
                    foreach (Resources::presets($resource, $start($get, $resource)) as $value) {
                        $options[(string) $value] = self::label($resource, $value, $t);
                    }

                    return $options + ['custom' => $t('resources.custom')];
                })
                ->inline()
                ->required()
                ->live();
            $fields[] = TextInput::make($resource.'_custom')
                ->hiddenLabel()
                ->integer()
                ->minValue($resource === 'disk' ? 1024 : 1)
                ->maxValue(Resources::MAX[$resource])
                ->suffix($t('resources.unit.'.$resource))
                ->helperText($t('resources.custom_help.'.$resource))
                ->visible(fn (Get $get) => $get($resource) === 'custom')
                ->required(fn (Get $get) => $get($resource) === 'custom');
        }

        return Step::make('resources')
            ->label($t('steps.resources'))
            ->description(fn (Get $get) => $get('game') === 'minecraft' ? $t('resources.help_type') : $t('resources.help_game'))
            ->schema($fields)
            ->afterValidation(function () use ($page): void {
                Resources::payload($page->data);
                $page->stepDone(4);
            });
    }

    private static function label(string $resource, int $value, callable $t): string
    {
        return match ($resource) {
            'ram' => $t('resources.gb', ['amount' => $value]),
            'cpu' => $t('resources.cores', ['amount' => rtrim(rtrim(number_format($value / 100, 1, '.', ''), '0'), '.')]),
            default => $t('resources.gb', ['amount' => Resources::gb($value)]),
        };
    }
}
