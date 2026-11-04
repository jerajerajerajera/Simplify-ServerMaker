<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Creation;

/**
 * Values for variables the egg needs but leaves empty, so a server does not fail on them. Pelican refuses to create
 * a server with such a variable empty, and Paper's own egg stops its install without a User Agent (required, no
 * default, hidden from users). Only an empty required variable changes: first to the value the server had before
 * (a change of software), then, for a User Agent, to a name for Server Maker as PaperMC asks of a download.
 */
final class RequiredValues
{
    /** Names Server Maker and where it lives, as download services such as PaperMC's ask of a user agent. */
    public const USER_AGENT = 'Simplify-ServerMaker (https://github.com/jerajerajerajera/Simplify-ServerMaker)';

    /**
     * @param  array<string, string>  $environment
     * @param  array<string, string>  $own  the values the server has now, for a change of software
     * @return array<string, string>
     */
    public static function fill(array $environment, EggData $egg, array $own = []): array
    {
        foreach ($egg->variables as $name => $variable) {
            if (! ($variable['required'] ?? false) || trim((string) ($environment[$name] ?? '')) !== '') {
                continue;
            }
            if (trim((string) ($own[$name] ?? '')) !== '') {
                $environment[$name] = (string) $own[$name];
            } elseif (preg_match('/^USER_?AGENT$/i', (string) $name) === 1) {
                $environment[$name] = self::USER_AGENT;
            }
        }

        return $environment;
    }
}
