<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Modpacks;

/** A site with Minecraft modpacks: how to search it, list a pack's versions and fill in the egg. */
interface Source
{
    public function name(): string;

    /** Whether it can be used now (CurseForge needs an API key). */
    public function available(): bool;

    /** @return list<Pack> popular packs for an empty query */
    public function search(string $query): array;

    public function pack(string $id): ?Pack;

    /** @return array<string, string> version id => name, newest first, server versions only */
    public function versions(string $id): array;

    /** @return array{minecraft?: string, java?: int} what the version runs on, as far as the site says */
    public function runtime(string $id, string $version): array;

    /** The bundled egg for this pack. */
    public function eggFile(string $id): ?string;

    /**
     * The egg's variables for this pack and version.
     *
     * @param  array<string, string>  $environment  the egg's defaults
     * @return array<string, string>
     */
    public function environment(string $id, string $version, array $environment): array;
}
