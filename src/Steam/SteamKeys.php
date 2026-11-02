<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Steam;

use App\Models\Egg;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Simplify\ServerMaker\Games\SteamArt;
use Simplify\ServerMaker\Settings;
use Throwable;

/**
 * A person's own Steam Web API key, as in Simplify 0.7.0, to create Game Server Login Tokens (GSLT) for games that
 * need one (CS2, Unturned, Insurgency: Sandstorm …). Stored encrypted per person, used only for their own new
 * servers, and never shown, logged or sent to the browser again: only its last four characters.
 */
final class SteamKeys
{
    private const API = 'https://api.steampowered.com/IGameServersService/';

    /** Egg variables that take a Game Server Login Token. */
    public const TOKEN_VARIABLES = ['STEAM_ACC', 'GSLT', 'GSLTT', 'GSLT_TOKEN', 'STEAM_LOGIN_TOKEN', 'TOWER_LOGIN_TOKEN', 'STEAM_TOKEN', 'SRCDS_TOKEN', 'STEAM_GSLT'];

    /** For tests: (method, url, params) => [status, json]. Never used for anything else. */
    public static ?Closure $transport = null;

    /** @return array{connected: bool, hint: ?string, steamid: ?string, auto: bool} */
    public static function status(User $user): array
    {
        $data = self::read($user);

        return [
            'connected' => $data !== null,
            'hint' => $data['hint'] ?? null,
            'steamid' => $data['steamid'] ?? null,
            'auto' => (bool) ($data['auto'] ?? true),
        ];
    }

    public static function connected(?User $user): bool
    {
        return $user !== null && self::read($user) !== null;
    }

    /** New servers get a token by themselves when the field is left empty. */
    public static function autoTokens(?User $user): bool
    {
        return $user !== null && (bool) (self::read($user)['auto'] ?? false);
    }

    public static function isTokenVariable(string $name): bool
    {
        return in_array(strtoupper($name), self::TOKEN_VARIABLES, true);
    }

    /** Checks the key with Steam, then stores it. With a Steam sign-in on the profile the key must be that account's. */
    public static function save(User $user, string $key): void
    {
        $key = strtoupper(trim($key));
        if (! preg_match('/^[A-F0-9]{32}$/', $key)) {
            self::fail('key', 'format');
        }
        [$status, $json] = self::call('GET', 'GetAccountList/v1/', ['key' => $key]);
        if ($status === 0) {
            self::fail('key', 'unreachable');
        }
        if ($status < 200 || $status >= 300) {
            self::fail('key', 'not_accepted');
        }
        $owner = $json['response']['actor'] ?? null;
        $owner = is_scalar($owner) && preg_match('/^\d{17}$/', (string) $owner) ? (string) $owner : null;
        $linked = self::linkedSteamId($user);
        if ($linked !== null && $owner !== null && $owner !== $linked) {
            self::fail('key', 'other_account');
        }
        self::store($user, ['key' => Crypt::encryptString($key), 'hint' => substr($key, -4), 'steamid' => $owner, 'auto' => true]);
    }

    public static function setAuto(User $user, bool $on): void
    {
        $data = self::read($user);
        if ($data !== null) {
            self::store($user, ['auto' => $on] + $data);
        }
    }

    public static function remove(User $user): void
    {
        Settings::put([self::setting($user) => null]);
    }

    /**
     * Fills empty token variables of a new server with a token from the person's key, when they turned that on.
     *
     * @param  array<string, mixed>  $variables  env variable => value
     * @param  bool  $admin  administrators may fill variables people cannot see or change
     * @return array<string, mixed>
     */
    public static function fillTokens(User $user, Egg $egg, array $variables, string $memo, bool $admin): array
    {
        if (! self::autoTokens($user)) {
            return $variables;
        }
        foreach ($egg->variables as $variable) {
            $name = (string) $variable->env_variable;
            $mayFill = $admin || ($variable->user_editable && $variable->user_viewable);
            if ($mayFill && self::isTokenVariable($name) && trim((string) ($variables[$name] ?? '')) === '') {
                try {
                    $variables[$name] = self::createForGame($user, (string) $egg->name, $memo);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(['data.variables.'.$name => collect($exception->errors())->flatten()->first()]);
                }
            }
        }

        return $variables;
    }

    /** A token for the game of an egg; Steam wants the game's own app (CS2: 730), found by name as for the art. */
    public static function createForGame(User $user, string $eggName, string $memo): string
    {
        $game = SteamArt::find($eggName, true);
        if ($game === null) {
            self::fail('token', 'no_game');
        }

        return self::create($user, $game['appid'], $memo);
    }

    public static function create(User $user, int $appId, string $memo): string
    {
        $data = self::read($user);
        if ($data === null) {
            self::fail('token', 'connect_first');
        }
        try {
            $key = Crypt::decryptString((string) $data['key']);
        } catch (Throwable) {
            self::remove($user);
            self::fail('token', 'unreadable');
        }
        [$status, $json] = self::call('POST', 'CreateAccount/v1/', [
            'key' => $key,
            'appid' => $appId,
            'memo' => mb_substr(trim($memo) ?: 'Pelican server', 0, 32),
        ]);
        $token = $json['response']['login_token'] ?? null;
        if ($status === 0) {
            self::fail('token', 'unreachable');
        }
        if (! is_string($token) || ! preg_match('/^[A-Za-z0-9]{16,64}$/', $token)) {
            self::fail('token', in_array($status, [401, 403], true) ? 'refused' : 'no_token');
        }

        return $token;
    }

    /** The SteamID of Pelican's "Sign in with Steam" on the profile, if linked. */
    public static function linkedSteamId(User $user): ?string
    {
        $id = $user->oauth['steam'] ?? null;

        return is_scalar($id) && preg_match('/^\d{17}$/', (string) $id) ? (string) $id : null;
    }

    /**
     * One request to Steam. Errors are never reported: the request carries the key.
     *
     * @return array{0: int, 1: array<mixed>} the status (0 when Steam could not be reached) and the JSON
     */
    private static function call(string $method, string $path, array $params): array
    {
        if (self::$transport) {
            return (self::$transport)($method, self::API.$path, $params);
        }
        try {
            $request = Http::timeout(10)->connectTimeout(3)->acceptJson();
            $response = $method === 'POST' ? $request->asForm()->post(self::API.$path, $params) : $request->get(self::API.$path, $params);
            $json = $response->json();

            return [$response->status(), is_array($json) ? $json : []];
        } catch (Throwable) {
            return [0, []];
        }
    }

    /** @return array{key: string, hint: string, steamid: ?string, auto: bool}|null */
    private static function read(User $user): ?array
    {
        $data = Settings::get(self::setting($user));

        return is_array($data) && is_string($data['key'] ?? null) ? $data : null;
    }

    private static function store(User $user, array $data): void
    {
        Settings::put([self::setting($user) => $data]);
    }

    private static function setting(User $user): string
    {
        return 'steam_key.'.(int) $user->id;
    }

    private static function fail(string $field, string $reason): never
    {
        throw ValidationException::withMessages([$field => __('simplify-servermaker::maker.steam.errors.'.$reason)]);
    }
}
