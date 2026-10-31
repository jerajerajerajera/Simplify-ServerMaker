<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Creation;

use App\Models\Server;

/**
 * The server's colour in Simplify (as in Simplify 0.7.0): the same colours as Simplify's own colour menu, saved
 * through Simplify after the server is made. Without Simplify (or an older one without its colour list) there is
 * nothing to choose.
 */
final class ServerColor
{
    private const LOOKS = 'Simplify\\Core\\Server\\Looks';

    /** @return array<string, string> hex => name */
    public static function choices(): array
    {
        return defined(self::LOOKS.'::COLORS') ? (array) constant(self::LOOKS.'::COLORS') : [];
    }

    public static function apply(Server $server, ?string $color): void
    {
        if ($color !== null && array_key_exists($color, self::choices())) {
            $looks = self::LOOKS;
            $looks::save($server, $color, false);
        }
    }
}
