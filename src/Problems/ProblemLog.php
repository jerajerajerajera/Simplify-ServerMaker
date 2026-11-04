<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Problems;

use App\Models\User;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * What went wrong when the Server Maker showed an error, kept for a day so Simplify-Explain can explain it
 * (id simplify-servermaker:<kind>:<ref>). Without Explain the record is never read and simply expires.
 */
final class ProblemLog
{
    public const KINDS = ['create', 'software', 'token'];

    /** Explain's button for an error notification (Simplify-Explain 2.0+), looked up by name so Explain stays optional. */
    private const EXPLAIN = 'Simplify\\Explain\\Problems\\Problems';

    /** Keeps the error for its user and returns the problem id. */
    public static function record(string $kind, User $user, string $message, ?Throwable $exception = null): string
    {
        $ref = Str::random(24);
        $trace = [];
        if ($exception !== null) {
            $trace[] = $exception::class.': '.$exception->getMessage();
            $trace[] = 'at '.$exception->getFile().':'.$exception->getLine();
            foreach (array_slice($exception->getTrace(), 0, 40) as $frame) {
                $trace[] = '  '.($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? '').'() '.($frame['file'] ?? '').':'.($frame['line'] ?? '');
            }
        }
        Cache::put(self::key($ref), ['user_id' => (int) $user->id, 'kind' => $kind, 'message' => $message, 'trace' => $trace], now()->addDay());

        return 'simplify-servermaker:'.$kind.':'.$ref;
    }

    /**
     * The record behind an id, or null when it is not the Server Maker's or has expired.
     *
     * @return array{user_id: int, kind: string, message: string, trace: list<string>}|null
     */
    public static function find(string $id): ?array
    {
        if (! preg_match('/^simplify-servermaker:([a-z]+):([A-Za-z0-9]{24})$/', $id, $parts) || ! in_array($parts[1], self::KINDS, true)) {
            return null;
        }
        $record = Cache::get(self::key($parts[2]));

        return is_array($record) && ($record['kind'] ?? null) === $parts[1] ? $record : null;
    }

    /** @return list<Action> Explain's button for the error notification, when Explain is there and on for this user. */
    public static function explainActions(string $id): array
    {
        $class = self::EXPLAIN;
        $explain = class_exists($class) && method_exists($class, 'notificationAction') ? $class::notificationAction($id) : null;

        return $explain instanceof Action ? [$explain] : [];
    }

    private static function key(string $ref): string
    {
        return 'simplify-servermaker.problem.'.$ref;
    }
}
