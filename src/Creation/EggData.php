<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Creation;

use App\Models\Egg;

/** The parts of an egg the Server Maker needs, as plain values, so the payload can be built and tested without a panel. */
final readonly class EggData
{
    /**
     * @param  array<string, array{default: string, user: bool, required?: bool}>  $variables  env variable => default, whether users
     *                                                                                    may set it and whether the egg needs a value
     * @param  array<string, string>  $images  label => Docker image
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $variables,
        public array $images,
        public string $startup,
    ) {}

    public static function from(Egg $egg): self
    {
        $variables = [];
        foreach ($egg->variables as $variable) {
            $variables[(string) $variable->env_variable] = [
                'default' => (string) ($variable->default_value ?? ''),
                'user' => (bool) ($variable->user_editable && $variable->user_viewable),
                'required' => in_array('required', self::rules($variable->rules), true),
            ];
        }

        return new self(
            (int) $egg->id,
            (string) $egg->name,
            $variables,
            array_filter((array) $egg->docker_images, 'is_string'),
            (string) (collect($egg->startup_commands)->first() ?? ''),
        );
    }

    /** @return list<string> an egg variable's rules, saved as a list or (older eggs) as "required|string" */
    private static function rules(mixed $rules): array
    {
        $rules = is_string($rules) ? explode('|', $rules) : (array) $rules;

        return array_values(array_map(fn ($rule) => trim((string) $rule), array_filter($rules, 'is_string')));
    }

    /** @return array<string, string> */
    public function defaults(): array
    {
        return array_map(fn (array $variable) => $variable['default'], $this->variables);
    }

    public function firstImage(): ?string
    {
        $images = array_values($this->images);

        return $images[0] ?? null;
    }
}
