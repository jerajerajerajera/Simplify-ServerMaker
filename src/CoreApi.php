<?php

declare(strict_types=1);

namespace Simplify\ServerMaker;

/** Which add-on API the installed Simplify core offers (its Addon::API), without needing the core. */
final class CoreApi
{
    private const ADDON = 'Simplify\\Core\\Addons\\Addon';

    /** Whether Simplify is installed with at least this add-on API; older versions have no API constant at all. */
    public static function atLeast(int $version): bool
    {
        return class_exists(self::ADDON) && defined(self::ADDON.'::API') && constant(self::ADDON.'::API') >= $version;
    }
}
