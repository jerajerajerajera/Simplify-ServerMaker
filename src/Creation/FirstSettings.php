<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Creation;

use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Simplify\ServerMaker\Games\Minecraft;

/**
 * What a new Minecraft server gets once it is installed (as in Simplify 0.7.0): players, game mode and difficulty in
 * server.properties, and for Java also the MOTD, view distance, spawn protection, PvP, whitelist and flight, a server
 * icon and the accepted EULA. Pure, apart from reading the uploaded icon.
 */
final class FirstSettings
{
    public const GAMEMODES = ['survival', 'creative', 'adventure', 'spectator'];

    public const DIFFICULTIES = ['peaceful', 'easy', 'normal', 'hard'];

    public const DEFAULTS = ['max_players' => 20, 'gamemode' => 'survival', 'difficulty' => 'easy', ...self::JAVA];

    /** Java only: Minecraft's own defaults, as the Server properties step of 0.7.0 offered them. */
    public const JAVA = ['motd' => 'A Minecraft Server', 'view_distance' => 10, 'spawn_protection' => 16, 'pvp' => true, 'white_list' => false, 'allow_flight' => false];

    /** Java numbers: form field => [property, minimum, maximum]. */
    public const NUMBERS = ['view_distance' => ['view-distance', 2, 32], 'spawn_protection' => ['spawn-protection', 0, 1000]];

    /** Java switches: form field => property. */
    public const SWITCHES = ['pvp' => 'pvp', 'white_list' => 'white-list', 'allow_flight' => 'allow-flight'];

    public const MOTD_MAX = 512;

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

    /** Minecraft Java only starts once its EULA is accepted; the user may tick that box on the Review step already. */
    public static function needsEula(?string $game, ?string $type): bool
    {
        return self::hasIcon($game, $type);
    }

    /**
     * The settings to apply after the installation, or [] when there is nothing to apply.
     *
     * @param  array<string, mixed>  $state
     * @return array{properties?: array<string, string>, icon?: string, eula?: bool}
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
        if (self::hasIcon($game, $type)) {
            $settings['properties'] += self::java($state);
            if (($icon = self::icon($state['server_icon'] ?? null)) !== null) {
                $settings['icon'] = $icon;
            }
        }
        // Optional: without the tick the server is made and installed, and asks for the EULA when it is started.
        if (self::needsEula($game, $type) && filter_var($state['eula'] ?? false, FILTER_VALIDATE_BOOL)) {
            $settings['eula'] = true;
        }

        return $settings;
    }

    /**
     * The Java-only properties, checked like 0.7.0 did.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, string>
     */
    private static function java(array $state): array
    {
        $motd = str_replace(["\r\n", "\r"], "\n", (string) ($state['motd'] ?? self::JAVA['motd']));
        if (mb_strlen($motd) > self::MOTD_MAX) {
            self::fail('motd', __('simplify-servermaker::maker.first.motd_too_long', ['max' => self::MOTD_MAX]));
        }
        $properties = ['motd' => $motd];
        foreach (self::NUMBERS as $field => [$key, $min, $max]) {
            $number = filter_var($state[$field] ?? self::JAVA[$field], FILTER_VALIDATE_INT);
            if ($number === false || $number < $min || $number > $max) {
                self::fail($field, __('simplify-servermaker::maker.first.number_between', ['min' => $min, 'max' => $max]));
            }
            $properties[$key] = (string) $number;
        }
        foreach (self::SWITCHES as $field => $key) {
            $properties[$key] = filter_var($state[$field] ?? self::JAVA[$field], FILTER_VALIDATE_BOOL) ? 'true' : 'false';
        }

        return $properties;
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
                        $result[] = $match[1].'='.self::encode($values[$match[1]]);
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
                $result[] = $key.'='.self::encode($value);
            }
        }

        return rtrim(implode("\n", $result), "\n")."\n";
    }

    /** A value as Java writes it: escaped line breaks and backslashes, and \uXXXX for anything outside ASCII. */
    public static function encode(string $value): string
    {
        $value = str_replace(['\\', "\r", "\n", "\t"], ['\\\\', '\\r', '\\n', '\\t'], $value);
        $value = (string) preg_replace_callback('/^ +/', fn (array $match) => str_repeat('\\ ', strlen($match[0])), $value);

        return (string) preg_replace_callback('/[^\x20-\x7e]/u', function (array $match): string {
            $units = unpack('n*', mb_convert_encoding($match[0], 'UTF-16BE', 'UTF-8'));

            return implode('', array_map(fn (int $unit) => sprintf('\\u%04x', $unit), $units));
        }, $value);
    }

    private static function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages(['data.'.$field => $message]);
    }
}
