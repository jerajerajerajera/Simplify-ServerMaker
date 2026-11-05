<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Simplify\ServerMaker\Steam\SteamKeys;

/**
 * The Steam row: a person's own Steam Web API key, so the Server Maker can create game server login tokens for them.
 * On Simplify's Connections page (add-on API 6) and on the Steam page. It saves only from its own popups, never by
 * itself; the key is never shown again, only its last four characters.
 */
final class SteamConnection extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /** Connect a key, or change the key and the automatic tokens of a connected one. */
    public function connectAction(): Action
    {
        $t = self::translator();

        return Action::make('connect')
            ->label(fn () => SteamKeys::connected(user()) ? $t('change') : $t('connect'))
            ->button()
            ->color('gray')
            ->icon('tabler-key')
            ->modalHeading($t('connect_heading'))
            ->modalDescription($t('help').' '.$t('connect_help'))
            ->fillForm(fn () => ['key' => '', 'auto' => SteamKeys::status(user())['auto']])
            ->schema([
                TextInput::make('key')
                    ->label($t('key_label'))
                    ->helperText(fn () => SteamKeys::connected(user()) ? $t('keep_key') : null)
                    ->password()
                    ->required(fn () => ! SteamKeys::connected(user()))
                    ->maxLength(32)
                    ->autocomplete('new-password'),
                Toggle::make('auto')
                    ->label($t('auto'))
                    ->helperText($t('auto_help')),
            ])
            ->modalSubmitActionLabel(__('simplify-servermaker::maker.settings.save'))
            ->action(function (array $data) use ($t): void {
                $user = user();
                abort_if($user === null, 403);
                $key = trim((string) ($data['key'] ?? ''));
                if ($key !== '') {
                    SteamKeys::save($user, $key);
                }
                SteamKeys::setAuto($user, (bool) ($data['auto'] ?? false));
                Notification::make()->title($key !== '' ? $t('connected') : __('simplify-servermaker::maker.settings.saved'))->success()->send();
            });
    }

    public function disconnectAction(): Action
    {
        $t = self::translator();

        return Action::make('disconnect')
            ->label($t('disconnect'))
            ->icon('tabler-unlink')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription($t('disconnect_help'))
            ->visible(fn () => SteamKeys::connected(user()))
            ->action(function () use ($t): void {
                abort_if(user() === null, 403);
                SteamKeys::remove(user());
                Notification::make()->title($t('disconnected'))->success()->send();
            });
    }

    public function render(): View
    {
        $t = self::translator();
        $status = SteamKeys::status(user());

        return view('simplify-servermaker::livewire.steam-connection', [
            'title' => $t('title'),
            'addon' => __('simplify-servermaker::maker.addon.name'),
            'connected' => $status['connected'],
            'line' => match (true) {
                ! $status['connected'] => $t('no_key'),
                $status['steamid'] !== null => $t('key_account', ['hint' => $status['hint'], 'account' => $status['steamid']]),
                default => $t('key', ['hint' => $status['hint']]),
            },
            'auto' => $status['connected'] && $status['auto'] ? $t('auto_on') : null,
        ]);
    }

    /** @return \Closure(string, array<string, mixed>=): string */
    private static function translator(): \Closure
    {
        return fn (string $key, array $replace = []): string => (string) __('simplify-servermaker::maker.steam.'.$key, $replace);
    }
}
