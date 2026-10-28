<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Games;

use App\Models\Egg;

/** The panel's egg for a Minecraft type, imported from the bundled eggs when the panel has none yet. */
final class MinecraftEggs
{
    public static function find(string $type, ?string $software): ?Egg
    {
        foreach (Minecraft::eggNames($type, $software) as $name) {
            $egg = Egg::query()->whereRaw('LOWER(name) = ?', [$name])->first();
            if ($egg) {
                return $egg;
            }
        }

        return null;
    }

    public static function ensure(string $type, ?string $software): ?Egg
    {
        $egg = self::find($type, $software);
        $file = Minecraft::eggFile($type, $software);
        if ($egg || $file === null) {
            return $egg;
        }

        return BundledEggs::import($file, fn () => self::find($type, $software));
    }
}
