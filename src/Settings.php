<?php

declare(strict_types=1);

namespace Simplify\ServerMaker;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The Server Maker's settings, in its own table. Secrets (the CurseForge key) are stored encrypted and never shown
 * again. Read once per request from a short cache.
 */
final class Settings
{
    public const SECRETS = ['curseforge_key'];

    private const TABLE = 'simplify_server_maker_settings';

    private const CACHE = 'simplify-server-maker.settings';

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all()) && self::all()[$key] !== '' && self::all()[$key] !== null;
    }

    /** @param  array<string, mixed>  $values  null removes a setting */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            if ($value === null) {
                DB::table(self::TABLE)->where('key', $key)->delete();

                continue;
            }
            $stored = json_encode($value, JSON_THROW_ON_ERROR);
            if (in_array($key, self::SECRETS, true)) {
                $stored = Crypt::encryptString($stored);
            }
            DB::table(self::TABLE)->updateOrInsert(['key' => $key], ['value' => $stored, 'updated_at' => now(), 'created_at' => now()]);
        }
        Cache::forget(self::CACHE);
    }

    /** @return array<string, mixed> */
    private static function all(): array
    {
        return Cache::remember(self::CACHE, 60, function (): array {
            $values = [];
            try {
                foreach (DB::table(self::TABLE)->get(['key', 'value']) as $row) {
                    $text = in_array($row->key, self::SECRETS, true) ? Crypt::decryptString($row->value) : $row->value;
                    $values[$row->key] = json_decode($text, true, 16, JSON_THROW_ON_ERROR);
                }
            } catch (Throwable $exception) {
                report($exception);
            }

            return $values;
        });
    }
}
