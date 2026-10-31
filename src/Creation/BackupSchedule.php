<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Creation;

use App\Models\Server;
use Throwable;

/**
 * An own automatic backup schedule for the new server (as in Simplify 0.7.0), through Simplify Toolkit when it is
 * installed and its automatic backups are on. Without the switch the server keeps the panel's schedule, which
 * Toolkit gives every new server by itself.
 */
final class BackupSchedule
{
    private const API = 'Simplify\\Toolkit\\Services\\AutomaticBackups';

    public static function available(): bool
    {
        try {
            $api = self::API;

            return class_exists($api) && (bool) $api::panel()->enabled;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array{backup_every: int, backup_keep: int, backup_only_when_used: bool} the panel's schedule */
    public static function defaults(): array
    {
        $api = self::API;
        $panel = self::available() ? $api::panel() : null;

        return [
            'backup_every' => (int) ($panel->every_minutes ?? 360),
            'backup_keep' => (int) ($panel->keep ?? 2),
            'backup_only_when_used' => (bool) ($panel->only_when_used ?? true),
        ];
    }

    /** @param  array<string, mixed>  $state */
    public static function apply(Server $server, array $state): void
    {
        if (! ($state['backup_schedule'] ?? false) || ! self::available()) {
            return;
        }
        $api = self::API;
        $api::save($server, [
            'enabled' => true,
            'every_minutes' => (int) ($state['backup_every'] ?? 0),
            'keep' => (int) ($state['backup_keep'] ?? 0),
            'only_when_used' => (bool) ($state['backup_only_when_used'] ?? true),
        ]);
    }
}
