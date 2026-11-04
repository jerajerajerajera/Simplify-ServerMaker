<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Filament\App\Pages;

use Filament\Pages\Page;
use Simplify\ServerMaker\CoreApi;

/**
 * Steam: the Steam row (SteamConnection) on a page of its own, for Simplify before Connections (add-on API 6) and for
 * panels without Simplify, where Pelican's user menu links here. The key is never shown again.
 */
final class Steam extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'tabler-brand-steam';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'steam';

    protected string $view = 'simplify-servermaker::pages.steam';

    /** Since Connections (add-on API 6) the Steam row is there, so the page is gone: one place for the key. */
    public static function canAccess(): bool
    {
        return user() !== null && ! CoreApi::atLeast(6);
    }

    public function getTitle(): string
    {
        return __('simplify-servermaker::maker.steam.title');
    }
}
