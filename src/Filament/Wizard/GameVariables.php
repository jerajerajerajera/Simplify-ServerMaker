<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Filament\Wizard;

use App\Models\Egg;
use App\Models\EggVariable;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Validation\ValidationException;
use Simplify\ServerMaker\Steam\SteamKeys;

/**
 * Form fields for an egg's variables, typed from their rules: a fixed list becomes a select, true/false a toggle,
 * a number a number field. Install and engine options most people never change go under "Advanced".
 */
final class GameVariables
{
    private const ADVANCED = '/^(SRCDS_|STEAM_|AUTO_?UPDATE|INSTALL_|WINDOWS_INSTALL|VALIDATE|BRANCH|BETA|.*_PORT$|.*_BRANCH$|LD_LIBRARY|WINE|CONSOLE_FILTER)/i';

    /** @return array<string, string|bool> starting values, toggles as true/false */
    public static function defaults(Egg $egg, bool $admin): array
    {
        $values = [];
        foreach (self::visible($egg, $admin) as $variable) {
            $default = (string) ($variable->default_value ?? '');
            $values[$variable->env_variable] = self::toggle(self::rules($variable)) !== null
                ? in_array(strtolower($default), ['1', 'true', 'yes', 'on'], true)
                : $default;
        }

        return $values;
    }

    /** @return array{0: list<Field>, 1: list<Field>} the main fields and the advanced ones */
    public static function fields(Egg $egg, bool $admin): array
    {
        $main = [];
        $advanced = [];
        foreach (self::visible($egg, $admin) as $variable) {
            $field = self::field($variable);
            if (preg_match(self::ADVANCED, (string) $variable->env_variable)) {
                $advanced[] = $field;
            } else {
                $main[] = $field;
            }
        }

        return [$main, $advanced];
    }

    /** @return list<EggVariable> */
    private static function visible(Egg $egg, bool $admin): array
    {
        return $egg->variables
            ->filter(fn (EggVariable $variable) => $admin || ($variable->user_editable && $variable->user_viewable))
            ->values()
            ->all();
    }

    private static function field(EggVariable $variable): Field
    {
        $rules = self::rules($variable);
        $path = 'variables.'.$variable->env_variable;
        $default = (string) ($variable->default_value ?? '');
        $choices = self::choices($rules);

        if (($toggle = self::toggle($rules)) !== null) {
            // Saved in the egg's own spelling: 0/1 or false/true.
            [$off, $on] = $toggle;
            $field = Toggle::make($path)
                ->formatStateUsing(fn ($state) => is_bool($state) ? $state : in_array(strtolower((string) $state), ['1', 'true'], true))
                ->dehydrateStateUsing(fn ($state) => $state ? $on : $off);
        } elseif ($choices !== null) {
            $field = Select::make($path)->options(array_combine($choices, $choices))->native(false)->selectablePlaceholder(false);
        } else {
            $field = TextInput::make($path)->rules($rules ?: ['nullable']);
            if (array_intersect($rules, ['numeric', 'integer'])) {
                $field->numeric();
            }
            if (preg_match('/PASS(WORD)?|SECRET|TOKEN/i', (string) $variable->env_variable)) {
                $field->password()->revealable();
            }
        }

        $field->label(trim((string) preg_replace('/^\[[^\]]*\]\s*/', '', (string) $variable->name)))
            ->helperText($variable->description ?: null)
            ->default($default);
        $token = $field instanceof TextInput && SteamKeys::isTokenVariable((string) $variable->env_variable);
        if (in_array('required', $rules, true) && ! $field instanceof Toggle) {
            // Left empty, a Steam login token is made when the server is created.
            $field->required(fn () => ! ($token && SteamKeys::autoTokens(user())));
        }

        return $token ? self::token($field, $variable) : $field;
    }

    /** A Steam login token field: one click (key) or automatically with the person's own Steam key (as in 0.7.0). */
    private static function token(TextInput $field, EggVariable $variable): TextInput
    {
        $t = fn (string $key) => __('simplify-servermaker::maker.steam.'.$key);

        return $field
            ->helperText(fn () => match (true) {
                SteamKeys::autoTokens(user()) => $t('token_auto'),
                SteamKeys::connected(user()) => $variable->description ?: null,
                default => $t('token_connect'),
            })
            ->suffixAction(Action::make('steam_token_'.strtolower((string) $variable->env_variable))
                ->icon('tabler-key')
                ->tooltip($t('token_create'))
                ->visible(fn () => SteamKeys::connected(user()))
                ->action(function (Get $get, Set $set) use ($field, $variable, $t): void {
                    try {
                        $set($field->getName(), SteamKeys::createForGame(user(), (string) $variable->egg?->name, (string) ($get('name') ?: $variable->egg?->name)));
                        Notification::make()->title($t('token_created'))->success()->send();
                    } catch (ValidationException $exception) {
                        Notification::make()->title($t('token_failed'))->body(collect($exception->errors())->flatten()->first())->danger()->send();
                    }
                }));
    }

    /** @return list<string> */
    private static function rules(EggVariable $variable): array
    {
        $rules = is_array($variable->rules) ? $variable->rules : explode('|', (string) $variable->rules);

        return array_values(array_filter(array_map(fn ($rule) => trim((string) $rule), $rules)));
    }

    /** @return list<string>|null */
    private static function choices(array $rules): ?array
    {
        foreach ($rules as $rule) {
            if (str_starts_with($rule, 'in:')) {
                return array_map('trim', explode(',', substr($rule, 3)));
            }
        }

        return null;
    }

    /** @return array{0: string, 1: string}|null the off and on values when the variable is a yes/no choice */
    private static function toggle(array $rules): ?array
    {
        $choices = self::choices($rules);
        if ($choices === null) {
            return in_array('boolean', $rules, true) ? ['0', '1'] : null;
        }
        if (count($choices) !== 2 || array_diff(array_map('strtolower', $choices), ['0', '1', 'true', 'false'])) {
            return null;
        }

        return in_array(strtolower($choices[0]), ['1', 'true'], true) ? [$choices[1], $choices[0]] : [$choices[0], $choices[1]];
    }
}
