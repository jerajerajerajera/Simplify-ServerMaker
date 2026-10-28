<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Games;

/**
 * Which Java a Minecraft release needs, and the egg's Docker image for it. Pure: the Mojang lookup lives in
 * MinecraftVersions.
 */
final class Java
{
    public const MAJORS = [8, 11, 16, 17, 21, 25];

    /** Java for a Minecraft release when Mojang cannot be asked. Paper asks for newer Java than Vanilla does. */
    public static function forMinecraft(string $minecraft, string $type): int
    {
        if (version_compare($minecraft, '26.1', '>=')) {
            return 25;
        }
        if ($type === 'plugins') {
            return match (true) {
                version_compare($minecraft, '1.20', '>=') => 21,
                version_compare($minecraft, '1.17', '>=') => 17,
                version_compare($minecraft, '1.16.5', '>=') => 16,
                version_compare($minecraft, '1.12', '>=') => 11,
                default => 8,
            };
        }

        return match (true) {
            version_compare($minecraft, '1.20.5', '>=') => 21,
            version_compare($minecraft, '1.18', '>=') => 17,
            version_compare($minecraft, '1.17', '>=') => 16,
            default => 8,
        };
    }

    /**
     * The egg's image for a Java version, matched on the image tag first (java_21) and the label second.
     *
     * @param  array<string, string>  $images  label => image, as in an egg
     */
    public static function image(array $images, int $major): string
    {
        $pattern = '/(?:java|jdk|jre)[-_ ]?(\d+)(?=$|[^0-9])/i';
        foreach ($images as $label => $image) {
            if (! is_string($image)) {
                continue;
            }
            if (preg_match($pattern, $image, $match)) {
                if ((int) $match[1] === $major) {
                    return $image;
                }

                continue;
            }
            if (preg_match($pattern, (string) $label, $match) && (int) $match[1] === $major) {
                return $image;
            }
        }

        return 'ghcr.io/pelican-eggs/yolks:java_'.$major;
    }
}
