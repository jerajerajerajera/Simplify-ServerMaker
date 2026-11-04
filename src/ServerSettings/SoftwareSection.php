<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\ServerSettings;

use App\Enums\SubuserPermission;
use App\Models\Egg;
use App\Models\Server;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Illuminate\Validation\ValidationException;
use Simplify\ServerMaker\Creation\EggData;
use Simplify\ServerMaker\Creation\Payload;
use Simplify\ServerMaker\Creation\RequiredValues;
use Simplify\ServerMaker\Filament\Wizard\SetupStep;
use Simplify\ServerMaker\Games\Java;
use Simplify\ServerMaker\Games\Minecraft;
use Simplify\ServerMaker\Games\MinecraftEggs;
use Simplify\ServerMaker\Games\MinecraftVersions;
use Simplify\ServerMaker\Modpacks\Sources;
use Simplify\ServerMaker\Problems\ProblemLog;
use Simplify\ServerMaker\Software\ChangeSoftware;
use Simplify\ServerMaker\Software\CurrentSoftware;
use Simplify\ServerMaker\Software\Startup;
use Throwable;

/**
 * Server software: other Minecraft Java software, version or modpack for an existing server, as in Simplify 0.7.0.
 * The same choices as the Server Maker's Minecraft step; the server is reinstalled, its worlds and files stay. One
 * section, on the Server software page or in Simplify Toolkit's server settings.
 */
final class SoftwareSection
{
    /** The types a server can switch between; Bedrock and proxies have other files. */
    public const TYPES = ['vanilla', 'plugins', 'mods', 'modpack'];

    /** The choices that mean other software; the confirm field alone changes nothing. */
    private const CHOICES = ['type', 'software', 'version', 'modpack', 'modpack_version'];

    /** The owner or an administrator, with the permission to reinstall, on a Minecraft Java server. */
    public static function canUse(Server $server): bool
    {
        $user = user();

        return $user !== null
            && ($user->isRootAdmin() || (int) $server->owner_id === (int) $user->id)
            && $user->can(SubuserPermission::SettingsReinstall, $server)
            && CurrentSoftware::of((string) $server->egg?->name) !== null;
    }

    /** What the server runs now, such as "Paper 1.21.1". */
    public static function now(Server $server): string
    {
        return trim($server->egg?->name.' '.CurrentSoftware::version(Startup::of($server)['environment']));
    }

    public static function make(Server $server): Section
    {
        $t = fn (string $key, array $replace = []) => __('simplify-servermaker::maker.'.$key, $replace);

        // The head: what runs now as the subtitle; the warning sits with the name that confirms it.
        return Section::make($t('software.title'))
            ->description(fn () => $t('software.now', ['software' => self::now($server)]))
            ->icon('tabler-packages')
            ->schema([
                View::make('simplify-servermaker::wizard.styles'),
                Group::make([
                    Radio::make('type')
                        ->hiddenLabel()
                        ->view('simplify-servermaker::wizard.type-cards')
                        ->options(array_combine(self::TYPES, self::TYPES))
                        ->required()
                        ->in(self::TYPES)
                        ->live()
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('software', Minecraft::software((string) $state, null))),
                    ...SetupStep::softwareFields($t),
                    TextInput::make('confirm')
                        ->label($t('software.confirm', ['name' => $server->name]))
                        ->helperText($t('software.warning'))
                        ->placeholder($server->name)
                        ->autocomplete('off')
                        ->live(debounce: 300),
                ])->extraAttributes(['class' => 'smk']),
            ]);
    }

    /** @return array<string, mixed> */
    public static function fill(Server $server): array
    {
        $current = CurrentSoftware::of((string) $server->egg?->name);
        $type = in_array($current['type'] ?? null, self::TYPES, true) ? $current['type'] : 'plugins';

        return [
            'type' => $type,
            'software' => Minecraft::software($type, $current['software'] ?? null),
            'version' => CurrentSoftware::version(Startup::of($server)['environment']) ?? MinecraftVersions::newest(),
            'confirm' => '',
        ];
    }

    /** Whether the choices differ from what the server runs, or the name was typed to reinstall the same. */
    public static function changed(Server $server, array $state): bool
    {
        $fill = self::fill($server);
        foreach (self::CHOICES as $key) {
            if (($state[$key] ?? null) !== ($fill[$key] ?? null)) {
                return true;
            }
        }

        return (string) ($state['confirm'] ?? '') !== '';
    }

    /**
     * Reinstalls the server with the chosen software, checked first: the server name typed, the server stopped and a
     * recent backup. Errors are on data.<field>.
     *
     * @param  array<string, mixed>  $state
     */
    public static function install(Server $server, array $state): bool
    {
        abort_unless(self::canUse($server), 403);
        if (! hash_equals($server->name, (string) ($state['confirm'] ?? ''))) {
            throw ValidationException::withMessages(['data.confirm' => __('simplify-servermaker::maker.software.wrong_name')]);
        }
        if (! in_array($state['type'] ?? null, self::TYPES, true)) {
            throw ValidationException::withMessages(['data.type' => __('simplify-servermaker::maker.minecraft.egg_missing')]);
        }
        $blocked = ChangeSoftware::blocked($server);
        if ($blocked !== null) {
            Notification::make()->title(__('simplify-servermaker::maker.software.blocked.'.$blocked))->warning()->send();

            return false;
        }
        $startup = self::startup(MinecraftEggs::forChoice($state), $state, Startup::of($server)['environment']);
        try {
            ChangeSoftware::apply($server, $startup);
        } catch (Throwable $exception) {
            report($exception);
            $problem = ProblemLog::record('software', user(), __('simplify-servermaker::maker.software.failed'), $exception);
            Notification::make()->title(__('simplify-servermaker::maker.software.failed'))->actions(ProblemLog::explainActions($problem))->danger()->send();

            return false;
        }
        Notification::make()->title(__('simplify-servermaker::maker.software.installing'))->success()->send();

        return true;
    }

    /**
     * The egg's own defaults with the chosen version or pack, its startup command and the image for the Java it needs.
     * A variable the egg needs but leaves empty keeps the server's own value (such as Paper's User Agent).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $own  the server's values now
     * @return array{egg_id: int, image: string, startup: string, environment: array<string, string>}
     */
    private static function startup(Egg $egg, array $data, array $own = []): array
    {
        $egg = EggData::from($egg);
        $java = MinecraftVersions::javaFor($data);
        $environment = Payload::minecraftEnvironment($data, $egg, $java);
        if ($data['type'] === 'modpack') {
            $environment = Sources::environment((string) $data['modpack'], (string) $data['modpack_version'], $environment);
        }
        $environment = RequiredValues::fill($environment, $egg, $own);
        $image = $java !== null ? Java::image($egg->images, $java) : $egg->firstImage();
        if ($image === null || $image === '') {
            throw ValidationException::withMessages(['data.type' => __('simplify-servermaker::maker.review.no_image')]);
        }

        return ['egg_id' => $egg->id, 'image' => $image, 'startup' => $egg->startup, 'environment' => $environment];
    }
}
