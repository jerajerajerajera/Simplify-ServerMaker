<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Filament\Admin\Pages;

use App\Models\Egg;
use App\Models\Node;
use App\Models\Role;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\FontWeight;
use Simplify\ServerMaker\Access;
use Simplify\ServerMaker\Creation\ExtraPorts;
use Simplify\ServerMaker\Creation\StartingResources;
use Simplify\ServerMaker\Games\Minecraft;
use Simplify\ServerMaker\Livewire\MakerSettingsForm;
use Simplify\ServerMaker\Settings;

/**
 * The Server Maker's settings: who besides administrators may make servers, where, which games and how much, what a
 * new Minecraft server starts with, and the CurseForge key. The form itself is MakerSettingsForm, which Simplify 2.0+
 * also shows in its settings › Add-ons. Opened from the Server Maker page (without Simplify), so it has no menu item.
 */
class MakerSettings extends Page
{
    protected static ?string $slug = 'server-maker/settings';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'simplify-servermaker::pages.settings';

    public static function canAccess(): bool
    {
        return (bool) user()?->can('view settings');
    }

    public static function mayChange(): bool
    {
        return (bool) user()?->can('update settings');
    }

    public function getTitle(): string
    {
        return __('simplify-servermaker::maker.settings.title');
    }

    /**
     * The saved settings without the CurseForge key, which is never sent back to the browser.
     *
     * @return array<string, mixed>
     */
    public static function current(): array
    {
        return [
            'self_service' => (bool) Settings::get('self_service', false),
            'role_ids' => array_map('intval', (array) Settings::get('role_ids', [])),
            'node_ids' => array_map('intval', (array) Settings::get('node_ids', [])),
            'eggs' => Access::allowedEggs(),
            'import_eggs' => (bool) Settings::get('import_eggs', false),
            'steam_art' => (bool) Settings::get('steam_art', true),
            'starting' => StartingResources::table(),
            ...Access::limits(),
        ];
    }

    /** Everything on the settings, here and in Simplify settings › Add-ons (MakerSettingsForm). */
    public static function formComponents(): array
    {
        $t = fn (string $key, array $replace = []) => __('simplify-servermaker::maker.settings.'.$key, $replace);

        return [
            self::whoSection(),
            self::allowanceSection(),
            self::startingSection(),
            Section::make($t('steam'))
                ->schema([
                    Toggle::make('steam_art')->label($t('steam_art'))->helperText($t('steam_art_help')),
                ]),
            // With Simplify Toolkit the CurseForge key is in Toolkit's settings; a key saved here before stays visible
            // until it is removed.
            Section::make($t('modpacks'))
                ->visible(fn () => ! class_exists('Simplify\\Toolkit\\Services\\Keys') || Settings::has('curseforge_key'))
                ->schema([
                    TextInput::make('curseforge_key')
                        ->label($t('curseforge_key'))
                        ->helperText(fn () => Settings::has('curseforge_key') ? $t('curseforge_saved') : $t('curseforge_help'))
                        ->password()
                        ->autocomplete('new-password')
                        ->maxLength(200),
                    Checkbox::make('curseforge_forget')
                        ->label($t('curseforge_forget'))
                        ->visible(fn () => Settings::has('curseforge_key')),
                ]),
        ];
    }

