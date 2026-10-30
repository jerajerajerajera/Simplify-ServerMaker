<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Games;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Simplify\ServerMaker\Settings;
use Throwable;

/**
 * Steam artwork for a game server (as in Simplify 0.7.0). An egg only knows its dedicated-server app, which has no
 * store art, so the game is looked up by name in the public Steam store search (no API key). A match is cached for
 * a week, a miss or failure for a day.
 */
final class SteamArt
{
    public const CDN = 'https://cdn.cloudflare.steamstatic.com/steam/apps/';

    public static function enabled(): bool
    {
        return (bool) Settings::get('steam_art', true);
    }

    /** "Valheim (Plus) Dedicated Server" → "Valheim". */
    public static function searchName(string $eggName): string
    {
        $name = preg_replace('/\s*[\(\[][^\)\]]*[\)\]]\s*/', ' ', $eggName) ?? $eggName;
        $name = preg_replace('/\b(dedicated\s+server|dedicated|server|servers|egg|ds)\b/i', ' ', $name) ?? $name;

        return trim(preg_replace('/\s+/', ' ', $name) ?? $name, " -–:|");
    }

    /** @return array{appid: int, name: string, banner: string, header: string, capsule: string}|null */
    public static function find(string $eggName): ?array
    {
        $term = self::searchName($eggName);
        if (! self::enabled() || mb_strlen($term) < 2) {
            return null;
        }
        $key = 'simplify-servermaker.steam.'.md5(mb_strtolower($term));
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached ?: null;
        }
        try {
            $items = Http::timeout(4)->connectTimeout(2)->acceptJson()
                ->get('https://store.steampowered.com/api/storesearch/', ['term' => $term, 'cc' => 'us', 'l' => 'en'])
                ->throw()->json('items');
            $art = self::pick(is_array($items) ? $items : [], $term);
        } catch (Throwable) {
            $art = null;
        }
        Cache::put($key, $art ?? false, $art ? 604800 : 86400);

        return $art;
    }

    /**
     * An exact name match first, then a result that contains the whole search name.
     *
     * @param  array<int, mixed>  $items
     * @return array{appid: int, name: string, banner: string, header: string, capsule: string}|null
     */
    public static function pick(array $items, string $term): ?array
    {
        $norm = static fn ($text): string => preg_replace('/[^a-z0-9]+/', '', mb_strtolower((string) $text)) ?? '';
        $wanted = $norm($term);
        $best = null;
        foreach ($wanted === '' ? [] : $items as $item) {
            if (! is_array($item) || ! is_numeric($item['id'] ?? null)) {
                continue;
            }
            $name = $norm($item['name'] ?? '');
            if ($name === $wanted) {
                $best = $item;
                break;
            }
            if ($best === null && str_contains($name, $wanted)) {
                $best = $item;
            }
        }
        if ($best === null) {
            return null;
        }
        $id = (int) $best['id'];

        return ['appid' => $id, 'name' => (string) ($best['name'] ?? ''), 'banner' => self::CDN.$id.'/library_hero.jpg',
            'header' => self::CDN.$id.'/header.jpg', 'capsule' => self::CDN.$id.'/library_600x900.jpg'];
    }
}
