<?php

declare(strict_types=1);

namespace Simplify\ServerMaker;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class ServerMakerPlugin implements Plugin
{
    public function getId(): string
    {
        return 'simplify-server-maker';
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void {}
}
