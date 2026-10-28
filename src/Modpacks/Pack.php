<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Modpacks;

/** One modpack as the Server Maker shows it, whatever site it comes from. */
final readonly class Pack
{
    public function __construct(
        public string $source,
        public string $id,
        public string $title,
        public string $description,
        public ?string $icon,
        public string $url,
    ) {}

    /** "modrinth:AANobbMI": the source and the id in one value, as the form keeps it. */
    public function key(): string
    {
        return $this->source.':'.$this->id;
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key(),
            'source' => $this->source,
            'title' => $this->title,
            'description' => $this->description,
            'icon' => $this->icon,
            'url' => $this->url,
        ];
    }

    /** @return array{0: string, 1: string}|null the source and the id, when the key is valid */
    public static function split(?string $key): ?array
    {
        if (! is_string($key) || ! preg_match('/^(modrinth|ftb|technic|curseforge):([a-zA-Z0-9_-]{1,128})$/', $key, $parts)) {
            return null;
        }
        if (in_array($parts[1], ['ftb', 'curseforge'], true) && ! ctype_digit($parts[2])) {
            return null;
        }

        return [$parts[1], $parts[2]];
    }

    public static function text(mixed $value, int $length): string
    {
        return mb_substr(trim(strip_tags((string) $value)), 0, $length);
    }
}
