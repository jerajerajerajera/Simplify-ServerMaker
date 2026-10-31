<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Filament\Wizard;

use Closure;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Simplify\ServerMaker\Creation\FirstSettings;
use Simplify\ServerMaker\Filament\Admin\Pages\ServerMaker;
use Simplify\ServerMaker\Games\Minecraft;
use Simplify\ServerMaker\Games\MinecraftVersions;
use Simplify\ServerMaker\Modpacks\Sources;
use Simplify\Toolkit\Filament\Components\MotdEditor;

/** Step 2: the software and version for Minecraft, or the game's own settings for every other game. */
final class SetupStep
{
    public static function make(ServerMaker $page): Step
    {
        $t = fn (string $key) => __('simplify-servermaker::maker.'.$key);

        return Step::make('setup')
            ->label(fn (Get $get) => $get('game') === 'other' ? $t('steps.settings') : $t('steps.minecraft'))
            ->schema([
                Group::make()
                    ->visible(fn (Get $get) => $get('game') === 'minecraft')
                    ->schema([
                        View::make('simplify-servermaker::wizard.types')
                            ->viewData(['types' => Minecraft::TYPES]),
                        Radio::make('software')
                            ->label(fn (Get $get) => $t('minecraft.software.'.$get('type')))
                            ->helperText(fn (Get $get) => $t('minecraft.software_help.'.$get('type')))
                            ->view('simplify-servermaker::wizard.chips')
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
                        TextInput::make('modpack_search')
                            ->label($t('modpack.search'))
                            ->placeholder($t('modpack.search_placeholder'))
                            ->prefixIcon('tabler-search')
                            ->dehydrated(false)
                            ->live(debounce: 600)
                            ->visible(fn (Get $get) => $get('type') === 'modpack'),
                        Radio::make('modpack')
                            ->hiddenLabel()
                            ->view('simplify-servermaker::wizard.modpacks')
                            ->options(fn (Get $get) => array_column(
                                Sources::results((string) $get('modpack_search'), $get('modpack')),
                                'title',
                                'key',
                            ))
                            ->visible(fn (Get $get) => $get('type') === 'modpack')
                            ->required(fn (Get $get) => $get('type') === 'modpack')
                            ->validationMessages(['required' => $t('modpack.required')])
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('modpack_version', null)),
                        Select::make('modpack_version')
                            ->label($t('modpack.version'))
                            ->helperText($t('modpack.version_help'))
                            ->options(fn (Get $get) => Sources::versions($get('modpack')))
                            ->searchable()
                            ->native(false)
                            ->visible(fn (Get $get) => $get('type') === 'modpack' && filled($get('modpack')))
                            ->required(fn (Get $get) => $get('type') === 'modpack'),
                        Text::make($t('modpack.curseforge_off'))
                            ->visible(fn (Get $get) => $get('type') === 'modpack' && ! Sources::get('curseforge')),
                        Text::make($t('minecraft.bedrock_help'))
                            ->visible(fn (Get $get) => $get('type') === 'bedrock'),
                        Text::make($t('minecraft.proxy_help'))
                            ->visible(fn (Get $get) => $get('type') === 'proxy'),
                        // Written to server.properties once the installation is done.
                        self::motd($t)
                            ->visible(fn (Get $get) => FirstSettings::hasIcon($get('game'), $get('type'))),
                        Grid::make(['default' => 1, 'md' => 3])
                            ->visible(fn (Get $get) => FirstSettings::hasProperties($get('game'), $get('type')))
                            ->schema([
                                TextInput::make('max_players')
                                    ->label($t('first.max_players'))
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(1000)
                                    ->required(fn (Get $get) => FirstSettings::hasProperties($get('game'), $get('type'))),
                                Select::make('gamemode')
                                    ->label($t('first.gamemode'))
                                    ->options(array_combine(FirstSettings::GAMEMODES, array_map(fn ($mode) => $t('first.gamemodes.'.$mode), FirstSettings::GAMEMODES)))
                                    ->native(false)
                                    ->selectablePlaceholder(false)
                                    ->required(fn (Get $get) => FirstSettings::hasProperties($get('game'), $get('type'))),
                                Select::make('difficulty')
                                    ->label($t('first.difficulty'))
                                    ->options(array_combine(FirstSettings::DIFFICULTIES, array_map(fn ($level) => $t('first.difficulties.'.$level), FirstSettings::DIFFICULTIES)))
                                    ->native(false)
                                    ->selectablePlaceholder(false)
                                    ->required(fn (Get $get) => FirstSettings::hasProperties($get('game'), $get('type'))),
                            ]),
                        Grid::make(['default' => 1, 'md' => 2])
                            ->visible(fn (Get $get) => FirstSettings::hasIcon($get('game'), $get('type')))
                            ->schema(array_map(
                                fn (string $field) => TextInput::make($field)
                                    ->label($t('first.'.$field))
                                    ->integer()
                                    ->minValue(FirstSettings::NUMBERS[$field][1])
                                    ->maxValue(FirstSettings::NUMBERS[$field][2])
                                    ->required(fn (Get $get) => FirstSettings::hasIcon($get('game'), $get('type'))),
                                array_keys(FirstSettings::NUMBERS),
                            )),
                        Group::make(array_map(
                            fn (string $field) => Toggle::make($field)
                                ->label($t('first.'.$field))
                                ->helperText($t('first.'.$field.'_help'))
                                ->inline(),
                            array_keys(FirstSettings::SWITCHES),
                        ))
                            ->columns(['default' => 1, 'md' => 3])
                            ->visible(fn (Get $get) => FirstSettings::hasIcon($get('game'), $get('type'))),
                        FileUpload::make('server_icon')
                            ->label($t('first.icon'))
                            ->helperText($t('first.icon_help'))
                            ->visible(fn (Get $get) => FirstSettings::hasIcon($get('game'), $get('type')))
                            ->image()
                            ->acceptedFileTypes(['image/png'])
                            ->maxSize(100)
                            ->imageResizeMode('cover')
                            ->imageResizeTargetWidth('64')
                            ->imageResizeTargetHeight('64')
                            ->storeFiles(false),
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
            ->afterValidation(function () use ($page): void {
                $page->prepareEgg();
                $page->stepDone(2);
            });
    }

    /** The Toolkit's MOTD maker (as on the server page) when it is installed, otherwise a plain text field. */
    private static function motd(Closure $t): Field
    {
        $field = class_exists(MotdEditor::class)
            ? MotdEditor::make('motd')
            : TextInput::make('motd')->maxLength(FirstSettings::MOTD_MAX);

        return $field
            ->label($t('first.motd'))
            ->helperText($t('first.motd_help'))
            ->rules(['string', 'max:'.FirstSettings::MOTD_MAX])
            ->required(fn (Get $get) => FirstSettings::hasIcon($get('game'), $get('type')));
    }
}
