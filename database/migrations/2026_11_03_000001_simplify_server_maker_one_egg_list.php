<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Until 2.0.0-beta.2 Minecraft had its own switch next to a list of "other games", and an empty list of nodes or
 * games meant none. Now one list of eggs holds "minecraft" and egg ids, and empty means all. The old choice is
 * carried over so nothing opens up by itself: a setup that let nobody in (no nodes, or no game at all) has
 * self-service turned off. Runs once: it does nothing when the new list is already there.
 */
return new class extends Migration
{
    private const TABLE = 'simplify_server_maker_settings';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }
        $stored = DB::table(self::TABLE)->pluck('value', 'key')->all();
        // Nothing saved yet (a new install), or already carried over.
        if ($stored === [] || array_key_exists('eggs', $stored)) {
            return;
        }
        $read = fn (string $key, mixed $default) => array_key_exists($key, $stored) ? json_decode((string) $stored[$key], true) ?? $default : $default;
        $eggs = array_values(array_filter(array_map('intval', (array) $read('egg_ids', [])), fn (int $id) => $id > 0));
        if ((bool) $read('minecraft', true)) {
            array_unshift($eggs, 'minecraft');
        }
        $values = ['eggs' => $eggs === [] ? ['minecraft'] : $eggs];
        if ($eggs === [] || array_filter((array) $read('node_ids', [])) === []) {
            $values['self_service'] = false;
        }
        foreach ($values as $key => $value) {
            DB::table(self::TABLE)->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()]);
        }
        DB::table(self::TABLE)->whereIn('key', ['minecraft', 'egg_ids'])->delete();
        // The settings are read through a short cache.
        cache()->forget('simplify-servermaker.settings');
    }

    public function down(): void {}
};
