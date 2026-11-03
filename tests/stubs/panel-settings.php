<?php

// Simplify's friends mode (core 2.0 beta, PanelSettings::friendsMode), for the Server Maker's smoke test without the core.

declare(strict_types=1);

namespace Simplify\Core\Support;

if (! class_exists(PanelSettings::class)) {
    final class PanelSettings
    {
        public static bool $friends = false;

        public static function friendsMode(): bool
        {
            return self::$friends;
        }
    }
}
