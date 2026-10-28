<?php

// The Simplify add-on contract (plugin id pelican-simplify, contract version 1), as far as these tests need it.

declare(strict_types=1);

namespace Simplify\Core\Addons;

if (! class_exists(Addon::class)) {
    abstract class Addon
    {
        abstract public function id(): string;

        abstract public function name(): string;

        public function description(): string
        {
            return '';
        }

        public function icon(): string
        {
            return 'tabler-puzzle';
        }

        /** @return list<Button> */
        public function dashboardActions(): array
        {
            return [];
        }

        public function settingsUrl(): ?string
        {
            return null;
        }
    }

    final readonly class Button
    {
        public function __construct(
            public string $label,
            public string $url,
            public ?string $icon = null,
            public bool $primary = false,
        ) {}
    }
}
