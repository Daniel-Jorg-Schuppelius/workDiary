<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingSecurityHeaders.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware\Whistleblowing;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strikte Sicherheits-Header fuer das oeffentliche Meldeportal (Abschnitt 6.2):
 * restriktive CSP ohne Drittanbieter-Ressourcen, `Referrer-Policy: no-referrer`,
 * `Cache-Control: no-store`. Bewusst eigenstaendig (nicht der allgemeine
 * SecurityHeaders-Stack), damit das Portal kein App-Verhalten erbt.
 */
class WhistleblowingSecurityHeaders {
    public function handle(Request $request, Closure $next): Response {
        $response = $next($request);
        $h = $response->headers;

        $csp = implode('; ', [
            "default-src 'none'",
            "base-uri 'none'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "img-src 'self' data:",
            "style-src 'self'",
            "script-src 'self'",
            "font-src 'self'",
            "connect-src 'self'",
        ]);

        $h->set('Content-Security-Policy', $csp);
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
