<?php

declare(strict_types=1);

namespace Simplify\ServerMaker;

use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Simplify\ServerMaker\Filament\Admin\Pages\MakerSettings;
use Simplify\ServerMaker\Filament\Admin\Pages\ServerMaker;
use Simplify\ServerMaker\Filament\App\Pages\ServerMaker as AppServerMaker;
use Simplify\ServerMaker\Filament\App\Pages\Steam;
use Simplify\ServerMaker\Filament\Server\Pages\Performance;
use Simplify\ServerMaker\Filament\Server\Pages\ServerSoftware;

final class ServerMakerPlugin implements Plugin
{
    public function getId(): string
    {
        return 'simplify-servermaker';
    }

    public function register(Panel $panel): void
    {
        match ($panel->getId()) {
            'admin' => $panel->pages([ServerMaker::class, MakerSettings::class]),
            'app' => $panel->pages([AppServerMaker::class, Steam::class])->userMenuItems([...self::makerMenu(), ...self::steamMenu()]),
            'server' => $panel->pages([ServerSoftware::class, Performance::class]),
            default => null,
        };
    }

    /**
     * Without Simplify there is no Create server button on the overview: people who may make servers reach the Server
     * Maker from Pelican's user menu. Administrators also have it in the admin menu.
     *
     * @return array<string, Action>
     */
    public static function makerMenu(): array
    {
        return [
            'simplify-servermaker' => Action::make('simplify-servermaker')
                ->label(fn () => __('simplify-servermaker::maker.addon.create'))
                ->icon('tabler-plus')
                ->url(fn () => AppServerMaker::getUrl(panel: 'app'))
                ->visible(fn () => ! CoreApi::atLeast(1) && Access::canUse()),
        ];
    }

    /** Without Simplify's account menu (add-on API 4) the Steam page is reached from Pelican's user menu. */
    private static function steamMenu(): array
    {
        if (CoreApi::atLeast(4)) {
            return [];
        }

        return [
            'simplify-servermaker-steam' => Action::make('simplify-servermaker-steam')
                ->label(fn () => __('simplify-servermaker::maker.steam.title'))
                ->icon('tabler-brand-steam')
                ->url(fn () => Steam::getUrl(panel: 'app')),
        ];
    }

    public function boot(Panel $panel): void {}
}
