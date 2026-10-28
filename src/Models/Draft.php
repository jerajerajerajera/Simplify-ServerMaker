<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The answers of a Server Maker someone left halfway, one per person, so they can carry on later.
 *
 * @property int $user_id
 * @property array<string, mixed> $data
 * @property int $step
 */
final class Draft extends Model
{
    protected $table = 'simplify_server_maker_drafts';

    protected $fillable = ['user_id', 'data', 'step'];

    protected function casts(): array
    {
        return ['data' => 'encrypted:array', 'step' => 'integer'];
    }

    public static function for(int $userId): ?self
    {
        return self::query()->where('user_id', $userId)->first();
    }

    /** Keeps only plain values: uploads and objects never end up in a draft. */
    public static function clean(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $clean[$key] = self::clean($value);
            } elseif ($value === null || is_scalar($value)) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
