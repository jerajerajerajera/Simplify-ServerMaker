<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Creation;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Writes the first settings of a new server (server.properties values and server-icon.png) once Pelican has
 * installed it. Waits for the installation by trying again every 30 seconds, for up to two hours.
 */
final class ApplyFirstSettings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    private const TABLE = 'simplify_server_maker_first_settings';

    public int $tries = 240;

    public int $timeout = 60;

    public function __construct(public int $serverId) {}

    /** @param  array{properties?: array<string, string>, icon?: string}  $settings */
    public static function queue(Server $server, array $settings): void
    {
        if ($settings === []) {
            return;
        }
        DB::table(self::TABLE)->updateOrInsert(
            ['server_id' => $server->id],
            ['settings' => json_encode($settings, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()],
        );
        self::dispatch($server->id)->delay(now()->addSeconds(20));
    }

    public function handle(DaemonFileRepository $files): void
    {
        $row = DB::table(self::TABLE)->where('server_id', $this->serverId)->first();
        $server = Server::query()->find($this->serverId);
        if (! $row || ! $server) {
            DB::table(self::TABLE)->where('server_id', $this->serverId)->delete();

            return;
        }
        // Only a finished installation has installed_at and no status; anything else is tried again later.
        if (! $server->installed_at || $server->status !== null) {
            $this->release(30);

            return;
        }
        $settings = json_decode((string) $row->settings, true) ?: [];
        $files->setServer($server);
        if (($settings['properties'] ?? []) !== []) {
            try {
                $original = $files->getContent('server.properties', 1048576);
            } catch (FileNotFoundException) {
                $original = '';
            }
            $files->putContent('server.properties', FirstSettings::merge($original, $settings['properties']))->throw();
        }
        if (filled($settings['icon'] ?? null)) {
            $files->putContent('server-icon.png', (string) base64_decode((string) $settings['icon'], true))->throw();
        }
        DB::table(self::TABLE)->where('server_id', $this->serverId)->delete();
    }

    public function failed(?Throwable $exception): void
    {
        DB::table(self::TABLE)->where('server_id', $this->serverId)->delete();
        if ($exception) {
            report($exception);
        }
    }
}
