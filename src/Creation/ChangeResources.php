<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Creation;

use App\Models\Node;
use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * New RAM, CPU and disk limits for an existing server, as in Simplify 0.7.0: checked against the node and (for
 * people who are not administrators) their allowance under the Server Maker's lock, so two changes never share the
 * last bit of room. Other limits stay as they are. Wings gets them now and uses them from the next start.
 */
final class ChangeResources
{
    /**
     * @param  array{memory: int, cpu: int, disk: int}  $resources  memory and disk in MB
     * @param  callable(): void|null  $checkAllowance  throws when a user would go over their allowance
     */
    public static function apply(Server $server, array $resources, ?callable $checkAllowance = null): void
    {
        try {
            Cache::lock('simplify-servermaker.create', 30)->block(10, function () use ($server, $resources, $checkAllowance): void {
                DB::transaction(function () use ($server, $resources, $checkAllowance): void {
                    $server->refresh();
                    $node = Node::query()->whereKey($server->node_id)->lockForUpdate()->firstOrFail();
                    $more = fn (string $key) => max(0, $resources[$key] - (int) $server->{$key});
                    if (! $node->isViable($more('memory'), $more('disk'), $more('cpu'))) {
                        throw ValidationException::withMessages(['data.ram' => __('simplify-servermaker::maker.resources.no_room')]);
                    }
                    if ($checkAllowance) {
                        $checkAllowance();
                    }
                    $server->forceFill($resources)->save();
                });
            });
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['data.ram' => __('simplify-servermaker::maker.review.busy')]);
        }
        try {
            app(DaemonServerRepository::class)->setServer($server)->sync();
        } catch (Throwable $exception) {
            // Wings reads the limits again when the server starts.
            report($exception);
        }
    }
}
