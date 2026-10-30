<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Creation;

use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Simplify\ServerMaker\Games\Minecraft;

/**
 * What a new Minecraft server gets once it is installed (as in Simplify 0.7.0): players, game mode and difficulty in
 * server.properties, and a server icon for Java. Pure, apart from reading the uploaded icon.
 */
final class FirstSettings
{
    public const GAMEMODES = ['survival', 'creative', 'adventure', 'spectator'];

    public const DIFFICULTIES = ['peaceful', 'easy', 'normal', 'hard'];

    public const DEFAULTS = ['max_players' => 20, 'gamemode' => 'survival', 'difficulty' => 'easy'];

    /** Every Minecraft type except a proxy has a server.properties. */
    public static function hasProperties(?string $game, ?string $type): bool
    {
        return $game === 'minecraft' && Minecraft::isType((string) $type) && $type !== 'proxy';
    }

    /** Only Java shows a server icon in the server list. */
    public static function hasIcon(?string $game, ?string $type): bool
    {
        return self::hasProperties($game, $type) && $type !== 'bedrock';
    }

    /**
     * The settings to apply after the installation, or [] when there is nothing to apply.
     *
     * @param  array<string, mixed>  $state
     * @return array{properties?: array<string, string>, icon?: string}
     */
    public static function from(array $state): array
    {
        $game = $state['game'] ?? null;
        $type = $state['type'] ?? null;
        if (! self::hasProperties($game, $type)) {
            return [];
        }
        $players = filter_var($state['max_players'] ?? self::DEFAULTS['max_players'], FILTER_VALIDATE_INT);
        if ($players === false || $players < 1 || $players > 1000) {
            self::fail('max_players', __('simplify-servermaker::maker.first.number_between', ['min' => 1, 'max' => 1000]));
        }
        $gamemode = (string) ($state['gamemode'] ?? self::DEFAULTS['gamemode']);
        $difficulty = (string) ($state['difficulty'] ?? self::DEFAULTS['difficulty']);
        if (! in_array($gamemode, self::GAMEMODES, true) || ! in_array($difficulty, self::DIFFICULTIES, true)) {
            self::fail('gamemode', __('simplify-servermaker::maker.game.invalid_value'));
        }
        // Releases before 1.13 still use numbers for these.
        $version = (string) ($state['version'] ?? '');
        if ($type !== 'bedrock' && $version !== '' && preg_match('/^\d+\.\d+/', $version) && version_compare($version, '1.13', '<')) {
            $gamemode = (string) array_search($gamemode, self::GAMEMODES, true);
            $difficulty = (string) array_search($difficulty, self::DIFFICULTIES, true);
        }
        $settings = ['properties' => ['max-players' => (string) $players, 'gamemode' => $gamemode, 'difficulty' => $difficulty]];
        if (self::hasIcon($game, $type) && ($icon = self::icon($state['server_icon'] ?? null)) !== null) {
            $settings['icon'] = $icon;
        }

        return $settings;
    }

    /** The uploaded icon as base64, after checking it is a 64 × 64 PNG of at most 100 KB. */
    public static function icon(mixed $upload): ?string
    {
        if (is_array($upload)) {
            $upload = reset($upload) ?: null;
        }
        if ($upload === null || $upload === '') {
            return null;
        }
        $content = $upload instanceof TemporaryUploadedFile ? (string) file_get_contents($upload->getRealPath()) : '';
        $size = $content !== '' ? @getimagesizefromstring($content) : false;
        if (! $size || $size[0] !== 64 || $size[1] !== 64 || $size[2] !== IMAGETYPE_PNG || strlen($content) > 102400) {
            self::fail('server_icon', __('simplify-servermaker::maker.first.icon_invalid'));
        }

        return base64_encode($content);
    }

    /**
     * Changes only the given keys of a .properties file; every other line, comment and order stays.
     *
     * @param  array<string, string>  $values
     */
    public static function merge(string $original, array $values): string
    {
        $result = [];
        $seen = [];
        $continuation = false;
        $skip = false;
        $original = rtrim($original, "\r\n");
        foreach ($original === '' ? [] : preg_split('/\r\n|\n|\r/', $original) as $line) {
            if (! $continuation) {
                $skip = false;
                if (preg_match('/^\s*([^#!\s=:]+)\s*(?:[=:]|\s)\s*/', $line, $match) && array_key_exists($match[1], $values)) {
                    $skip = true;
                    if (! isset($seen[$match[1]])) {
                        $result[] = $match[1].'='.$values[$match[1]];
                        $seen[$match[1]] = true;
                    }
                }
            }
            if (! $skip) {
                $result[] = $line;
            }
            $slashes = strlen($line) - strlen(rtrim($line, '\\'));
            $continuation = $slashes % 2 === 1 && ! preg_match('/^\s*[#!]/', $line);
        }
        foreach ($values as $key => $value) {
            if (! isset($seen[$key])) {
                $result[] = $key.'='.$value;
            }
        }

        return rtrim(implode("\n", $result), "\n")."\n";
    }

    private static function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages(['data.'.$field => $message]);
    }
}
