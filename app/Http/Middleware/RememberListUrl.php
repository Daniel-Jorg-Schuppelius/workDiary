<?php
/*
 * Created on   : Sat Sep 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RememberListUrl.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Merkt sich je benannter Route die zuletzt geladene URL samt Filtern,
 * Sortierung und Seite. `redirect()->toList()` führt Aktionen dorthin zurück,
 * statt die Liste auf den Grundzustand zu setzen. Ohne Query-String wird die
 * Erinnerung verworfen — „Zurücksetzen" in der Filterleiste gilt also auch
 * für den nächsten Rücksprung.
 */
class RememberListUrl {
    /** Flache Map Routenname → URL; Punkt-Notation würde `a.b` und `a.b.c` ineinander schachteln. */
    public const SESSION_KEY = 'list_urls';

    public function handle(Request $request, Closure $next): Response {
        $response = $next($request);

        $name = $request->route()?->getName();
        if ($name === null || ! $request->isMethod('GET') || $request->ajax() || $request->expectsJson()
            || ! $request->hasSession() || ! $response->isSuccessful()) {
            return $response;
        }

        $urls = (array) $request->session()->get(self::SESSION_KEY, []);
        $url = $request->getQueryString() === null ? null : $request->fullUrl();
        if (($urls[$name] ?? null) !== $url) {
            if ($url === null) {
                unset($urls[$name]);
            } else {
                $urls[$name] = $url;
            }
            $request->session()->put(self::SESSION_KEY, $urls);
        }

        return $response;
    }
}
