<?php

// A stand-in for Simplify Subdomains (plugin simplify-subdomains), with the methods its README promises.

declare(strict_types=1);

namespace Simplify\Subdomains;

use App\Models\Server;

final class Subdomains
{
    public static ?int $allocation = null;

    public static function ready(): bool
    {
        return true;
    }

    public static function domain(): string
    {
        return 'example.com';
    }

    public static function suggest(string $serverName, ?string $uuid = null): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($serverName)), '-');
    }

    public static function check(string $name, ?string $uuid = null): ?string
    {
        return $name === 'taken' ? 'That name is taken.' : null;
    }

    public static function allocationFor(int $nodeId): ?int
    {
        return self::$allocation;
    }

    public static function request(Server $server, string $name): void {}
}
