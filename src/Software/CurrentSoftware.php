<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Software;

use Simplify\ServerMaker\Games\BundledEggs;
use Simplify\ServerMaker\Games\Minecraft;

/**
 * Which Minecraft Java software a server runs, read from its egg's name and variables. No database. Bedrock and
 * proxies are left out, as in Simplify 0.7.0: their files do not fit other software.
 */
final class CurrentSoftware
{
    public const VERSION_KEYS = ['MINECRAFT_VERSION', 'VANILLA_VERSION', 'MC_VERSION'];

    /** Software of a type with a choice, by the key into Minecraft::EGGS. */
    private const TYPES = [
        'vanilla' => 'vanilla',
        'paper' => 'plugins', 'purpur' => 'plugins',
        'fabric' => 'mods', 'forge' => 'mods', 'neoforge' => 'mods', 'quilt' => 'mods',
    ];

    /**
     * The type and software of a Minecraft Java egg: a known type, "modpack" for pack eggs, "other" for other Java
     * software such as Spigot; null for eggs that are not Minecraft Java.
     *
     * @return array{type: string, software: ?string}|null
     */
    public static function of(string $eggName): ?array
    {
        $name = mb_strtolower(trim($eggName));
        foreach (self::TYPES as $flavour => $type) {
            if (in_array($name, Minecraft::EGGS[$flavour][0], true)) {
                return ['type' => $type, 'software' => $type === $flavour ? null : $flavour];
            }
        }
        if (preg_match('/\b(bedrock|pocketmine(-mp)?|nukkit|velocity|bungeecord|waterfall)\b/', $name)) {
            return null;
        }
        // The other bundled eggs are all modpacks, also those without a telling name (The 1.12.2 Pack).
        $bundled = in_array($name, array_map(fn (array $egg) => mb_strtolower($egg['name']), BundledEggs::manifest()), true);
        if (! $bundled && ! Minecraft::isMinecraftEgg($name)) {
            return null;
        }
        $pack = $bundled || preg_match('/\b(modrinth|curseforge|ftb|feed the beast|tekkit\w*|hexxit|blightfall|b-team)\b/', $name);

        return ['type' => $pack ? 'modpack' : 'other', 'software' => null];
    }

    /** @param  array<string, string>  $environment */
    public static function version(array $environment): ?string
    {
        foreach (self::VERSION_KEYS as $key) {
            if (Minecraft::validVersion((string) ($environment[$key] ?? ''))) {
                return $environment[$key];
            }
        }

        return null;
    }
}
