<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Creation;

use Simplify\ServerMaker\Games\Minecraft;
use Simplify\ServerMaker\Settings;

/**
 * The CPU, RAM and disk a new server starts with: per Minecraft type as the administrator set them in the settings,
 * else the built-in defaults. CPU in %, RAM in GB, disk in MB.
 */
final class StartingResources
{
    /** The defaults for games other than Minecraft. */
    public const OTHER = ['cpu' => 100, 'ram' => 4, 'disk' => 10240];

    /** Allowed range per resource, as in the Resources step. */
    public const RANGE = ['cpu' => [1, 1600], 'ram' => [1, 128], 'disk' => [1024, 10485760]];

    /** @return array{cpu: int, ram: int, disk: int} null for games other than Minecraft */
    public static function for(?string $type): array
    {
        if ($type === null) {
            return self::OTHER;
        }

        return self::pick($type, (array) Settings::get('starting_resources', []));
    }

    /**
     * The saved values for a type where they are valid, else the defaults.
     *
     * @param  array<string, mixed>  $saved  type => [cpu, ram, disk]
     * @return array{cpu: int, ram: int, disk: int}
     */
    public static function pick(string $type, array $saved): array
    {
        $values = Minecraft::resources($type);
        foreach (self::RANGE as $resource => [$min, $max]) {
            $value = $saved[$type][$resource] ?? null;
            if (is_int($value) && $value >= $min && $value <= $max) {
                $values[$resource] = $value;
            }
        }

        return $values;
    }

    /**
     * The settings form's table (disk in GB) as saved values (disk in MB), clamped to the allowed range.
     *
     * @param  array<string, mixed>  $table  type => [cpu, ram, disk in GB]
     * @return array<string, array{cpu: int, ram: int, disk: int}>
     */
    public static function fromTable(array $table): array
    {
        $saved = [];
        foreach (Minecraft::TYPES as $type) {
            $row = (array) ($table[$type] ?? []);
            foreach (self::RANGE as $resource => [$min, $max]) {
                $value = (int) ($row[$resource] ?? 0) * ($resource === 'disk' ? 1024 : 1);
                $saved[$type][$resource] = $value > 0 ? max($min, min($max, $value)) : Minecraft::resources($type)[$resource];
            }
        }

        return $saved;
    }

    /** @return array<string, array{cpu: int, ram: int, disk: int}> type => values with disk in GB, for the settings form */
    public static function table(): array
    {
        $table = [];
        foreach (Minecraft::TYPES as $type) {
            $values = self::for($type);
            $table[$type] = ['cpu' => $values['cpu'], 'ram' => $values['ram'], 'disk' => intdiv($values['disk'], 1024)];
        }

        return $table;
    }
}
