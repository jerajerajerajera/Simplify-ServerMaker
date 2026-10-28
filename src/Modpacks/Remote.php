<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Modpacks;

use Closure;
use Illuminate\Support\Facades\Http;
use Throwable;

/** JSON from the modpack sites, with short time-outs. An error is an empty answer, never a broken page. */
final class Remote
{
    /** Tests replace the network: fn (string $url, array $query, array $headers): array */
    public static ?Closure $transport = null;

    public static function json(string $url, array $query = [], array $headers = []): array
    {
        if (self::$transport) {
            return (self::$transport)($url, $query, $headers);
        }
        try {
            $response = Http::acceptJson()
                ->connectTimeout(2)
                ->timeout(6)
                ->withHeaders(['User-Agent' => 'Simplify-Server-Maker (Pelican plugin)', ...$headers])
                ->get($url, $query);
            $data = $response->successful() ? $response->json() : null;

            return is_array($data) ? $data : [];
        } catch (Throwable) {
            return [];
        }
    }

    public static function safeUrl(mixed $url): ?string
    {
        return is_string($url)
            && filter_var($url, FILTER_VALIDATE_URL)
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && ! parse_url($url, PHP_URL_USER)
            ? $url : null;
    }
}
