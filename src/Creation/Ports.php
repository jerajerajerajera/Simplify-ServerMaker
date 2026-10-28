<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Creation;

use App\Models\Allocation;
use App\Models\Node;
use Illuminate\Database\Eloquent\Builder;
use Simplify\ServerMaker\Access;

/** Nodes and free ports (allocations) for a new server. */
final class Ports
{
    /** @return Builder<Node> */
    public static function nodes(): Builder
    {
        return Access::nodes();
    }

    /** @return Builder<Allocation> */
    public static function free(?int $nodeId): Builder
    {
        return Allocation::query()->where('node_id', (int) $nodeId)->whereNull('server_id')->orderBy('port');
    }

    public static function first(?int $nodeId): ?int
    {
        $id = self::free($nodeId)->value('id');

        return $id === null ? null : (int) $id;
    }

    /** @return array<int, string> allocation id => ip:port */
    public static function options(?int $nodeId): array
    {
        return self::free($nodeId)->limit(500)->get()
            ->mapWithKeys(fn (Allocation $allocation) => [$allocation->id => $allocation->address])
            ->all();
    }
}