    /** Who besides administrators may make servers, where and which eggs; also asked in Simplify's setup. */
    public static function whoSection(): Section
    {
        $t = fn (string $key) => __('simplify-servermaker::maker.settings.'.$key);
        $on = fn (Get $get) => (bool) $get('self_service');
        // Adding missing eggs only matters while Minecraft or every egg may be picked.
        $import = function (Get $get) use ($on): bool {
            $eggs = Access::eggList((array) $get('eggs'));

            return $on($get) && ($eggs === [] || in_array(Access::MINECRAFT, $eggs, true));
        };

        return Section::make($t('who'))
            ->description($t('who_help'))
            ->schema([
                Toggle::make('self_service')->label($t('self_service'))->live(),
                Select::make('role_ids')
                    ->label($t('roles'))
                    ->helperText($t('roles_help'))
                    ->placeholder($t('roles_all'))
                    ->multiple()
                    ->options(fn () => Role::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->visible($on),
                Select::make('node_ids')
                    ->label($t('nodes'))
                    ->helperText($t('nodes_help'))
                    ->placeholder($t('nodes_all'))
                    ->multiple()
                    ->options(fn () => Node::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->visible($on),
                Select::make('eggs')
                    ->label($t('eggs'))
                    ->helperText($t('eggs_help'))
                    ->placeholder($t('eggs_all'))
                    ->multiple()
                    ->searchable()
                    ->options(fn () => self::eggOptions())
                    ->live()
                    ->visible($on),
                Toggle::make('import_eggs')->label($t('import_eggs'))->helperText($t('import_eggs_help'))->visible($import),
            ]);
    }

    /**
     * The choices for the eggs people may pick: Minecraft as one entry (its eggs follow the server type), then every
     * other egg on the panel.
     *
     * @return array<int|string, string>
     */
    public static function eggOptions(): array
    {
        $options = [Access::MINECRAFT => __('simplify-servermaker::maker.settings.minecraft_all')];
        foreach (Egg::query()->orderBy('name')->get(['id', 'name']) as $egg) {
            if (! Minecraft::isMinecraftEgg((string) $egg->name)) {
                $options[$egg->id] = (string) $egg->name;
            }
        }

        return $options;
    }

    /** How much one person may use over all their servers; also asked in Simplify's setup. */
    public static function allowanceSection(): Section
    {
        $t = fn (string $key) => __('simplify-servermaker::maker.settings.'.$key);
        $on = fn (Get $get) => (bool) $get('self_service');

        return Section::make($t('allowance'))
            ->description($t('allowance_help'))
            ->visible($on)
            ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
            ->schema([
                TextInput::make('max_servers')->label($t('max_servers'))->integer()->minValue(0)->maxValue(100)->required(),
                TextInput::make('max_ram')->label($t('max_ram'))->integer()->minValue(0)->maxValue(1024)->suffix('GB')->required(),
                TextInput::make('max_disk')->label($t('max_disk'))->integer()->minValue(0)->maxValue(10240)->suffix('GB')->required(),
                TextInput::make('max_cpu')->label($t('max_cpu'))->helperText($t('max_cpu_help'))->integer()->minValue(0)->maxValue(1600)->suffix('%')->required(),
                TextInput::make('max_backups')->label($t('max_backups'))->integer()->minValue(0)->maxValue(100)->required(),
                TextInput::make('max_ports')->label($t('max_ports'))->helperText($t('max_ports_help'))->integer()->minValue(0)->maxValue(ExtraPorts::MAX)->required(),
            ]);
    }

    /** The CPU, RAM and disk a new Minecraft server starts with, per type, as one compact table. */
    public static function startingSection(): Section
    {
        $t = fn (string $key) => __('simplify-servermaker::maker.'.$key);
        $cells = [Text::make('')];
        foreach (['cpu', 'ram', 'disk'] as $resource) {
            $cells[] = Text::make($t('resources.'.$resource))->color('gray');
        }
        foreach (Minecraft::TYPES as $type) {
            $cells[] = Text::make($t('minecraft.types.'.$type.'.label'))->weight(FontWeight::SemiBold);
            foreach (['cpu' => '%', 'ram' => 'GB', 'disk' => 'GB'] as $resource => $unit) {
                [$min, $max] = StartingResources::RANGE[$resource];
                $cells[] = TextInput::make('starting.'.$type.'.'.$resource)
                    ->hiddenLabel()
                    ->integer()
                    ->minValue($resource === 'disk' ? 1 : $min)
                    ->maxValue($resource === 'disk' ? intdiv($max, 1024) : $max)
                    ->suffix($unit)
                    ->required();
            }
        }

        return Section::make($t('settings.starting'))
            ->description($t('settings.starting_help'))
            ->schema([Grid::make(['default' => 4])->schema($cells)]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(__('simplify-servermaker::maker.settings.save'))
                ->icon('tabler-device-floppy')
                ->visible(fn () => static::mayChange())
                ->action(fn () => $this->dispatch(MakerSettingsForm::SAVE)->to(MakerSettingsForm::class))
                ->keyBindings(['mod+s']),
        ];
    }

    /**
     * Saves a form's state, the CurseForge key only when one was filled in (or Remove was ticked).
     *
     * @param  array<string, mixed>  $state
     */
    public static function save(array $state): void
    {
        abort_unless(static::mayChange(), 403);
        $values = self::values($state);
        $key = trim((string) ($state['curseforge_key'] ?? ''));
        if ($key !== '') {
            $values['curseforge_key'] = $key;
        } elseif ($state['curseforge_forget'] ?? false) {
            $values['curseforge_key'] = null;
        }
        Settings::put($values);
    }

    /**
     * The settings to save from a form's state, without the CurseForge key. Limits that were not asked stay as they are.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function values(array $state): array
    {
        $values = [
            'self_service' => (bool) ($state['self_service'] ?? false),
            'role_ids' => array_values(array_map('intval', (array) ($state['role_ids'] ?? []))),
            'node_ids' => array_values(array_map('intval', (array) ($state['node_ids'] ?? []))),
            'eggs' => Access::eggList((array) ($state['eggs'] ?? [])),
            'import_eggs' => (bool) ($state['import_eggs'] ?? false),
        ];
        if (array_key_exists('starting', $state)) {
            $values['starting_resources'] = StartingResources::fromTable((array) $state['starting']);
        }
        if (array_key_exists('steam_art', $state)) {
            $values['steam_art'] = (bool) $state['steam_art'];
        }
        foreach (array_keys(Access::LIMITS) as $limit) {
            if (array_key_exists($limit, $state)) {
                $values[$limit] = max(0, (int) $state[$limit]);
            }
        }

        return $values;
    }
}
