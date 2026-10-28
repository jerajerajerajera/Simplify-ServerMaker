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
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Simplify\ServerMaker\Access;
use Simplify\ServerMaker\Settings;

/**
 * The Server Maker's settings: who besides administrators may make servers, where, which games and how much, and the
 * CurseForge key. Opened from the Server Maker page (and from Simplify when it is installed), so it has no menu item.
 */
class MakerSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'server-maker/settings';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'simplify-server-maker::pages.settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

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
        return __('simplify-server-maker::maker.settings.title');
    }

    public function mount(): void
    {
        $values = [
            'self_service' => (bool) Settings::get('self_service', false),
            'role_ids' => array_map('intval', (array) Settings::get('role_ids', [])),
            'node_ids' => array_map('intval', (array) Settings::get('node_ids', [])),
            'minecraft' => (bool) Settings::get('minecraft', true),
            'egg_ids' => array_map('intval', (array) Settings::get('egg_ids', [])),
            'import_eggs' => (bool) Settings::get('import_eggs', false),
            'curseforge_key' => '',
            'curseforge_forget' => false,
            ...Access::limits(),
        ];
        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        $t = fn (string $key, array $replace = []) => __('simplify-server-maker::maker.settings.'.$key, $replace);
        $on = fn (Get $get) => (bool) $get('self_service');

        return $schema
            ->statePath('data')
            ->disabled(fn () => ! static::mayChange())
            ->components([
                Section::make($t('who'))
                    ->description($t('who_help'))
                    ->schema([
                        Toggle::make('self_service')->label($t('self_service'))->live(),
                        Select::make('role_ids')
                            ->label($t('roles'))
                            ->helperText($t('roles_help'))
                            ->multiple()
                            ->options(fn () => Role::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->visible($on),
                        Select::make('node_ids')
                            ->label($t('nodes'))
                            ->helperText($t('nodes_help'))
                            ->multiple()
                            ->options(fn () => Node::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->required($on)
                            ->visible($on),
                        Toggle::make('minecraft')->label($t('minecraft'))->visible($on),
                        Select::make('egg_ids')
                            ->label($t('eggs'))
                            ->helperText($t('eggs_help'))
                            ->multiple()
                            ->searchable()
                            ->options(fn () => Egg::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->visible($on),
                        Toggle::make('import_eggs')->label($t('import_eggs'))->helperText($t('import_eggs_help'))->visible($on),
                    ]),
                Section::make($t('allowance'))
                    ->description($t('allowance_help'))
                    ->visible($on)
                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                    ->schema([
                        TextInput::make('max_servers')->label($t('max_servers'))->integer()->minValue(0)->maxValue(100)->required(),
                        TextInput::make('max_ram')->label($t('max_ram'))->integer()->minValue(0)->maxValue(1024)->suffix('GB')->required(),
                        TextInput::make('max_disk')->label($t('max_disk'))->integer()->minValue(0)->maxValue(10240)->suffix('GB')->required(),
                        TextInput::make('max_cpu')->label($t('max_cpu'))->helperText($t('max_cpu_help'))->integer()->minValue(0)->maxValue(1600)->suffix('%')->required(),
                        TextInput::make('max_backups')->label($t('max_backups'))->integer()->minValue(0)->maxValue(100)->required(),
                    ]),
                Section::make($t('modpacks'))
                    ->schema([
                        TextInput::make('curseforge_key')
                            ->label($t('curseforge_key'))
                            ->helperText(fn () => Settings::has('curseforge_key') ? $t('curseforge_saved') : $t('curseforge_help'))
                            ->password()
                            ->autocomplete('off')
                            ->maxLength(200),
                        Checkbox::make('curseforge_forget')
                            ->label($t('curseforge_forget'))
                            ->visible(fn () => Settings::has('curseforge_key')),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(__('simplify-server-maker::maker.settings.save'))
                ->icon('tabler-device-floppy')
                ->visible(fn () => static::mayChange())
                ->action(fn () => $this->save())
                ->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        abort_unless(static::mayChange(), 403);
        $state = $this->form->getState();
        $values = [
            'self_service' => (bool) ($state['self_service'] ?? false),
            'role_ids' => array_values(array_map('intval', (array) ($state['role_ids'] ?? []))),
            'node_ids' => array_values(array_map('intval', (array) ($state['node_ids'] ?? []))),
            'minecraft' => (bool) ($state['minecraft'] ?? true),
            'egg_ids' => array_values(array_map('intval', (array) ($state['egg_ids'] ?? []))),
            'import_eggs' => (bool) ($state['import_eggs'] ?? false),
        ];
        foreach (array_keys(Access::LIMITS) as $limit) {
            if (array_key_exists($limit, $state)) {
                $values[$limit] = max(0, (int) $state[$limit]);
            }
        }
        $key = trim((string) ($state['curseforge_key'] ?? ''));
        if ($key !== '') {
            $values['curseforge_key'] = $key;
        } elseif ($state['curseforge_forget'] ?? false) {
            $values['curseforge_key'] = null;
        }
        Settings::put($values);

        // The key is never sent back to the browser.
        $this->data['curseforge_key'] = '';
        $this->data['curseforge_forget'] = false;
        Notification::make()->title(__('simplify-server-maker::maker.settings.saved'))->success()->send();
    }
}
