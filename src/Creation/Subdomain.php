<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Creation;

use App\Models\Server;
use Throwable;

/**
 * The one place that talks to subdomains, when they are set up: Simplify's own (since Simplify 1.2) or the older
 * separate plugin Simplify Subdomains. A subdomain for the new server, on a port that is forwarded. Without either
 * every method says "not available", and a failure there never stops a server from being made.
 */
final class Subdomain
{
    /** Simplify's own first; its ready() is false while that part is off or the separate plugin is still installed. */
    private const APIS = ['Simplify\\Core\\Subdomains\\Subdomains', 'Simplify\\Subdomains\\Subdomains'];

    public static function available(): bool
    {
        return self::api() !== null;
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

    /** The first API that is installed and ready. */
    private static function api(): ?string
    {
        foreach (self::APIS as $api) {
            if (class_exists($api) && (bool) self::call('ready', [], false, $api)) {
                return $api;
            }
        }

        return null;
    }

    private static function call(string $method, array $arguments, mixed $fallback, ?string $api = null): mixed
    {
        try {
            return [$api ?? self::api(), $method](...$arguments);
        } catch (Throwable $exception) {
            report($exception);

            return $fallback;
        }
    }
}
