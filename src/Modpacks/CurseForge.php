<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Modpacks;

use Simplify\ServerMaker\Settings;

/** CurseForge (api.curseforge.com): only with an API key, set in the Server Maker settings. */
final class CurseForge implements Source
{
    private const API = 'https://api.curseforge.com/v1';

    private const MINECRAFT = 432;

    private const MODPACKS = 4471;

    public function name(): string
    {
        return 'CurseForge';
    }

    public function available(): bool
    {
        return $this->key() !== '';
    }

    public function search(string $query): array
    {
        $data = $this->get('/mods/search', [
            'gameId' => self::MINECRAFT,
            'classId' => self::MODPACKS,
            'searchFilter' => $query,
            'sortField' => 2,
            'sortOrder' => 'desc',
            'pageSize' => 8,
        ]);
        $packs = [];
        foreach ($data['data'] ?? [] as $item) {
            if (is_array($item) && ! empty($item['id'])) {
                $packs[] = $this->card((string) $item['id'], $item);
            }
        }

        return $packs;
    }

    public function pack(string $id): ?Pack
    {
        $data = $this->get('/mods/'.$id)['data'] ?? [];

        return (int) ($data['classId'] ?? 0) === self::MODPACKS ? $this->card($id, $data) : null;
    }

    public function versions(string $id): array
    {
        $items = $this->get('/mods/'.$id.'/files', ['pageSize' => 50])['data'] ?? [];
        usort($items, fn ($a, $b) => strtotime((string) ($b['fileDate'] ?? '')) <=> strtotime((string) ($a['fileDate'] ?? '')));
        $versions = [];
        foreach ($items as $item) {
            // Only files with a server pack can run as a server.
            if (! empty($item['id']) && (! empty($item['isServerPack']) || ! empty($item['serverPackFileId']))) {
                $versions[(string) $item['id']] = Pack::text($item['displayName'] ?? $item['fileName'] ?? $item['id'], 100);
            }
        }

        return $versions;
    }

    public function runtime(string $id, string $version): array
    {
        $item = $this->get('/mods/'.$id.'/files/'.rawurlencode($version))['data'] ?? [];
        if ((string) ($item['modId'] ?? '') !== $id) {
            return [];
        }
        $minecraft = Sources::newestRelease($item['gameVersions'] ?? []);

        return $minecraft === null ? [] : ['minecraft' => $minecraft];
    }

    public function eggFile(string $id): ?string
    {
        return 'egg-curseforge-generic.json';
    }

    public function environment(string $id, string $version, array $environment): array
    {
        return Sources::require($environment, ['PROJECT_ID' => $id, 'VERSION_ID' => $version, 'API_KEY' => $this->key()]);
    }

    private function card(string $id, array $data): Pack
    {
        return new Pack(
            'curseforge',
            $id,
            Pack::text($data['name'] ?? $id, 100),
            Pack::text($data['summary'] ?? '', 220),
            Remote::safeUrl($data['logo']['thumbnailUrl'] ?? null),
            Remote::safeUrl($data['links']['websiteUrl'] ?? null)
                ?? 'https://www.curseforge.com/minecraft/modpacks/'.rawurlencode((string) ($data['slug'] ?? $id)),
        );
    }

    private function key(): string
    {
        return (string) Settings::get('curseforge_key', '');
    }

    private function get(string $path, array $query = []): array
    {
        return $this->available() ? Remote::json(self::API.$path, $query, ['x-api-key' => $this->key()]) : [];
    }
}
