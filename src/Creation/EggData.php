<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Creation;

use App\Models\Egg;

/** The parts of an egg the Server Maker needs, as plain values, so the payload can be built and tested without a panel. */
final readonly class EggData
{
    /**
     * @param  array<string, array{default: string, user: bool}>  $variables  env variable => default and whether users may set it
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
