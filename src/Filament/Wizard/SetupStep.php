<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Filament\Wizard;

use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Simplify\ServerMaker\Filament\Admin\Pages\ServerMaker;
use Simplify\ServerMaker\Games\Minecraft;
use Simplify\ServerMaker\Games\MinecraftVersions;

/** Step 2: the software and version for Minecraft, or the game's own settings for every other game. */
final class SetupStep
{
    public static function make(ServerMaker $page): Step
    {
        $t = fn (string $key) => __('simplify-server-maker::maker.'.$key);

        return Step::make('setup')
            ->label(fn (Get $get) => $get('game') === 'other' ? $t('steps.settings') : $t('steps.minecraft'))
            ->schema([
                Group::make()
                    ->visible(fn (Get $get) => $get('game') === 'minecraft')
                    ->schema([
                        Radio::make('software')
                            ->label(fn (Get $get) => $t('minecraft.software.'.$get('type')))
                            ->helperText(fn (Get $get) => $t('minecraft.software_help.'.$get('type')))
                            ->view('simplify-server-maker::wizard.chips')
                            ->options(fn (Get $get) => Minecraft::SOFTWARE[$get('type')] ?? [])
                            ->visible(fn (Get $get) => isset(Minecraft::SOFTWARE[$get('type')]))
                            ->required(fn (Get $get) => isset(Minecraft::SOFTWARE[$get('type')]))
                            ->in(fn (Get $get) => array_keys(Minecraft::SOFTWARE[$get('type')] ?? []))
                            ->live(),
                        Select::make('version')
                            ->label($t('minecraft.version'))
                            ->helperText(fn (Get $get) => $get('type') === 'mods' ? $t('minecraft.version_help_mods') : $t('minecraft.version_help'))
                            ->options(fn () => MinecraftVersions::releases())
                            ->default(fn () => MinecraftVersions::newest())
                            ->searchable()
                            ->native(false)
                            ->visible(fn (Get $get) => Minecraft::usesVersion((string) $get('type')))
                            ->required(fn (Get $get) => Minecraft::usesVersion((string) $get('type')))
                            ->live(),
                        Text::make($t('minecraft.bedrock_help'))
                            ->visible(fn (Get $get) => $get('type') === 'bedrock'),
                        Text::make($t('minecraft.proxy_help'))
                            ->visible(fn (Get $get) => $get('type') === 'proxy'),
                    ]),
                Section::make($t('steps.settings'))
                    ->description(fn () => $page->egg()?->description ?: null)
                    ->visible(fn (Get $get) => $get('game') === 'other')
                    ->schema(function () use ($page, $t): array {
                        $egg = $page->egg();
                        if (! $egg) {
                            return [];
                        }
                        [$main, $advanced] = GameVariables::fields($egg, $page->isAdmin());

                        return [
                            Grid::make(['default' => 1, 'md' => 2])->schema($main),
                            ...($advanced === [] ? [] : [
                                Section::make($t('game.advanced'))
                                    ->description($t('game.advanced_help'))
                                    ->collapsible()
                                    ->collapsed()
                                    ->schema([Grid::make(['default' => 1, 'md' => 2])->schema($advanced)]),
                            ]),
                        ];
                    }),
            ])
            ->afterValidation(fn () => $page->prepareEgg());
    }
}
