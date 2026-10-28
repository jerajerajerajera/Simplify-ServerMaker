<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Games;

/**
 * The kinds of Minecraft server the Server Maker offers, and which bundled egg each one uses. Pure: no database.
 */
final class Minecraft
{
    /** Server types in the order the Server Maker shows them. */
    public const TYPES = ['vanilla', 'plugins', 'mods', 'modpack', 'bedrock', 'proxy'];

    /** The software to pick within a type; the first one is the default. */
    public const SOFTWARE = [
        'plugins' => ['paper' => 'Paper', 'purpur' => 'Purpur'],
        'mods' => ['fabric' => 'Fabric', 'forge' => 'Forge', 'neoforge' => 'NeoForge', 'quilt' => 'Quilt'],
        'proxy' => ['velocity' => 'Velocity', 'bungeecord' => 'BungeeCord'],
    ];

    /** Egg names to look for on the panel (lower case), and the bundled egg imported when none is there. */
    public const EGGS = [
        'vanilla' => [['vanilla minecraft', 'minecraft vanilla', 'vanilla'], 'egg-vanilla-minecraft.json'],
        'paper' => [['paper'], 'egg-paper.json'],
        'purpur' => [['purpur'], 'egg-purpur.json'],
        'fabric' => [['fabric'], 'egg-fabric.json'],
        'forge' => [['forge minecraft', 'forge'], 'egg-forge.json'],
        'neoforge' => [['neoforge'], 'egg-neoforge.json'],
        'quilt' => [['quilt'], 'egg-quilt.json'],
        'bedrock' => [['vanilla bedrock', 'bedrock'], 'egg-vanilla-bedrock.json'],
        'velocity' => [['velocity'], 'egg-velocity.json'],
        'bungeecord' => [['bungeecord'], 'egg-bungeecord.json'],
    ];

    /** Starting resources per type: CPU in %, RAM in GB, disk in MB. */
    public const RESOURCES = [
        'vanilla' => ['cpu' => 100, 'ram' => 4, 'disk' => 10240],
        'plugins' => ['cpu' => 150, 'ram' => 4, 'disk' => 10240],
        'mods' => ['cpu' => 200, 'ram' => 4, 'disk' => 20480],
        'modpack' => ['cpu' => 200, 'ram' => 6, 'disk' => 20480],
        'bedrock' => ['cpu' => 100, 'ram' => 2, 'disk' => 10240],
        'proxy' => ['cpu' => 100, 'ram' => 2, 'disk' => 5120],
    ];

    public static function isType(?string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }

    /** The chosen software when the type offers a choice, else the type's default; null for types without a choice. */
    public static function software(string $type, ?string $software): ?string
    {
        $choices = self::SOFTWARE[$type] ?? null;
        if ($choices === null) {
            return null;
        }

        return isset($choices[(string) $software]) ? (string) $software : array_key_first($choices);
    }

    /** The key into EGGS: the software for types with a choice, the type itself otherwise. */
    public static function flavour(string $type, ?string $software): ?string
    {
        if (! self::isType($type) || $type === 'modpack') {
            return null;
        }

        return self::software($type, $software) ?? $type;
    }

    /** @return list<string> */
    public static function eggNames(string $type, ?string $software): array
    {
        return self::EGGS[self::flavour($type, $software) ?? ''][0] ?? [];
    }

    public static function eggFile(string $type, ?string $software): ?string
    {
        return self::EGGS[self::flavour($type, $software) ?? ''][1] ?? null;
    }

    /** Types that run a Minecraft Java release you choose; the others bring their own version. */
    public static function usesVersion(string $type): bool
    {
        return in_array($type, ['vanilla', 'plugins', 'mods'], true);
    }

    /** Java servers, with server.properties and the EULA. Bedrock and proxies have neither. */
    public static function isJava(string $type): bool
    {
        return self::isType($type) && ! in_array($type, ['bedrock', 'proxy'], true);
    }

    /** @return array{cpu: int, ram: int, disk: int} */
    public static function resources(string $type): array
    {
        return self::RESOURCES[$type] ?? self::RESOURCES['vanilla'];
    }

    /** Eggs the Minecraft part already covers, so the list of other games does not offer them a second time. */
    public static function isMinecraftEgg(string $name): bool
    {
        // Whole words, so games such as Paperboy or Arma Reforger stay in the list.
        return preg_match(
            '/\b(minecraft|vanilla|paper|purpur|spigot|bukkit|folia|sponge\w*|modrinth|curseforge|ftb|feed the beast'
            .'|tekkit\w*|hexxit|blightfall|b-team|forge|neoforge|fabric|quilt|bedrock|pocketmine(-mp)?|nukkit'
            .'|velocity|bungeecord|waterfall)\b/i',
            $name,
        ) === 1;
    }

    /**
     * The egg's variables with the chosen Minecraft version filled in.
     *
     * @param  array<string, string>  $defaults  env variable => default value
     * @return array<string, string>
     */
    public static function environment(array $defaults, string $type, ?string $version): array
    {
        if (self::usesVersion($type) && $version !== null) {
            foreach (['MINECRAFT_VERSION', 'VANILLA_VERSION', 'MC_VERSION'] as $key) {
                if (array_key_exists($key, $defaults)) {
                    $defaults[$key] = $version;
                }
            }
        }

        return $defaults;
    }

    public static function validVersion(string $version): bool
    {
        return preg_match('/^\d+\.\d+(\.\d+)?$/', $version) === 1;
    }
}
