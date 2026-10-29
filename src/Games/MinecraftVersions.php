<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Games;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Minecraft releases and the Java each one needs, from Mojang's public version list. Answers are cached for six
 * hours; a failed lookup for ten minutes, so an unreachable Mojang never slows the Server Maker down twice.
 */
final class MinecraftVersions
{
    private const MANIFEST = 'https://piston-meta.mojang.com/mc/game/version_manifest_v2.json';

    private const FALLBACK = ['1.21.10', '1.21.8', '1.21.4', '1.21.1', '1.20.6', '1.20.4', '1.20.1', '1.19.4', '1.18.2', '1.16.5', '1.12.2'];

    /** @return array<string, string> newest first, version => version */
    public static function releases(): array
    {
        $releases = self::remember('releases', function (): ?array {
            $versions = self::manifest()['versions'] ?? [];
            $ids = [];
            foreach ($versions as $version) {
                if (($version['type'] ?? '') === 'release' && is_string($version['id'] ?? null)) {
                    $ids[] = $version['id'];
                }
            }

            return $ids === [] ? null : array_slice($ids, 0, 80);
        });

        $list = $releases ?: self::FALLBACK;

        return array_combine($list, $list);
    }

    public static function newest(): string
    {
        return array_key_first(self::releases());
    }

    /** The Java major version a release needs: Mojang's own answer, else the known table. */
    public static function java(string $minecraft, string $type): int
    {
        // Paper publishes its own (newer) Java requirements.
        if ($type === 'plugins' || ! Minecraft::validVersion($minecraft)) {
            return Java::forMinecraft($minecraft, $type);
        }
        $major = self::remember('java.'.$minecraft, function () use ($minecraft): ?int {
            foreach (self::manifest()['versions'] ?? [] as $version) {
                if (($version['id'] ?? '') !== $minecraft) {
                    continue;
                }
                $url = (string) ($version['url'] ?? '');
                if (parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== 'piston-meta.mojang.com') {
                    return null;
                }
                $value = Http::timeout(4)->connectTimeout(2)->get($url)->json('javaVersion.majorVersion');

                return is_numeric($value) && (int) $value >= 8 && (int) $value <= 99 ? (int) $value : null;
            }

            return null;
        });

        return $major ?? Java::forMinecraft($minecraft, $type);
    }

    private static function manifest(): array
    {
        return self::remember('manifest', function (): ?array {
            $response = Http::timeout(4)->connectTimeout(2)->get(self::MANIFEST);

            return $response->successful() ? $response->json() : null;
        }) ?? [];
    }

    private static function remember(string $key, callable $resolve): mixed
    {
        $key = 'simplify-servermaker.mojang.'.$key;
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached === false ? null : $cached;
        }
        try {
            $value = $resolve();
        } catch (Throwable) {
            $value = null;
        }
        Cache::put($key, $value ?? false, $value === null ? 600 : 21600);

        return $value;
    }
}
