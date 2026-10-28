<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Filament\Wizard;

use App\Models\Egg;
use App\Models\EggVariable;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

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
        if (in_array('required', $rules, true) && ! $field instanceof Toggle) {
            $field->required();
        }

        return $field;
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
