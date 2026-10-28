<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Modpacks;

use App\Models\Egg;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Simplify\ServerMaker\Games\BundledEggs;

/** Every modpack site in one list: search, versions, Java and the egg. Answers are cached for a few minutes. */
final class Sources
{
    /** @var array<string, Source>|null */
    private static ?array $sources = null;

    /** @return array<string, Source> */
    public static function all(): array
    {
        return self::$sources ??= [
            'modrinth' => new Modrinth,
            'curseforge' => new CurseForge,
            'ftb' => new Ftb,
            'technic' => new Technic,
        ];
    }

    public static function get(string $name): ?Source
    {
        $source = self::all()[$name] ?? null;

        return $source?->available() ? $source : null;
    }

    /**
     * Popular packs from every site for an empty query, else the matches mixed so no site hides below another.
     * The chosen pack stays in the list.
     *
     * @return list<array<string, mixed>>
     */
    public static function results(string $query, ?string $selected = null): array
    {
        $query = mb_substr(trim($query), 0, 60);
        if (mb_strlen($query) < 3) {
            $query = '';
        }
        $available = array_filter(self::all(), fn (Source $source) => $source->available());
        $cacheKey = 'simplify-server-maker.modpacks.'.hash('sha256', mb_strtolower($query).'|'.implode(',', array_keys($available)));
        $results = Cache::remember($cacheKey, 600, function () use ($query, $available): array {
            $lists = array_map(fn (Source $source) => $source->search($query), $available);

            return array_map(fn (Pack $pack) => $pack->toArray(), self::interleave($lists, $query === '' ? 8 : 24));
        });
        if ($selected !== null && ! in_array($selected, array_column($results, 'key'), true) && ($pack = self::pack($selected))) {
            array_unshift($results, $pack->toArray());
        }

        return $results;
    }

    /**
     * @param  array<string, list<Pack>>  $lists
     * @return list<Pack>
     */
    public static function interleave(array $lists, int $limit): array
    {
        $mixed = [];
        $longest = $lists === [] ? 0 : max(array_map('count', $lists));
        for ($index = 0; $index < $longest && count($mixed) < $limit; $index++) {
            foreach ($lists as $list) {
                if (isset($list[$index]) && count($mixed) < $limit) {
                    $mixed[] = $list[$index];
                }
            }
        }

        return $mixed;
    }

    public static function pack(?string $key): ?Pack
    {
        [$source, $id] = Pack::split($key) ?? [null, null];
        if ($source === null || ! self::get($source)) {
            return null;
        }
        $pack = Cache::remember('simplify-server-maker.modpack.'.$key, 1800, fn () => self::get($source)->pack($id)?->toArray() ?? false);

        return is_array($pack)
            ? new Pack($source, $id, $pack['title'], $pack['description'], $pack['icon'], $pack['url'])
            : null;
    }

    /** @return array<string, string> */
    public static function versions(?string $key): array
    {
        [$source, $id] = Pack::split($key) ?? [null, null];
        if ($source === null || ! self::get($source)) {
            return [];
        }

        return Cache::remember('simplify-server-maker.modpack-versions.'.$key, 600, fn () => self::get($source)->versions($id));
    }

    /** @return array{minecraft?: string, java?: int} */
    public static function runtime(?string $key, ?string $version): array
    {
        [$source, $id] = Pack::split($key) ?? [null, null];
        if ($source === null || ! self::get($source) || ! $version) {
            return [];
        }

        return Cache::remember(
            'simplify-server-maker.modpack-runtime.'.$key.':'.$version,
            600,
            fn () => self::get($source)->runtime($id, $version),
        );
    }

    /** The egg for a pack: found by its name on the panel, else added from the bundled eggs. */
    public static function egg(string $key, bool $mayImport): ?Egg
    {
        [$source, $id] = Pack::split($key) ?? [null, null];
        $file = $source !== null ? self::get($source)?->eggFile($id) : null;
        if ($file === null) {
            return null;
        }
        $name = BundledEggs::manifest()[$file]['name'] ?? null;
        $find = fn () => $name === null ? null : Egg::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        $egg = $find();
        if ($egg || ! $mayImport) {
            return $egg;
        }

        return BundledEggs::import($file, $find);
    }

    /**
     * The egg's variables for the chosen pack and version, after checking that the version belongs to the pack.
     *
     * @param  array<string, string>  $environment
     * @return array<string, string>
     */
    public static function environment(string $key, string $version, array $environment): array
    {
        [$source, $id] = Pack::split($key) ?? [null, null];
        if ($source === null || ! self::get($source) || ! array_key_exists($version, self::versions($key))) {
            throw ValidationException::withMessages(['data.modpack' => __('simplify-server-maker::maker.modpack.invalid')]);
        }

        return self::get($source)->environment($id, $version, $environment);
    }

    /**
     * Fills in the variables a pack needs; an egg without them is not the egg the pack was made for.
     *
     * @param  array<string, string>  $environment
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    public static function require(array $environment, array $values): array
    {
        foreach ($values as $name => $value) {
            if (! array_key_exists($name, $environment)) {
                throw ValidationException::withMessages(['data.modpack' => __('simplify-server-maker::maker.modpack.wrong_egg', ['name' => $name])]);
            }
            $environment[$name] = $value;
        }

        return $environment;
    }

    /** The newest plain release (1.20.1) in a list of game versions. */
    public static function newestRelease(mixed $versions): ?string
    {
        $releases = array_values(array_filter((array) $versions, fn ($version) => is_string($version) && preg_match('/^\d+\.\d+(\.\d+)?$/', $version)));
        usort($releases, fn ($a, $b) => version_compare($b, $a));

        return $releases[0] ?? null;
    }
}
