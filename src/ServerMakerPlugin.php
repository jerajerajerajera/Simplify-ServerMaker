<?php

declare(strict_types=1);

namespace Simplify\ServerMaker;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Simplify\ServerMaker\Filament\Admin\Pages\MakerSettings;
use Simplify\ServerMaker\Filament\Admin\Pages\ServerMaker;
use Simplify\ServerMaker\Filament\App\Pages\ServerMaker as AppServerMaker;

final class ServerMakerPlugin implements Plugin
{
    public function getId(): string
    {
        return 'simplify-server-maker';
    }

    public function register(Panel $panel): void
    {
        match ($panel->getId()) {
            'admin' => $panel->pages([ServerMaker::class, MakerSettings::class]),
            'app' => $panel->pages([AppServerMaker::class]),
            default => null,
        };
    }

    public function boot(Panel $panel): void {}
}
