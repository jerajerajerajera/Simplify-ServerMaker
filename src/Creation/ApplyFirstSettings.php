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
use Throwable;

/**
 * Writes the first settings of a new server (server.properties values and server-icon.png) once Pelican has
 * installed it. Waits for the installation by trying again every 30 seconds, for up to two hours. The settings
 * travel with the job itself, so nothing else has to exist on the panel.
 */
final class ApplyFirstSettings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 240;

    public int $timeout = 60;

    /** @param  array{properties?: array<string, string>, icon?: string}  $settings */
    public function __construct(public int $serverId, public array $settings) {}

    /**
     * Queues the settings for a server that was just made. Not named queue(): Laravel's dispatcher calls a job's
     * queue($queue, $job) method when it has one. Never throws: the server is already there, so a
     * problem here is reported and the Server Maker carries on to the server page.
     *
     * @param  array{properties?: array<string, string>, icon?: string}  $settings
     */
    public static function afterInstall(Server $server, array $settings): void
    {
        // Without a queue worker the job would run right now, before the installation, and do nothing.
        if ($settings === [] || in_array(config('queue.default'), ['sync', 'null'], true)) {
            return;
        }
        try {
            self::dispatch($server->id, $settings)->delay(now()->addSeconds(20));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function handle(DaemonFileRepository $files): void
    {
        $server = Server::query()->find($this->serverId);
        if (! $server) {
            return;
        }
        // Only a finished installation has installed_at and no status; anything else is tried again later.
        if (! $server->installed_at || $server->status !== null) {
            $this->release(30);

            return;
        }
        $files->setServer($server);
        if (($this->settings['properties'] ?? []) !== []) {
            try {
                $original = $files->getContent('server.properties', 1048576);
            } catch (FileNotFoundException) {
                $original = '';
            }
            $files->putContent('server.properties', FirstSettings::merge($original, $this->settings['properties']))->throw();
        }
        if (filled($this->settings['icon'] ?? null)) {
            $files->putContent('server-icon.png', (string) base64_decode((string) $this->settings['icon'], true))->throw();
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception) {
            report($exception);
        }
    }
}
