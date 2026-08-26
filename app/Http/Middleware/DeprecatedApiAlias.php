<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeprecatedApiAlias.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kompatibilitäts-Alias der unversionierten Sanctum-API (MVP-717, Vollscan
 * J10): `/api/<pfad>` liefert dieselbe Antwort wie `/api/v1/<pfad>`, trägt aber
 * die Header `Deprecation: true`, `Sunset` (RFC 8594) und `Link` auf die
 * versionierte Ressource, damit Clients rechtzeitig umstellen können.
 */
class DeprecatedApiAlias {
    /** Abschaltdatum der unversionierten Pfade (RFC 7231 HTTP-Date). */
    public const SUNSET_DATE = '2027-08-31';

    public const SUNSET_HTTP_DATE = 'Tue, 31 Aug 2027 00:00:00 GMT';

    public function handle(Request $request, Closure $next): Response {
        $response = $next($request);

        $response->headers->set('Deprecation', 'true');
        $response->headers->set('Sunset', self::SUNSET_HTTP_DATE);
        $response->headers->set('Link', '<' . $this->versionedUrl($request) . '>; rel="successor-version"');

        return $response;
    }

    private function versionedUrl(Request $request): string {
        $path = $request->path(); // "api/customers/…"
        $versioned = preg_replace('#^api/#', 'api/v1/', $path) ?? $path;
        $query = $request->getQueryString();

        return $request->getSchemeAndHttpHost() . '/' . $versioned . ($query !== null && $query !== '' ? '?' . $query : '');
    }
}
