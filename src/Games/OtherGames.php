<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Games;

use App\Models\Egg;
use App\Models\Server;
use Illuminate\Database\Eloquent\Builder;

/** Every other game: the eggs on the panel that the Minecraft part does not cover. */
final class OtherGames
{
    /** @return Builder<Egg> */
    public static function query(): Builder
    {
        return Egg::query()->orderBy('name');
    }

    /**
     * Cards for the game picker, most used first.
     *
     * @return list<array{id: int, name: string, description: string}>
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
                'id' => (int) $egg->id,
                'name' => (string) $egg->name,
                'description' => self::summary((string) $egg->description),
                'used' => (int) ($used[$egg->id] ?? 0),
            ];
        }
        usort($cards, fn (array $a, array $b) => [$b['used'], $a['name']] <=> [$a['used'], $b['name']]);

        return array_map(fn (array $card) => array_diff_key($card, ['used' => true]), $cards);
    }

    public static function find(mixed $id): ?Egg
    {
        $egg = is_numeric($id) ? self::query()->find((int) $id) : null;

        return $egg && ! Minecraft::isMinecraftEgg((string) $egg->name) ? $egg : null;
    }

    /** One line of an egg's description, for a card. */
    public static function summary(string $description): string
    {
        return mb_strimwidth(trim((string) preg_replace('/\s+/', ' ', $description)), 0, 140, '…');
    }
}
