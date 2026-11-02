<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Software;

use App\Models\EggVariable;
use App\Models\Server;
use App\Models\ServerVariable;
use Illuminate\Support\Facades\DB;

/** A server's egg, Docker image, startup command and variables: read, and written as one change. */
final class Startup
{
    /** @return array{egg_id: int, image: string, startup: string, environment: array<string, string>} */
    public static function of(Server $server): array
    {
        $values = ServerVariable::query()->where('server_id', $server->id)->pluck('variable_value', 'variable_id');
        $environment = [];
        foreach (EggVariable::query()->where('egg_id', $server->egg_id)->get() as $variable) {
            $environment[(string) $variable->env_variable] = (string) ($values[$variable->id] ?? $variable->default_value ?? '');
        }

        return [
            'egg_id' => (int) $server->egg_id,
            'image' => (string) $server->image,
            'startup' => (string) $server->startup,
            'environment' => $environment,
        ];
    }

    /**
     * Variables the egg does not have are left out.
     *
     * @param  array{egg_id: int, image: string, startup: string, environment: array<string, string>}  $startup
     */
    public static function write(Server $server, array $startup): void
    {
        DB::transaction(function () use ($server, $startup): void {
            $server->forceFill([
                'egg_id' => $startup['egg_id'],
                'image' => $startup['image'],
                'startup' => $startup['startup'],
                'skip_scripts' => false,
            ])->save();
            ServerVariable::query()->where('server_id', $server->id)->delete();
            foreach (EggVariable::query()->where('egg_id', $startup['egg_id'])->get() as $variable) {
                ServerVariable::query()->create([
                    'server_id' => $server->id,
                    'variable_id' => $variable->id,
                    'variable_value' => (string) ($startup['environment'][$variable->env_variable] ?? $variable->default_value ?? ''),
                ]);
            }
        });
    }
}
