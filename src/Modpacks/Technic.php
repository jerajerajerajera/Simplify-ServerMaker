<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Modpacks;

/**
 * Technic: the classic Technic server packs that have an egg of their own, from resources/catalog/technic.json.
 * Nothing is fetched; each pack installs the server build its egg names.
 */
final class Technic implements Source
{
    private const DEFAULT = 'egg_default';

    public function name(): string
    {
        return 'Technic';
    }

    public function available(): bool
    {
        return true;
    }

    public function search(string $query): array
    {
        $packs = [];
        foreach (self::catalog() as $item) {
            if ($query === '' || str_contains(mb_strtolower($item['title'].' technic '.$item['description']), mb_strtolower($query))) {
                $packs[] = $this->card($item);
            }
        }

        return $query === '' ? array_slice($packs, 0, 2) : $packs;
    }

    public function pack(string $id): ?Pack
    {
        $item = self::item($id);

        return $item ? $this->card($item) : null;
    }

    public function versions(string $id): array
    {
        $item = self::item($id);

        return $item ? [self::DEFAULT => (string) $item['default_version']] : [];
    }

    public function runtime(string $id, string $version): array
    {
        $item = self::item($id);

        return $item && $version === self::DEFAULT ? ['minecraft' => (string) $item['minecraft'], 'java' => (int) $item['java']] : [];
    }

    public function eggFile(string $id): ?string
    {
        return self::item($id)['egg_file'] ?? null;
    }

    public function environment(string $id, string $version, array $environment): array
    {
        // The egg's own tested server build stays as it is.
        return Sources::require($environment, ['MODPACK_VERSION' => $environment['MODPACK_VERSION'] ?? '']);
    }

    /** @return list<array<string, mixed>> */
    public static function catalog(): array
    {
        static $catalog = null;

        return $catalog ??= json_decode(
            (string) file_get_contents(dirname(__DIR__, 2).'/resources/catalog/technic.json'),
            true,
            16,
            JSON_THROW_ON_ERROR,
        );
    }

    private static function item(string $id): ?array
    {
        foreach (self::catalog() as $item) {
            if ($item['id'] === $id) {
                return $item;
            }
        }

        return null;
    }

    private function card(array $item): Pack
    {
        return new Pack('technic', $item['id'], $item['title'], $item['description'], Remote::safeUrl($item['icon_url']), $item['url']);
    }
}
