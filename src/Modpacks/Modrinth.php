<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Modpacks;

/** Modrinth (api.modrinth.com): public, no key. */
final class Modrinth implements Source
{
    private const API = 'https://api.modrinth.com/v2';

    public function name(): string
    {
        return 'Modrinth';
    }

    public function available(): bool
    {
        return true;
    }

    public function search(string $query): array
    {
        $data = Remote::json(self::API.'/search', [
            'query' => $query,
            'index' => $query === '' ? 'downloads' : 'relevance',
            'limit' => 8,
            'facets' => '[["project_type:modpack"]]',
        ]);
        $packs = [];
        foreach ($data['hits'] ?? [] as $hit) {
            if (is_array($hit) && ! empty($hit['project_id'])) {
                $packs[] = $this->card((string) $hit['project_id'], $hit);
            }
        }

        return $packs;
    }

    public function pack(string $id): ?Pack
    {
        $data = Remote::json(self::API.'/project/'.rawurlencode($id));

        return ($data['project_type'] ?? '') === 'modpack' ? $this->card($id, $data) : null;
    }

    public function versions(string $id): array
    {
        $versions = [];
        foreach (array_slice(Remote::json(self::API.'/project/'.rawurlencode($id).'/version'), 0, 60) as $item) {
            if (is_array($item) && ! empty($item['id'])) {
                $versions[(string) $item['id']] = Pack::text($item['version_number'] ?? $item['name'] ?? $item['id'], 100);
            }
        }

        return $versions;
    }

    public function runtime(string $id, string $version): array
    {
        $item = Remote::json(self::API.'/version/'.rawurlencode($version));
        if ((string) ($item['project_id'] ?? '') !== $id) {
            return [];
        }
        $minecraft = Sources::newestRelease($item['game_versions'] ?? []);

        return $minecraft === null ? [] : ['minecraft' => $minecraft];
    }

    public function eggFile(string $id): ?string
    {
        return 'egg-modrinth-generic.json';
    }

    public function environment(string $id, string $version, array $environment): array
    {
        return Sources::require($environment, ['PROJECT_ID' => $id, 'VERSION_ID' => $version]);
    }

    private function card(string $id, array $data): Pack
    {
        $slug = (string) ($data['slug'] ?? $id);

        return new Pack(
            'modrinth',
            $id,
            Pack::text($data['title'] ?? $id, 100),
            Pack::text($data['description'] ?? '', 220),
            Remote::safeUrl($data['icon_url'] ?? null),
            'https://modrinth.com/modpack/'.rawurlencode($slug),
        );
    }
}
