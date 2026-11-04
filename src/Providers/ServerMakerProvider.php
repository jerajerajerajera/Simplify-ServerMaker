<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Simplify\ServerMaker\Livewire\SteamConnection;
use Simplify\ServerMaker\Problems\ServerMakerProblems;
use Simplify\ServerMaker\ServerMakerAddon;

final class ServerMakerProvider extends ServiceProvider
{
    public function register(): void
    {
        // Only a class name: Simplify loads it when it asks for its add-ons, so without Simplify nothing is loaded.
        $this->app->tag([ServerMakerAddon::class], 'simplify.addons');
        // Simplify-Explain asks this for the lines behind an error the Server Maker showed.
        $this->app->tag([ServerMakerProblems::class], 'simplify.problems');
    }

    public function boot(): void
    {
        Livewire::component('simplify-servermaker-steam-connection', SteamConnection::class);
    }
}
