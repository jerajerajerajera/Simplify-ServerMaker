<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Games;

use App\Models\Egg;
use App\Models\Server;
use Illuminate\Database\Eloquent\Builder;
use Simplify\ServerMaker\Access;

/** Every other game: the eggs on the panel that the Minecraft part does not cover. */
final class OtherGames
{
    /** @return Builder<Egg> */
    public static function query(): Builder
    {
        return Access::eggs();
    }

    /**
     * Cards for the game picker: the games on the panel, and for people who may add eggs every game in the
     * Pelican egg index that is not on it yet. Most played first, then the well-known games.
     *
     * @return list<array{key: string, name: string, description: string, installed: bool, popular: int}>
     */
    public static function cards(): array
    {
        $used = Server::query()->selectRaw('egg_id, count(*) as total')->groupBy('egg_id')->pluck('total', 'egg_id');
        $cards = [];
        foreach (self::query()->get(['id', 'name', 'description']) as $egg) {
            if (Minecraft::isMinecraftEgg((string) $egg->name)) {
                continue;
            }
            $cards[] = [
                'key' => 'egg:'.$egg->id,
                'name' => (string) $egg->name,
                'description' => self::summary((string) $egg->description),
                'installed' => true,
                'popular' => (int) ($used[$egg->id] ?? 0) * 100 + EggIndex::rank((string) $egg->name),
            ];
        }
        $cards = [...$cards, ...EggIndex::cards()];
        usort($cards, fn (array $a, array $b) => [$b['popular'], $b['installed'], $a['name']] <=> [$a['popular'], $a['installed'], $b['name']]);

        return $cards;
    }

    /** The egg behind a card key (egg:12), or an egg id. */
    public static function find(mixed $id): ?Egg
    {
        $id = is_string($id) && str_starts_with($id, 'egg:') ? substr($id, 4) : $id;
        $egg = is_numeric($id) ? self::query()->find((int) $id) : null;

        return $egg && ! Minecraft::isMinecraftEgg((string) $egg->name) ? $egg : null;
    }

    /** One line of an egg's description, for a card. */
    public static function summary(string $description): string
    {
        return mb_strimwidth(trim((string) preg_replace('/\s+/', ' ', $description)), 0, 140, '…');
    }
}
