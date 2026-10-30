<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Games;

use App\Enums\EggFormat;
use App\Models\Egg;
use App\Services\Eggs\Sharing\EggImporterService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Every game in the official Pelican egg repositories (as in Simplify 0.7.0): a list that comes with the plugin,
 * with a pinned download address and a checksum per egg (scripts/build-egg-index.py). A game that is not on the
 * panel yet is downloaded, checked against that checksum and imported when someone continues with it.
 */
final class EggIndex
{
    /** The games people play most, first under Popular. */
    private const POPULAR = ['valheim', 'rust', 'terraria', 'palworld', 'ark: survival ascended', '7 days to die', 'satisfactory', 'counter-strike 2', 'project zomboid', 'factorio', 'enshrouded', 'v rising'];

    private const SOURCE = 'https://raw.githubusercontent.com/pelican-eggs/';

    /** @var list<array<string, mixed>>|null */
    private static ?array $games = null;

    /** @return list<array<string, mixed>> */
    public static function games(): array
    {
        if (self::$games !== null) {
            return self::$games;
        }
        try {
            $data = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/resources/catalog/game-index.json'), true, 16, JSON_THROW_ON_ERROR);

            return self::$games = array_values(array_filter($data['games'] ?? [], 'is_array'));
        } catch (Throwable) {
            return self::$games = [];
        }
    }

    /** Only people who may add eggs to the panel see the games that are not on it yet. */
    public static function canInstall(): bool
    {
        return (bool) user()?->can('create', Egg::class);
    }

    /** Higher is more popular: the place on the list above, 0 for the rest. */
    public static function rank(string $name): int
    {
        $at = array_search(mb_strtolower($name), self::POPULAR, true);

        return $at === false ? 0 : count(self::POPULAR) - $at;
    }

    /**
     * Cards for the games that are not on the panel yet, leaving out anything already there by uuid or name.
     *
     * @return list<array{key: string, name: string, description: string, installed: bool, popular: int}>
     */
    public static function cards(): array
    {
        if (! self::canInstall()) {
            return [];
        }
        $present = Egg::query()->get(['uuid', 'name']);
        $uuids = $present->pluck('uuid')->filter()->flip();
        $names = $present->pluck('name')->map(fn ($name) => mb_strtolower((string) $name))->flip();
        $cards = [];
        foreach (self::games() as $game) {
            if (blank($game['sha256'] ?? null) || blank($game['uuid'] ?? null)) {
                continue;
            }
            if (isset($uuids[$game['uuid'] ?? '']) || isset($names[mb_strtolower((string) $game['name'])]) || Minecraft::isMinecraftEgg((string) $game['name'])) {
                continue;
            }
            $cards[] = [
                'key' => 'index:'.$game['id'],
                'name' => (string) $game['name'],
                'description' => OtherGames::summary((string) ($game['description'] ?: $game['category'].' game server')),
                'installed' => false,
                'popular' => self::rank((string) $game['name']),
            ];
        }

        return $cards;
    }

    /** @return array<string, mixed>|null */
    public static function find(string $id): ?array
    {
        foreach (self::games() as $game) {
            if (($game['id'] ?? null) === $id) {
                return $game;
            }
        }

        return null;
    }

    /** The egg for an index game: the one on the panel with the same uuid, or downloaded, checked and imported. */
    public static function install(string $id): Egg
    {
        $game = self::find($id);
        if (! $game || ! self::canInstall()) {
            throw ValidationException::withMessages(['data.game' => __('simplify-servermaker::maker.game.required')]);
        }
        try {
            return Cache::lock('simplify-servermaker.egg-index.'.$id, 120)->block(15, fn (): Egg => self::import($game));
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['data.game' => __('simplify-servermaker::maker.game.install_busy')]);
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['data.game' => __('simplify-servermaker::maker.game.install_failed', ['error' => $exception->getMessage()])]);
        }
    }

    /** @param  array<string, mixed>  $game */
    private static function import(array $game): Egg
    {
        // By uuid only: an unrelated egg that happens to share the name is never taken for this one.
        $egg = filled($game['uuid'] ?? null) ? Egg::query()->where('uuid', $game['uuid'])->first() : null;
        if ($egg) {
            return $egg;
        }
        if (! str_starts_with((string) $game['url'], self::SOURCE)) {
            throw new RuntimeException('Unknown egg source.');
        }
        $content = Http::timeout(20)->connectTimeout(5)->get((string) $game['url'])->throw()->body();
        if (! hash_equals((string) $game['sha256'], hash('sha256', $content))) {
            throw new RuntimeException('The download does not match the egg index checksum.');
        }

        return app(EggImporterService::class)->fromContent($content, EggFormat::JSON, new Egg);
    }
}
