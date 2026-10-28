<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Creation;

use Illuminate\Validation\ValidationException;

/**
 * RAM, CPU and disk: the presets the Server Maker offers and the checks before a server is made. Pure.
 *
 * In the form RAM is in GB, CPU in % (100 = one core) and disk in MB. Each resource is a preset or "custom" with
 * its own number field.
 */
final class Resources
{
    public const RAM = [2, 4, 6, 8, 12, 16];

    public const CPU = [100, 150, 200, 300, 400];

    public const DISK = [5120, 10240, 20480, 40960];

    /** Upper limits for administrators. Users get their allowance instead. */
    public const MAX = ['ram' => 128, 'cpu' => 1600, 'disk' => 10485760];

    /**
     * The presets up to a limit, with the starting value always among them.
     *
     * @return list<int>
     */
    public static function presets(string $resource, int $start, ?int $limit = null): array
    {
        $values = match ($resource) {
            'ram' => self::RAM,
            'cpu' => self::CPU,
            default => self::DISK,
        };
        $limit ??= self::MAX[$resource];
        $values = array_values(array_unique([...$values, min($start, $limit)]));
        sort($values);

        return array_values(array_filter($values, fn (int $value) => $value >= 1 && $value <= $limit));
    }

    /** The form value for a starting amount: the preset itself, or "custom" when it is not one. */
    public static function choice(int $value, array $presets): string
    {
        return in_array($value, $presets, true) ? (string) $value : 'custom';
    }

    /**
     * The amounts for Pelican (memory and disk in MB, CPU in %), after checking them against the limits.
     *
     * @param  array<string, mixed>  $state
     * @param  array{ram: int, cpu: int, disk: int}|null  $limits  null for administrators
     * @return array{memory: int, cpu: int, disk: int}
     */
    public static function payload(array $state, ?array $limits = null): array
    {
        $ram = self::amount($state, 'ram');
        $cpu = self::amount($state, 'cpu');
        $disk = self::amount($state, 'disk');
        $limits ??= self::MAX;
        if ($ram < 1 || $ram > $limits['ram']) {
            self::fail('ram', __('simplify-server-maker::maker.resources.ram_range', ['max' => $limits['ram']]));
        }
        if ($cpu < 1 || $cpu > $limits['cpu']) {
            self::fail('cpu', __('simplify-server-maker::maker.resources.cpu_range', ['max' => $limits['cpu']]));
        }
        if ($disk < 1024 || $disk > $limits['disk']) {
            self::fail('disk', __('simplify-server-maker::maker.resources.disk_range', ['max' => self::gb($limits['disk'])]));
        }

        return ['memory' => $ram * 1024, 'cpu' => $cpu, 'disk' => $disk];
    }

    /** The chosen amount: the preset, or the custom field when "custom" is picked. */
    public static function amount(array $state, string $resource): int
    {
        $choice = (string) ($state[$resource] ?? '');
        $value = $choice === 'custom' ? ($state[$resource.'_custom'] ?? null) : $choice;

        return filter_var($value, FILTER_VALIDATE_INT) === false ? 0 : (int) $value;
    }

    public static function gb(int $megabytes): string
    {
        return rtrim(rtrim(number_format($megabytes / 1024, 1, '.', ''), '0'), '.');
    }

    private static function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages(['data.'.$field => $message]);
    }
}
