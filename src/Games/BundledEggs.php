<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Games;

use App\Enums\EggFormat;
use App\Models\Egg;
use App\Services\Eggs\Sharing\EggImporterService;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * The Minecraft eggs shipped in resources/eggs (from pelican-eggs, MIT). Each file is checked against
 * manifest.json before Pelican's own importer adds it to the panel.
 */
final class BundledEggs
{
    public static function directory(): string
    {
        return dirname(__DIR__, 2).'/resources/eggs';
    }

    /** @return array<string, array{name: string, sha256: string}> */
    public static function manifest(?string $directory = null): array
    {
        $text = file_get_contents(($directory ?? self::directory()).'/manifest.json');

        return json_decode((string) $text, true, 16, JSON_THROW_ON_ERROR);
    }

    /** The egg definition, after checking its checksum and name against the manifest. */
    public static function definition(string $file, ?string $directory = null): array
    {
        $directory ??= self::directory();
        $entry = self::manifest($directory)[$file] ?? null;
        $content = $entry === null ? false : file_get_contents($directory.'/'.basename($file));

        return self::verify($file, $entry, $content === false ? null : $content);
    }

    /** @param  array{name: string, sha256: string}|null  $entry */
    public static function verify(string $file, ?array $entry, ?string $content): array
    {
        if ($entry === null || $content === null || ! hash_equals($entry['sha256'], hash('sha256', $content))) {
            throw new RuntimeException("The bundled egg $file failed its checksum.");
        }
        $definition = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (($definition['name'] ?? '') !== $entry['name']) {
            throw new RuntimeException("The bundled egg $file is not {$entry['name']}.");
        }

        return $definition;
    }

    /** Adds the egg to the panel as a new egg. One import at a time per file, so two Server Makers share it. */
    public static function import(string $file, callable $existing): Egg
    {
        return Cache::lock('simplify-server-maker.egg.'.$file, 60)->block(15, function () use ($file, $existing): Egg {
            $egg = $existing();
            if ($egg instanceof Egg) {
                return $egg;
            }
            $definition = self::definition($file);
            // Without its uuid the importer always makes a new egg and never changes an egg already on the panel.
            unset($definition['uuid']);

            return app(EggImporterService::class)->fromContent(
                json_encode($definition, JSON_THROW_ON_ERROR),
                EggFormat::JSON,
                new Egg,
            );
        });
    }
}
