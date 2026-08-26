<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DsarPortalSecurityHeaders.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware\Privacy;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strikte Header des oeffentlichen Betroffenenportals (G11, MVP-728). Wie beim
 * Meldeportal: keine Drittanbieter-Ressourcen, kein Einbetten, kein Caching.
 * Unterschied: das Layout ist self-contained (Inline-Styles wie im
 * Karrierebereich), deshalb `style-src 'self' 'unsafe-inline'` — `script-src`
 * bleibt strikt, die Seite kommt ohne JavaScript aus.
 */
class DsarPortalSecurityHeaders {
    public function handle(Request $request, Closure $next): Response {
        $response = $next($request);
        $h = $response->headers;

        $h->set('Content-Security-Policy', implode('; ', [
            "default-src 'none'",
            "base-uri 'none'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "img-src 'self' data:",
            "style-src 'self' 'unsafe-inline'",
            "script-src 'none'",
            "font-src 'self'",
            "connect-src 'self'",
        ]));
        $h->set('Referrer-Policy', 'no-referrer');
        $h->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $h->set('Pragma', 'no-cache');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'DENY');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), interest-cohort=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        $h->set('Cross-Origin-Resource-Policy', 'same-origin');

        return $response;
    }
}
