<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Creation;

use Illuminate\Validation\ValidationException;
use Simplify\ServerMaker\Games\Java;
use Simplify\ServerMaker\Games\Minecraft;

/**
 * Turns the answers of the Server Maker into the data for Pelican's ServerCreationService. Pure: the page looks up
 * the egg, the port and the Java version and passes them in.
 */
final class Payload
{
    /**
     * @param  array<string, mixed>  $state  the form
     * @param  array{memory: int, cpu: int, disk: int}  $resources
     * @param  int|null  $java  the Java major version for Minecraft Java servers
     * @param  bool  $admin  administrators may override the image, the startup command and every variable
     */
    public static function build(
        array $state,
        EggData $egg,
        array $resources,
        int $ownerId,
        int $nodeId,
        int $allocationId,
        ?int $java,
        bool $admin,
    ): array {
        $name = trim((string) ($state['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 191) {
            throw ValidationException::withMessages(['data.name' => __('simplify-server-maker::maker.server.name_required')]);
        }

        $minecraft = ($state['game'] ?? '') === 'minecraft';
        $environment = $minecraft
            ? self::minecraftEnvironment($state, $egg, $java)
            : self::gameEnvironment((array) ($state['variables'] ?? []), $egg, $admin);
        if ($admin) {
            $environment = self::overrides($environment, (array) ($state['environment'] ?? []));
        }

        $image = $minecraft && $java !== null ? Java::image($egg->images, $java) : $egg->firstImage();
        $startup = $egg->startup;
        if ($admin && trim((string) ($state['image'] ?? '')) !== '') {
            $image = trim((string) $state['image']);
        }
        if ($admin && trim((string) ($state['startup'] ?? '')) !== '') {
            $startup = trim((string) $state['startup']);
        }
        if ($image === null || $image === '') {
            throw ValidationException::withMessages(['data.image' => __('simplify-server-maker::maker.review.no_image')]);
        }

        return [
            'name' => $name,
            'owner_id' => $ownerId,
            'node_id' => $nodeId,
            'allocation_id' => $allocationId,
            'egg_id' => $egg->id,
            'environment' => $environment,
            'memory' => $resources['memory'],
            'swap' => 0,
            'disk' => $resources['disk'],
            'io' => 500,
            'cpu' => $resources['cpu'],
            'threads' => null,
            'oom_killer' => false,
            'database_limit' => 0,
            'allocation_limit' => 0,
            'backup_limit' => (int) ($state['backups'] ?? 2),
            'image' => $image,
            'startup' => $startup,
            'skip_scripts' => $admin && (bool) ($state['skip_scripts'] ?? false),
            'start_on_completion' => false,
        ];
    }

    /** @return array<string, string> */
    private static function minecraftEnvironment(array $state, EggData $egg, ?int $java): array
    {
        $type = (string) ($state['type'] ?? '');
        $version = (string) ($state['version'] ?? '');
        if (Minecraft::usesVersion($type) && ! Minecraft::validVersion($version)) {
            throw ValidationException::withMessages(['data.version' => __('simplify-server-maker::maker.minecraft.version_required')]);
        }
        $environment = Minecraft::environment($egg->defaults(), $type, $version);
        foreach (['JAVA_VERSION', 'JDK_VERSION'] as $key) {
            if ($java !== null && array_key_exists($key, $environment)) {
                $environment[$key] = (string) $java;
            }
        }

        return $environment;
    }

    /**
     * The egg's variables with the values from the form. Users may only set variables the egg lets them see and change.
     *
     * @return array<string, string>
     */
    public static function gameEnvironment(array $values, EggData $egg, bool $admin): array
    {
        $environment = [];
        foreach ($egg->variables as $name => $variable) {
            $editable = $admin || $variable['user'];
            $value = $editable && array_key_exists($name, $values) ? $values[$name] : $variable['default'];
            if ($value !== null && ! is_scalar($value)) {
                throw ValidationException::withMessages(['data.variables.'.$name => __('simplify-server-maker::maker.game.invalid_value')]);
            }
            $environment[$name] = is_bool($value) ? ($value ? '1' : '0') : (string) ($value ?? '');
        }
        foreach (array_keys($values) as $name) {
            if (! isset($egg->variables[$name]) || ! ($admin || $egg->variables[$name]['user'])) {
                throw ValidationException::withMessages(['data.variables' => __('simplify-server-maker::maker.game.locked_variable')]);
            }
        }

        return $environment;
    }

    /** @return array<string, string> */
    private static function overrides(array $environment, array $overrides): array
    {
        foreach ($overrides as $name => $value) {
            if (! array_key_exists((string) $name, $environment)) {
                throw ValidationException::withMessages(['data.environment' => __('simplify-server-maker::maker.review.unknown_variable', ['name' => $name])]);
            }
            $environment[(string) $name] = (string) $value;
        }

        return $environment;
    }
}
