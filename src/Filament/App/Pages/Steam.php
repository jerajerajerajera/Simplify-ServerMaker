<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Filament\App\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Simplify\ServerMaker\Steam\SteamKeys;

/**
 * Steam: a person's own Steam Web API key, so the Server Maker can create game server login tokens for them (as in
 * Simplify 0.7.0). Opened from Simplify's account menu; the key is never shown again.
 *
 * @property Schema $form
 */
final class Steam extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-brand-steam';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'steam';

    protected string $view = 'simplify-servermaker::pages.settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return user() !== null;
    }

    public function getTitle(): string
    {
        return __('simplify-servermaker::maker.steam.title');
    }

    public function mount(): void
    {
        $this->form->fill(['auto' => SteamKeys::status(user())['auto']]);
    }

    public function form(Schema $schema): Schema
    {
        $t = fn (string $key, array $replace = []) => __('simplify-servermaker::maker.steam.'.$key, $replace);

        return $schema->statePath('data')->components([
            Text::make($t('help'))->color('gray'),
            Text::make(function () use ($t): string {
                $status = SteamKeys::status(user());
                if (! $status['connected']) {
                    return $t('no_key');
                }

                return $status['steamid']
                    ? $t('key_account', ['hint' => $status['hint'], 'account' => $status['steamid']])
                    : $t('key', ['hint' => $status['hint']]);
            }),
            Toggle::make('auto')
                ->label($t('auto'))
                ->helperText($t('auto_help'))
                ->visible(fn () => SteamKeys::connected(user()))
                ->live()
                ->afterStateUpdated(fn (bool $state) => SteamKeys::setAuto(user(), $state)),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $t = fn (string $key) => __('simplify-servermaker::maker.steam.'.$key);

        return [
            Action::make('connect')
                ->label(fn () => SteamKeys::connected(user()) ? $t('replace') : $t('connect'))
                ->icon('tabler-key')
                ->modalHeading($t('connect_heading'))
                ->modalDescription($t('connect_help'))
                ->schema([
                    TextInput::make('key')
                        ->label($t('key_label'))
                        ->password()
                        ->required()
                        ->maxLength(32)
                        ->autocomplete('off'),
                ])
                ->modalSubmitActionLabel($t('connect'))
                ->action(function (array $data): void {
                    SteamKeys::save(user(), (string) $data['key']);
                    $this->data['auto'] = true;
                    Notification::make()->title($this->translate('connected'))->success()->send();
                }),
            ActionGroup::make([
                Action::make('disconnect')
                    ->label($t('disconnect'))
                    ->icon('tabler-unlink')
                    ->requiresConfirmation()
                    ->modalDescription($t('disconnect_help'))
                    ->action(function (): void {
                        SteamKeys::remove(user());
                        Notification::make()->title($this->translate('disconnected'))->success()->send();
                    }),
            ])->visible(fn () => SteamKeys::connected(user())),
        ];
    }

    private function translate(string $key): string
    {
        return __('simplify-servermaker::maker.steam.'.$key);
    }
}
