<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Creation;

use App\Models\Server;
use Closure;
use GdImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Simplify\ServerMaker\Games\SteamArt;
use Throwable;

/**
 * Copies a game's Steam art to a new server once, after the creation response: a square icon as Pelican's server
 * icon, and with Simplify the banner as the server's banner. Images come only from Steam's CDN, are at most 6 MB and
 * are decoded and drawn again before they are stored. A failure never touches the server.
 */
final class ApplySteamArt
{
    private const LOOKS = 'Simplify\\Core\\Server\\Looks';

    /** @param  array{name?: string, banner?: string, header?: string, capsule?: string}  $art */
    public static function afterResponse(Server $server, array $art): void
    {
        app()->terminating(fn () => self::apply($server, $art));
    }

    /** @param  array{name?: string, banner?: string, header?: string, capsule?: string}  $art */
    public static function apply(Server $server, array $art): void
    {
        try {
            $icon = self::render([$art['capsule'] ?? null, $art['header'] ?? null], self::icon(...));
            if ($icon !== null && method_exists($server, 'writeIcon')) {
                $server->writeIcon('png', $icon);
            }
            $looks = self::LOOKS;
            $banner = class_exists($looks) ? self::render([$art['banner'] ?? null, $art['header'] ?? null], self::banner(...)) : null;
            if ($banner !== null) {
                $path = $looks::DIRECTORY.'/'.Str::random(40).'.jpg';
                Storage::disk('local')->put($path, $banner);
                $looks::save($server, $looks::for($server)['color'] ?? null, $path);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** Scaled down to at most 1920 pixels wide, as JPEG. */
    private static function banner(GdImage $source, int $width, int $height): void
    {
        $scale = min(1, 1920 / max(1, $width));
        $target = imagescale($source, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagejpeg($target ?: $source, null, 84);
    }

    /** A 128 × 128 PNG: the top square of the portrait capsule, else the middle of the header image. */
    private static function icon(GdImage $source, int $width, int $height): void
    {
        $side = min($width, $height);
        $icon = imagecreatetruecolor(128, 128);
        imagecopyresampled($icon, $source, 0, 0, (int) (($width - $side) / 2), $height > $width ? 0 : (int) (($height - $side) / 2), 128, 128, $side, $side);
        imagepng($icon, null, 7);
    }

    /** The first URL that downloads as an image, drawn again by $draw. */
    private static function render(array $urls, Closure $draw): ?string
    {
        foreach (array_filter($urls) as $url) {
            $source = self::download((string) $url);
            if ($source !== null) {
                ob_start();
                $draw($source, imagesx($source), imagesy($source));

                return ob_get_clean() ?: null;
            }
        }

        return null;
    }

    private static function download(string $url): ?GdImage
    {
        if (! str_starts_with($url, SteamArt::CDN)) {
            return null;
        }
        try {
            $response = Http::timeout(10)->connectTimeout(3)->withOptions(['allow_redirects' => false])->get($url);
            $bytes = $response->successful() ? $response->body() : '';
            if ($bytes === '' || strlen($bytes) > 6 * 1024 * 1024 || ! @getimagesizefromstring($bytes)) {
                return null;
            }
            $image = @imagecreatefromstring($bytes);

            return $image instanceof GdImage ? $image : null;
        } catch (Throwable) {
            return null;
        }
    }
}
