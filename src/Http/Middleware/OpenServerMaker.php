<?php

declare(strict_types=1);

namespace Simplify\ServerMaker\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Simplify\ServerMaker\Access;
use Simplify\ServerMaker\CoreApi;
use Symfony\Component\HttpFoundation\Response;

/**
 * With Simplify, Create server is the Server Maker from every entrance. Simplify catches the clicks; this catches the
 * address itself (typed, reloaded or opened in a new tab): Pelican's own form at admin › Servers › Create goes to the
 * list of servers, where Simplify opens Create server in its popup (the view open-create). Without Simplify, and in
 * Simplify's legacy panel (Pelican's own pages), Pelican's form stays.
 */
final class OpenServerMaker
{
    /** The session flash that tells the next page to open Create server. */
    public const OPEN = 'simplify_servermaker_open';

    private const LEGACY = 'simplify_legacy';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || ! $request->routeIs('filament.admin.resources.servers.create')) {
            return $next($request);
        }
        if (! CoreApi::atLeast(1) || ! Access::canUse() || session(self::LEGACY) || $request->query(self::LEGACY) !== null || $request->query('simplify_embed') !== null) {
            return $next($request);
        }

        return redirect()->route('filament.admin.resources.servers.index')->with(self::OPEN, true);
    }
}
