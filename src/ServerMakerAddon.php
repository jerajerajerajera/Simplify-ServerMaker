<?php

declare(strict_types=1);

namespace Simplify\ServerMaker;

use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\Button;
use Simplify\ServerMaker\Filament\Admin\Pages\MakerSettings;
use Simplify\ServerMaker\Filament\App\Pages\ServerMaker as AppServerMaker;

/** How the Server Maker shows up in Simplify (plugin id pelican-simplify), when that is installed. */
final class ServerMakerAddon extends Addon
{
    public function id(): string
    {
        return 'simplify-server-maker';
    }

    public function name(): string
    {
        return __('simplify-server-maker::maker.addon.name');
    }

    public function description(): string
    {
        return __('simplify-server-maker::maker.addon.description');
    }

    public function icon(): string
    {
        return 'tabler-server-2';
    }

    /** "Create server" on the dashboard, for everyone who may use the Server Maker. */
    public function dashboardActions(): array
    {
        if (! Access::canUse()) {
            return [];
        }

        return [new Button(__('simplify-server-maker::maker.addon.create'), AppServerMaker::getUrl(panel: 'app'), 'tabler-plus', true)];
    }

    public function settingsUrl(): ?string
    {
        return MakerSettings::canAccess() ? MakerSettings::getUrl(panel: 'admin') : null;
    }
}
