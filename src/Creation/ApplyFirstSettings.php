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
use RuntimeException;
use Simplify\ServerMaker\Models\WaitingSettings;
use Throwable;

/**
 * Writes the first settings of a new server (server.properties values, server-icon.png and the EULA the user accepted)
 * once Pelican has installed it. The settings wait in the database and Pelican's scheduler writes them: it runs every
 * minute in a fresh process, so this needs no queue worker, and a worker that was started before the plugin was
 * installed cannot get in the way. After two hours without a finished installation the settings are dropped.
 *
 * Still a job, only for the ones 2.0.0-beta.10 and older left in the queue: such a job hands its settings over.
 */
final class ApplyFirstSettings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    private const HOURS = 2;

    public int $tries = 3;

    public int $timeout = 60;

    /** @param  array{properties?: array<string, string>, icon?: string, eula?: bool}  $settings */
    public function __construct(public int $serverId, public array $settings) {}

    public function handle(): void
    {
        self::keep($this->serverId, $this->settings);
    }

    /**
     * Keeps the settings of a server that was just made, for the scheduler. Never throws: the server is already
     * there, so a problem here is reported and the Server Maker carries on to the server page.
     *
     * @param  array{properties?: array<string, string>, icon?: string, eula?: bool}  $settings
     */
    public static function afterInstall(Server $server, array $settings): void
    {
        try {
            self::keep((int) $server->id, $settings);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Writes the settings of every server whose installation finished, and drops the ones that waited too long.
     *
     * @return int how many servers got their settings
     */
    public static function writeDue(): int
    {
        $written = 0;
        foreach (WaitingSettings::query()->get() as $waiting) {
            $server = Server::query()->find($waiting->server_id);
            if ($server === null || $waiting->created_at->lt(now()->subHours(self::HOURS))) {
                if ($server !== null) {
                    report(new RuntimeException('The first settings of server '.$server->uuid.' were not written: it was not installed and reachable within two hours.'));
                }
                $waiting->delete();

                continue;
            }
            // Only a finished installation has installed_at and no status; anything else waits for the next minute.
            if (! $server->installed_at || $server->status !== null) {
                continue;
            }
            try {
                self::write($server, $waiting->settings);
                $waiting->delete();
                $written++;
            } catch (Throwable) {
                // Wings did not take the files: the next minute tries again, until the two hours are over.
            }
        }

        return $written;
    }

    /** @param  array{properties?: array<string, string>, icon?: string, eula?: bool}  $settings */
    private static function keep(int $serverId, array $settings): void
    {
        if ($settings !== []) {
            WaitingSettings::query()->updateOrCreate(['server_id' => $serverId], ['settings' => $settings]);
        }
    }

    /** @param  array{properties?: array<string, string>, icon?: string, eula?: bool}  $settings */
    private static function write(Server $server, array $settings): void
    {
        $files = app(DaemonFileRepository::class)->setServer($server);
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
        if (($settings['eula'] ?? false) === true) {
            $files->putContent('eula.txt', "# Accepted in the Simplify Server Maker (https://www.minecraft.net/eula).\neula=true\n")->throw();
        }
    }
}
