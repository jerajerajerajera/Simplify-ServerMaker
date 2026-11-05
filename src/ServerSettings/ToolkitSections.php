<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\ServerSettings;

use App\Models\Server;
use Filament\Schemas\Components\Section;
use Illuminate\Validation\ValidationException;

/**
 * Server software and Performance as sections of Simplify Toolkit's server settings, with the page's one Save, instead
 * of pages of their own (Toolkit's README, "For other Simplify add-ons"). Tagged 'simplify.server-settings-sections'.
 */
final class ToolkitSections
{
    public const KEY = 'simplify-servermaker';

    /** Toolkit's tag for these sections; without Toolkit the Server Maker keeps its own pages. */
    private const SECTIONS = 'Simplify\\Toolkit\\Services\\SettingsSections';

    public static function available(): bool
    {
        return class_exists(self::SECTIONS);
    }

    public function serverSettingsKey(): string
    {
        return self::KEY;
    }

    /** @return list<Section> */
    public function serverSettingsSections(Server $server): array
    {
        return array_values(array_filter([
            SoftwareSection::canUse($server) ? SoftwareSection::make($server) : null,
            user() !== null ? PerformanceSection::make($server) : null,
        ]));
    }

    /** @return array<string, mixed> */
    public function serverSettingsFill(Server $server): array
    {
        return [
            ...(SoftwareSection::canUse($server) ? SoftwareSection::fill($server) : []),
            ...PerformanceSection::fill($server),
        ];
    }

    /**
     * New limits first (a restart applies them), then other software (that reinstalls). Errors point to the fields
     * of this part of the page.
     *
     * @param  array<string, mixed>  $state
     */
    public function serverSettingsSave(Server $server, array $state): bool
    {
        try {
            if (PerformanceSection::canChange($server) && PerformanceSection::changed($server, $state)) {
                PerformanceSection::save($server, $state);
            }
            if (SoftwareSection::canUse($server) && SoftwareSection::changed($server, $state)) {
                return SoftwareSection::install($server, $state);
            }
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $key => $messages) {
                $errors['data.sections.'.self::KEY.'.'.preg_replace('/^data\./', '', $key)] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }

        return true;
    }
}
