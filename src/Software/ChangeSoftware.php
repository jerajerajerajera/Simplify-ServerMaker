<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Software;

use App\Models\Server;
use App\Services\Servers\ReinstallServerService;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Installs other Minecraft software on an existing server, as in Simplify 0.7.0: only while it is stopped and right
 * after a successful backup. The old startup settings are kept on the panel and put back when the reinstall fails.
 */
final class ChangeSoftware
{
    /** Why the software cannot be changed now (a key under software.blocked), or null. */
    public static function blocked(Server $server): ?string
    {
        if ($server->status !== null || ! $server->installed_at) {
            return 'installing';
        }
        try {
            $state = $server->retrieveStatus()->value;
        } catch (Throwable) {
            $state = 'unknown';
        }
        if ($state !== 'offline') {
            return 'running';
        }
        $backup = $server->backups()
            ->where('is_successful', true)
            ->whereNotNull('completed_at')
            ->where('created_at', '>=', now()->subMinutes(30))
            ->exists();

        return $backup ? null : 'backup';
    }

    /** @param  array{egg_id: int, image: string, startup: string, environment: array<string, string>}  $startup */
    public static function apply(Server $server, array $startup): void
    {
        $old = Startup::of($server);
        Storage::disk('local')->put(
            'simplify-servermaker/software/'.$server->uuid.'/'.now()->format('Ymd-His').'.json',
            json_encode($old, JSON_THROW_ON_ERROR),
        );
        Startup::write($server, $startup);
        try {
            app(ReinstallServerService::class)->handle($server->fresh());
        } catch (Throwable $exception) {
            Startup::write($server->fresh(), $old);
            throw $exception;
        }
    }
}
