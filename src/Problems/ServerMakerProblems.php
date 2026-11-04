<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Problems;

use App\Models\User;

/**
 * The Server Maker's errors for Simplify-Explain (tag simplify.problems): who may see one, and the lines behind it.
 * Only the person who got the error and root admins; the exception and its trace only for root admins.
 */
final class ServerMakerProblems
{
    private const TITLES = [
        'create' => 'simplify-servermaker::maker.review.failed',
        'software' => 'simplify-servermaker::maker.software.failed',
        'token' => 'simplify-servermaker::maker.steam.token_failed',
    ];

    private const CONTEXT = [
        'create' => 'Creating a game server with the Server Maker (a Pelican panel plugin) failed.',
        'software' => 'Changing the server software of a Minecraft server with the Server Maker failed; the old settings were restored.',
        'token' => 'Creating a Steam game server login token with the user\'s own Steam Web API key failed.',
    ];

    public function canExplainProblem(string $id, User $user): bool
    {
        $record = ProblemLog::find($id);

        return $record !== null && ($user->isRootAdmin() || $record['user_id'] === (int) $user->id);
    }

    /** @return array{title: string, context: string, lines: list<string>}|null */
    public function explainProblem(string $id, User $user): ?array
    {
        $record = ProblemLog::find($id);
        if ($record === null || ! $this->canExplainProblem($id, $user)) {
            return null;
        }
        $lines = [$record['message'], ...($user->isRootAdmin() ? $record['trace'] : [])];

        return [
            'title' => (string) __(self::TITLES[$record['kind']]),
            'context' => self::CONTEXT[$record['kind']],
            'lines' => array_slice(array_filter($lines, fn ($line) => $line !== ''), 0, 100),
        ];
    }
}
