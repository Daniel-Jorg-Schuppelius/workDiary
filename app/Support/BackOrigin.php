<?php
/*
 * Created on   : Sat Nov 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackOrigin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Rücksprung einer Detailseite zur Seite, von der sie geöffnet wurde
 * (MVP-1778). Der Rückpfeil mit `back-route` führte immer zur Liste, auch wenn
 * der Nutzer aus einer anderen Akte, dem Dashboard oder einem Bericht kam.
 *
 * Je Seite (Pfad) merkt die Sitzung die Herkunft aus dem Referer. Drei Regeln
 * halten das stabil:
 * - Nach einer Aktion auf der Seite ist sie selbst der Referer: die Herkunft bleibt.
 * - Wer von einer Seite zurückkommt, die er von hier geöffnet hat, überschreibt
 *   nichts — zwei Akten, die aufeinander verweisen, pendeln sonst endlos.
 * - Formularseiten (`.create`, `.edit`) sind keine Herkunft.
 */
final class BackOrigin {
    private const string SESSION_KEY = 'back_origins';

    /** So viele Herkünfte hält die Sitzung; ältere fallen heraus. */
    private const int LIMIT = 40;

    private const array FORM_ROUTE_SUFFIXES = ['.create', '.edit'];

    /** Ziel des Rückpfeils: die gemerkte Herkunft, sonst `$fallback` (die Liste). */
    public static function resolve(Request $request, ?string $fallback): ?string {
        if (! $request->isMethod('GET') || ! $request->hasSession()) {
            return $fallback;
        }
        $path = self::pathOf($request->url());
        $stored = $request->session()->get(self::SESSION_KEY, []);
        /** @var array<string, string> $origins */
        $origins = is_array($stored) ? array_filter($stored, 'is_string') : [];

        $caller = self::caller($request, $path);
        if ($caller !== null) {
            $callerPath = self::pathOf($caller);
            $returning = isset($origins[$callerPath]) && self::pathOf($origins[$callerPath]) === $path;
            if (! $returning) {
                unset($origins[$path]);
                $origins[$path] = $caller;
                $request->session()->put(self::SESSION_KEY, array_slice($origins, -self::LIMIT, null, true));
            }
        }

        $origin = $origins[$path] ?? null;
        // Herkunft ist die Liste selbst: der Listen-Rücksprung kennt ihre zuletzt gesetzten Filter.
        if ($origin === null || ($fallback !== null && self::pathOf($origin) === self::pathOf($fallback))) {
            return $fallback;
        }

        return $origin;
    }

    /** Aufrufende Seite: Referer derselben App, eine andere Seite als diese und keine Formularseite. */
    private static function caller(Request $request, string $path): ?string {
        $referer = (string) $request->headers->get('referer', '');
        if ($referer === '' || ! UrlSafety::isSameOriginOrRelative($referer, $request->getHost()) || self::pathOf($referer) === $path) {
            return null;
        }
        try {
            $name = (string) app('router')->getRoutes()->match(Request::create($referer))->getName();
        } catch (HttpExceptionInterface) {
            return null;
        }
        foreach (self::FORM_ROUTE_SUFFIXES as $suffix) {
            if (str_ends_with($name, $suffix)) {
                return null;
            }
        }

        return $referer;
    }

    private static function pathOf(string $url): string {
        return '/' . trim((string) parse_url($url, PHP_URL_PATH), '/');
    }
}
