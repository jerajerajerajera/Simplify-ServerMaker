<?php

declare(strict_types=1);

namespace Simplify\ServerMaker;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Simplify\ServerMaker\Filament\Admin\Pages\ServerMaker;

final class ServerMakerPlugin implements Plugin
{
    public function getId(): string
    {
        return 'simplify-server-maker';
    }

    public function register(Panel $panel): void
    {
        if ($panel->getId() === 'admin') {
            $panel->pages([ServerMaker::class]);
        }
    }

    public function boot(Panel $panel): void {}
}
