<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Games;

use App\Models\Egg;
use Illuminate\Validation\ValidationException;
use Simplify\ServerMaker\Access;
use Simplify\ServerMaker\Modpacks\Sources;
use Throwable;

/** The panel's egg for a Minecraft type, imported from the bundled eggs when the panel has none yet. */
final class MinecraftEggs
{
    public static function find(string $type, ?string $software): ?Egg
    {
        foreach (Minecraft::eggNames($type, $software) as $name) {
            $egg = Egg::query()->whereRaw('LOWER(name) = ?', [$name])->first();
            if ($egg) {
                return $egg;
            }
        }

        return null;
    }

    public static function ensure(string $type, ?string $software): ?Egg
    {
        $egg = self::find($type, $software);
        $file = Minecraft::eggFile($type, $software);
        if ($egg || $file === null) {
            return $egg;
        }

        return BundledEggs::import($file, fn () => self::find($type, $software));
    }

    /**
     * The egg for a chosen Minecraft type and software, or modpack and version (form state under "data"), added from
     * the bundled eggs when missing and the person may add eggs.
     *
     * @param  array<string, mixed>  $data
     */
    public static function forChoice(array $data): Egg
    {
        $type = (string) ($data['type'] ?? '');
        $software = $data['software'] ?? null;
        $mayImport = Access::mayImportEggs();
        try {
            if ($type === 'modpack') {
                $key = (string) ($data['modpack'] ?? '');
                if (! array_key_exists((string) ($data['modpack_version'] ?? ''), Sources::versions($key))) {
                    throw ValidationException::withMessages(['data.modpack_version' => __('simplify-servermaker::maker.modpack.version_required')]);
                }
                $egg = Sources::egg($key, $mayImport);
            } else {
                $egg = self::find($type, $software) ?? ($mayImport ? self::ensure($type, $software) : null);
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['data.type' => __('simplify-servermaker::maker.minecraft.egg_failed')]);
        }
        if (! $egg) {
            throw ValidationException::withMessages(['data.type' => __('simplify-servermaker::maker.minecraft.egg_missing')]);
        }

        return $egg;
    }
}
