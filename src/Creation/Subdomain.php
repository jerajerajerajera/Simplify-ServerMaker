<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Creation;

use App\Models\Server;
use Throwable;

/**
 * The one place that talks to Simplify Subdomains (plugin simplify-subdomains), when that is installed and set up:
 * a subdomain for the new server, on a port that is forwarded. Without it every method says "not available", and a
 * failure there never stops a server from being made.
 */
final class Subdomain
{
    private const API = 'Simplify\\Subdomains\\Subdomains';

    public static function available(): bool
    {
        return class_exists(self::API) && (bool) self::call('ready', [], false);
    }

    public static function domain(): string
    {
        return self::available() ? (string) self::call('domain', [], '') : '';
    }

    /** A free name from the server name, or '' when there is none or Subdomains is not available. */
    public static function suggest(string $serverName): string
    {
        return self::available() && trim($serverName) !== '' ? (string) self::call('suggest', [$serverName], '') : '';
    }

    /** null when the name may be used, otherwise the reason. */
    public static function check(string $name): ?string
    {
        if (! self::available()) {
            return null;
        }
        $reason = self::call('check', [$name], null);

        return $reason === null ? null : (string) $reason;
    }

    /** A free port on the node that is forwarded, so the subdomain works. */
    public static function allocationFor(int $nodeId): ?int
    {
        $id = self::available() ? self::call('allocationFor', [$nodeId], null) : null;

        return $id === null ? null : (int) $id;
    }

    public static function request(Server $server, string $name): void
    {
        if ($name !== '' && self::available()) {
            self::call('request', [$server, $name], null);
        }
    }

    private static function call(string $method, array $arguments, mixed $fallback): mixed
    {
        try {
            return [self::API, $method](...$arguments);
        } catch (Throwable $exception) {
            report($exception);

            return $fallback;
        }
    }
}
