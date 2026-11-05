<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Creation;

use App\Models\Allocation;
use App\Models\Server;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Extra ports for a server (for a voice chat, a map or a query port): the Server Maker picks free ports of the same
 * node, right after the main port when it can, and takes the newest ones away again. The main port never changes.
 */
final class ExtraPorts
{
    /** The most extra ports a server can get, also for administrators. */
    public const MAX = 10;

    /** @return list<Allocation> the server's ports, the main one first */
    public static function of(Server $server): array
    {
        return self::allocations()->where('server_id', $server->id)->get()
            ->sortBy(fn (Allocation $allocation) => [(int) $allocation->id !== (int) $server->allocation_id, (int) $allocation->port])
            ->values()
            ->all();
    }

    /**
     * In the server panel Filament scopes allocations to the server; free ports belong to no server, so no scopes.
     *
     * @return Builder<Allocation>
     */
    private static function allocations(): Builder
    {
        return Allocation::withoutGlobalScopes();
    }

    public static function count(Server $server): int
    {
        return max(0, count(self::of($server)) - 1);
    }

    /** Gives the server exactly $count extra ports, under the Server Maker's lock so two servers never get the same port. */
    public static function set(Server $server, int $count): void
    {
        try {
            Cache::lock('simplify-servermaker.create', 30)->block(10, fn () => DB::transaction(function () use ($server, $count): void {
                $main = self::allocations()->whereKey($server->allocation_id)->first();
                $extra = self::allocations()->where('server_id', $server->id)->whereKeyNot((int) $server->allocation_id)
                    ->orderByDesc('updated_at')->orderByDesc('id')->lockForUpdate()->get();
                if ($extra->count() > $count) {
                    self::allocations()->whereKey($extra->take($extra->count() - $count)->pluck('id'))
                        ->update(['server_id' => null]);

                    return;
                }
                $free = Ports::free($server->node_id)->when($main, fn ($query) => $query->where('ip', $main->ip))
                    ->lockForUpdate()->pluck('port', 'id')->all();
                $picked = self::pick((int) $main?->port, $free, $count - $extra->count());
                if (count($picked) < $count - $extra->count()) {
                    throw ValidationException::withMessages(['data.extra_ports' => __('simplify-servermaker::maker.performance.no_ports')]);
                }
                self::allocations()->whereKey($picked)->update(['server_id' => $server->id]);
            }));
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['data.extra_ports' => __('simplify-servermaker::maker.review.busy')]);
        }
    }

    /**
     * Pure: the free ports to add, those right after the main port first, then the nearest below it.
     *
     * @param  array<int, int|string>  $free  allocation id => port
     * @return list<int> allocation ids
     */
    public static function pick(int $mainPort, array $free, int $needed): array
    {
        $order = [];
        foreach ($free as $id => $port) {
            $port = (int) $port;
            $order[(int) $id] = [$port < $mainPort, abs($port - $mainPort)];
        }
        asort($order);

        return array_slice(array_keys($order), 0, max(0, $needed));
    }
}
