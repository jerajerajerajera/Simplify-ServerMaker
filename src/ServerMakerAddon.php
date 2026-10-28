<?php

declare(strict_types=1);

namespace Simplify\ServerMaker;

use Simplify\Core\Addons\Addon;

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
}
