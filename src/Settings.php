<?php

declare(strict_types=1);

namespace Simplify\ServerMaker;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The Server Maker's settings, in its own table. Secrets (the CurseForge key) are stored encrypted and never shown
 * again. Read from a short cache, in which secrets stay encrypted.
 */
final class Settings
{
    public const SECRETS = ['curseforge_key'];

    private const TABLE = 'simplify_server_maker_settings';

    private const CACHE = 'simplify-server-maker.settings';

    public static function get(string $key, mixed $default = null): mixed
    {
        $stored = self::stored()[$key] ?? null;
        if ($stored === null) {
            return $default;
        }
        try {
            $text = in_array($key, self::SECRETS, true) ? Crypt::decryptString($stored) : $stored;

            return json_decode($text, true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            // For example a secret saved under another APP_KEY: treat it as not set.
            report($exception);

            return $default;
        }
    }

    public static function has(string $key): bool
    {
        $value = self::get($key);

        return $value !== null && $value !== '' && $value !== [];
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

    /** @return array<string, string> key => the value as stored, so secrets stay encrypted in the cache too */
    private static function stored(): array
    {
        return Cache::remember(self::CACHE, 60, function (): array {
            try {
                return DB::table(self::TABLE)->pluck('value', 'key')->all();
            } catch (Throwable $exception) {
                report($exception);

                return [];
            }
        });
    }
}
