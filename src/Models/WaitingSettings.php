<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The first settings of a new server, kept until Pelican has installed it (ApplyFirstSettings).
 *
 * @property int $server_id
 * @property array{properties?: array<string, string>, icon?: string, eula?: bool} $settings
 * @property Carbon $created_at
 */
final class WaitingSettings extends Model
{
    protected $table = 'simplify_server_maker_first_settings';

    protected $fillable = ['server_id', 'settings'];

    protected function casts(): array
    {
        return ['settings' => 'encrypted:array'];
    }
}
