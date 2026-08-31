<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScimSecurityHeaders.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\SetsTransportSecurity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Schlanke Header für den SCIM-Stack (Sicherheitsscan 2026-08-23, S-62).
 *
 * SCIM läuft in einer eigenen Gruppe ohne `SecurityHeaders` und hatte damit
 * weder HSTS noch `nosniff` noch eine Cache-Vorgabe. Über die Schnittstelle
 * gehen Verzeichnisdaten — Namen, Adressen, Kontenzustände — und Antworten
 * darauf gehören in keinen Zwischenspeicher.
 *
 * Bewusst kein CSP: SCIM liefert JSON, keine Seiten.
 */
class ScimSecurityHeaders {
    use SetsTransportSecurity;

    public function handle(Request $request, Closure $next): Response {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'no-store, max-age=0');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $this->applyTransportSecurity($request, $response);

        return $response;
    }
}
