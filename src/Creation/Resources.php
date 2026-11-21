<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Creation;

use Illuminate\Validation\ValidationException;

/**
 * RAM, CPU and disk: the presets the Server Maker offers and the checks before a server is made. Pure.
 *
 * In the form RAM is in GB, CPU in % (100 = one core) and disk in MB. Each resource is a preset or "custom" with
 * its own number field; the custom disk is typed in whole GB, like RAM.
 */
final class Resources
{
    public const RAM = [2, 4, 6, 8, 12, 16];

    public const CPU = [100, 150, 200, 300, 400];

    public const DISK = [5120, 10240, 20480, 40960];

    /** Upper limits for administrators. Users get their allowance instead. */
    public const MAX = ['ram' => 128, 'cpu' => 1600, 'disk' => 10485760];

    /** Marks a saved draft whose custom disk is in GB; a draft without it is older and holds MB. */
    public const DRAFT_GB = 'disk_in_gb';

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
            self::fail('ram', __('simplify-servermaker::maker.resources.ram_range', ['max' => $limits['ram']]));
        }
        if ($cpu < 1 || $cpu > $limits['cpu']) {
            self::fail('cpu', __('simplify-servermaker::maker.resources.cpu_range', ['max' => $limits['cpu']]));
        }
        if ($disk < 1024 || $disk > $limits['disk']) {
            self::fail('disk', __('simplify-servermaker::maker.resources.disk_range', ['max' => self::gb($limits['disk'])]));
        }

        return ['memory' => $ram * 1024, 'cpu' => $cpu, 'disk' => $disk];
    }

    /** The chosen amount: the preset, or the custom field (disk: in GB, returned in MB) when "custom" is picked. */
    public static function amount(array $state, string $resource): int
    {
        $choice = (string) ($state[$resource] ?? '');
        $value = $choice === 'custom' ? ($state[$resource.'_custom'] ?? null) : $choice;
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return 0;
        }

        return $choice === 'custom' && $resource === 'disk' ? (int) $value * 1024 : (int) $value;
    }

    /** An amount as the custom field shows it: disk in whole GB, RAM and CPU as they are. */
    public static function custom(string $resource, int $amount): int
    {
        return $resource === 'disk' ? max(1, intdiv($amount, 1024)) : $amount;
    }

    /**
     * A saved draft's answers for the form: the custom disk of an older draft, in MB, becomes whole GB.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fromDraft(array $data): array
    {
        $current = ($data[self::DRAFT_GB] ?? false) === true;
        unset($data[self::DRAFT_GB]);
        if (! $current && isset($data['disk_custom'])) {
            $data['disk_custom'] = self::custom('disk', (int) $data['disk_custom']);
        }

        return $data;
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
