<?php

declare(strict_types=1);

namespace Simplify\ServerMaker;

use App\Models\User;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\Button;
use Simplify\ServerMaker\Filament\Admin\Pages\MakerSettings;
use Simplify\ServerMaker\Filament\App\Pages\ServerMaker as AppServerMaker;
use Simplify\ServerMaker\Filament\App\Pages\Steam;
use Simplify\ServerMaker\Livewire\MakerSettingsForm;
use Simplify\ServerMaker\Livewire\SteamConnection;

/** How the Server Maker shows up in Simplify (plugin id pelican-simplify), when that is installed. */
final class ServerMakerAddon extends Addon
{
    public function id(): string
    {
        return 'simplify-servermaker';
    }

    public function name(): string
    {
        return __('simplify-servermaker::maker.addon.name');
    }

    public function description(): string
    {
        return __('simplify-servermaker::maker.addon.description');
    }

    public function icon(): string
    {
        return 'tabler-wand';
    }

    /** "Create server" on the dashboard, for everyone who may use the Server Maker. */
    public function dashboardActions(): array
    {
        if (! Access::canUse()) {
            return [];
        }

        return [new Button(__('simplify-servermaker::maker.addon.create'), AppServerMaker::getUrl(panel: 'app'), 'tabler-plus', true)];
    }

    /** Steam in Simplify's account menu (add-on API 4 and 5); since API 6 it is a row on Connections instead. */
    public function accountPages(User $user): array
    {
        if (CoreApi::atLeast(6)) {
            return [];
        }

        return [new Button(__('simplify-servermaker::maker.steam.title'), Steam::getUrl(panel: 'app'), 'tabler-brand-steam')];
    }

    /** The Steam row on Simplify's Connections page (add-on API 6): the person's own key for game server login tokens. */
    public function connections(User $user): array
    {
        return [SteamConnection::class];
    }

    /** The settings in Simplify settings › Add-ons (add-on API 8), the same form as on the settings page. */
    public function settingsForm(): ?string
    {
        return MakerSettingsForm::class;
    }

    public function settingsUrl(): ?string
    {
        if (! MakerSettings::canAccess()) {
            return null;
        }
        $settings = 'Simplify\\Core\\Filament\\Admin\\Pages\\Settings';

        return CoreApi::atLeast(8) && method_exists($settings, 'addonUrl')
            ? $settings::addonUrl($this->id())
            : MakerSettings::getUrl(panel: 'admin');
    }

    /** In Simplify's setup (Simplify 1.3+, add-on API 3): who may make servers and how much. */
    public function setupFields(): array
    {
        return MakerSettings::mayChange() ? [MakerSettings::whoSection(), MakerSettings::allowanceSection()] : [];
    }

    public function setupState(): array
    {
        return MakerSettings::current();
    }

    public function saveSetup(array $state): void
    {
        if (MakerSettings::mayChange()) {
            Settings::put(MakerSettings::values($state));
        }
    }
}
