<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Modpacks;

use Illuminate\Support\Facades\Cache;

/** Feed The Beast (api.feed-the-beast.com, with api.modpacks.ch as its older address): public, no key. */
final class Ftb implements Source
{
    private const APIS = ['https://api.feed-the-beast.com/v1/modpacks/public', 'https://api.modpacks.ch/public'];

    public function name(): string
    {
        return 'FTB';
    }

    public function available(): bool
    {
        return true;
    }

    public function search(string $query): array
    {
        $path = $query === '' ? '/modpack/popular/installs/4' : '/modpack/search/8';
        $ids = $this->get($path, $query === '' ? [] : ['term' => $query])['packs'] ?? [];
        $packs = [];
        foreach (array_slice($ids, 0, 6) as $id) {
            if (ctype_digit((string) $id) && ($pack = $this->pack((string) $id))) {
                $packs[] = $pack;
            }
        }

        return $packs;
    }

    public function pack(string $id): ?Pack
    {
        $data = $this->data($id);
        if ($data === []) {
            return null;
        }
        $icon = null;
        foreach ($data['art'] ?? [] as $art) {
            if (($art['type'] ?? '') === 'square' && ($url = Remote::safeUrl($art['url'] ?? null))) {
                $icon = $url;
                break;
            }
        }

        return new Pack(
            'ftb',
            $id,
            Pack::text($data['name'] ?? $id, 100),
            Pack::text($data['synopsis'] ?? $data['description'] ?? '', 220),
            $icon,
            'https://www.feed-the-beast.com/modpacks/'.rawurlencode($id.'-'.($data['slug'] ?? '')),
        );
    }

    public function versions(string $id): array
    {
        $items = $this->data($id)['versions'] ?? [];
        usort($items, fn ($a, $b) => (int) ($b['updated'] ?? 0) <=> (int) ($a['updated'] ?? 0));
        $versions = [];
        foreach (array_slice($items, 0, 60) as $item) {
            if (! empty($item['id']) && ($item['type'] ?? '') !== 'archived' && empty($item['private'])) {
                $versions[(string) $item['id']] = Pack::text($item['name'] ?? $item['id'], 100);
            }
        }

        return $versions;
    }

    public function runtime(string $id, string $version): array
    {
        foreach ($this->data($id)['versions'] ?? [] as $item) {
            if ((string) ($item['id'] ?? '') !== $version) {
                continue;
            }
            $runtime = [];
            foreach ($item['targets'] ?? [] as $target) {
                if (($target['name'] ?? '') === 'minecraft') {
                    $runtime['minecraft'] = (string) $target['version'];
                }
                if (($target['name'] ?? '') === 'java' && preg_match('/^(?:1\.)?(\d+)/', (string) ($target['version'] ?? ''), $match)) {
                    $runtime['java'] = (int) $match[1];
                }
            }

            return $runtime;
        }

        return [];
    }

    public function eggFile(string $id): ?string
    {
        return 'egg-ftb-server.json';
    }

    public function environment(string $id, string $version, array $environment): array
    {
        return Sources::require($environment, ['FTB_MODPACK_ID' => $id, 'FTB_MODPACK_VERSION_ID' => $version]);
    }

    private function data(string $id): array
    {
        return Cache::remember('simplify-servermaker.ftb.'.$id, 1800, function () use ($id): array {
            $data = $this->get('/modpack/'.$id);

            return (string) ($data['id'] ?? '') === $id && ! empty($data['name']) ? $data : [];
        });
    }

    private function get(string $path, array $query = []): array
    {
        foreach (self::APIS as $api) {
            $data = Remote::json($api.$path, $query);
            if ($data !== [] && ($data['status'] ?? '') !== 'error') {
                return $data;
            }
        }

        return [];
    }
}
