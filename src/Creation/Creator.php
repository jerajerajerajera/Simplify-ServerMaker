<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Creation;

use App\Models\Allocation;
use App\Models\Node;
use App\Models\Server;
use App\Services\Servers\ServerCreationService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Makes the server with Pelican's own ServerCreationService, after checking that the node has room and the port is
 * still free. One Server Maker at a time, so two people never get the same port or the last bit of room.
 */
final class Creator
{
    /**
     * @param  array<string, mixed>  $payload  from Payload::build()
     * @param  callable(): void|null  $checkAllowance  throws when a user would go over their allowance
     */
    public static function create(array $payload, ?callable $checkAllowance = null): Server
    {
        try {
            return Cache::lock('simplify-server-maker.create', 30)->block(10, function () use ($payload, $checkAllowance): Server {
                $payload = DB::transaction(function () use ($payload, $checkAllowance): array {
                    $node = Node::query()->whereKey($payload['node_id'])->lockForUpdate()->first();
                    if (! $node || ! Ports::nodes()->whereKey($node->id)->exists()) {
                        self::fail('node_id', 'server.node_denied');
                    }
                    if (! $node->isViable((int) $payload['memory'], (int) $payload['disk'], (int) $payload['cpu'])) {
                        self::fail('ram', 'resources.no_room');
                    }
                    $allocation = Allocation::query()->whereKey($payload['allocation_id'])->lockForUpdate()->first();
                    if (! $allocation || $allocation->server_id !== null || (int) $allocation->node_id !== (int) $node->id) {
                        self::fail('allocation_id', 'server.port_taken');
                    }
                    if ($checkAllowance) {
                        $checkAllowance();
                    }

                    return $payload;
                });

                // Pelican saves the server before it asks Wings to make it, so this call runs outside our transaction.
                return app(ServerCreationService::class)->handle($payload);
            });
        } catch (LockTimeoutException) {
            self::fail('name', 'review.busy');
        }
    }

    private static function fail(string $field, string $key): never
    {
        throw ValidationException::withMessages(['data.'.$field => __('simplify-server-maker::maker.'.$key)]);
    }
}
