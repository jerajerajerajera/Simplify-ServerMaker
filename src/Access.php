<?php

declare(strict_types=1);

namespace Simplify\ServerMaker;

use App\Models\Egg;
use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Who may use the Server Maker, where and how much. Administrators (who may create servers in Pelican) may do
 * everything. Other people only when self-service is on in the settings, for their role, on the chosen nodes, with
 * the chosen games and within their allowance.
 */
final class Access
{
    public const LIMITS = [
        'max_servers' => 2,
        'max_ram' => 8,
        'max_disk' => 40,
        'max_cpu' => 200,
        'max_backups' => 2,
    ];

    public static function admin(?User $user = null): bool
    {
        $user ??= user();

        return (bool) $user?->can('create', Server::class);
    }

    public static function selfService(?User $user = null): bool
    {
        $user ??= user();
        if ($user === null || ! Settings::get('self_service', false) || Settings::get('node_ids', []) === []) {
            return false;
        }
        if (! Settings::get('minecraft', true) && Settings::get('egg_ids', []) === []) {
            return false;
        }
        $roles = array_map('intval', (array) Settings::get('role_ids', []));

        return $roles === [] || $user->roles()->whereIn('roles.id', $roles)->exists();
    }

    public static function canUse(?User $user = null): bool
    {
        return self::admin($user) || self::selfService($user);
    }

    /** @return Builder<Node> */
    public static function nodes(): Builder
    {
        $user = user();
        if ($user === null) {
            return Node::query()->whereRaw('1 = 0');
        }
        $query = $user->accessibleNodes();

        return self::admin() ? $query : $query->whereIn('id', array_map('intval', (array) Settings::get('node_ids', [])));
    }

    /** @return Builder<Egg> */
    public static function eggs(): Builder
    {
        $query = Egg::query()->orderBy('name');

        return self::admin() ? $query : $query->whereIn('id', array_map('intval', (array) Settings::get('egg_ids', [])));
    }

    public static function minecraft(): bool
    {
        return self::admin() || (bool) Settings::get('minecraft', true);
    }

    /** Whether a missing Minecraft or modpack egg may be added from the bundled eggs. */
    public static function mayImportEggs(): bool
    {
        return (bool) user()?->can('create', Egg::class) || (self::selfService() && (bool) Settings::get('import_eggs', false));
    }

    /** @return array{max_servers: int, max_ram: int, max_disk: int, max_cpu: int, max_backups: int} */
    public static function limits(): array
    {
        $limits = [];
        foreach (self::LIMITS as $key => $default) {
            $limits[$key] = max(0, (int) Settings::get($key, $default));
        }

        return $limits;
    }

    /**
     * What one person still has left: servers, RAM and disk over all their servers.
     *
     * @return array{servers: int, ram: int, disk: int, cpu: int, backups: int} RAM in GB, disk in MB
     */
    public static function remaining(User $user): array
    {
        $limits = self::limits();
        $servers = Server::query()->where('owner_id', $user->id)->get(['memory', 'disk']);

        return self::left($limits, $servers->count(), (int) $servers->sum('memory'), (int) $servers->sum('disk'));
    }

    /** Pure: the allowance minus what is in use (memory and disk in MB). */
    public static function left(array $limits, int $count, int $memory, int $disk): array
    {
        return [
            'servers' => max(0, $limits['max_servers'] - $count),
            'ram' => max(0, intdiv($limits['max_ram'] * 1024 - $memory, 1024)),
            'disk' => max(0, $limits['max_disk'] * 1024 - $disk),
            'cpu' => $limits['max_cpu'],
            'backups' => $limits['max_backups'],
        ];
    }

    /** The upper limits for the resource fields: the allowance for users, the plain maximum for administrators. */
    public static function resourceLimits(): ?array
    {
        if (self::admin()) {
            return null;
        }
        $left = self::remaining(user());

        return ['ram' => $left['ram'], 'cpu' => $left['cpu'], 'disk' => $left['disk']];
    }

    /** Pure: refuses a server that does not fit in what is left. Run again right before the server is made. */
    public static function check(array $payload, array $left): void
    {
        $fail = fn (string $field, string $key, array $replace = []) => throw ValidationException::withMessages([
            'data.'.$field => __('simplify-server-maker::maker.access.'.$key, $replace),
        ]);
        if ($left['servers'] < 1) {
            $fail('name', 'no_servers_left');
        }
        if ($payload['memory'] > $left['ram'] * 1024) {
            $fail('ram', 'no_ram_left', ['amount' => $left['ram']]);
        }
        if ($payload['disk'] > $left['disk']) {
            $fail('disk', 'no_disk_left', ['amount' => rtrim(rtrim(number_format($left['disk'] / 1024, 1, '.', ''), '0'), '.')]);
        }
        if ($payload['cpu'] > $left['cpu']) {
            $fail('cpu', 'too_much_cpu', ['amount' => $left['cpu']]);
        }
        if ($payload['backup_limit'] > $left['backups']) {
            $fail('backups', 'too_many_backups', ['amount' => $left['backups']]);
        }
    }
}
