<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : B2bCatalogSecurityHeaders.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware\B2bCatalog;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sicherheits-Header des öffentlichen Punchout-Katalogs (Feature 099,
 * MVP-457). Strikte CSP ohne Drittanbieter-Ressourcen — Muster
 * {@see \App\Http\Middleware\Careers\CareerPortalSecurityHeaders}. Zwei
 * Besonderheiten des OCI-Flows:
 *
 * - `form-action` erhält zusätzlich die HTTPS-Origin der validierten
 *   `HOOK_URL` (Request-Attribut `b2b.form_action`), sonst blockt der Browser
 *   die Warenkorb-Rückgabe an das Einkaufssystem.
 * - `script-src` erlaubt das per Nonce signierte Auto-Submit-Inline-Script
 *   der Transfer-Seite (@cspNonce).
 */
class B2bCatalogSecurityHeaders {
    public function handle(Request $request, Closure $next): Response {
        $response = $next($request);
        $h = $response->headers;

        $formAction = "'self'";
        $extra = (string) $request->attributes->get('b2b.form_action', '');
        if ($extra !== '' && str_starts_with($extra, 'https://')) {
            $formAction .= ' ' . $extra;
        }

        $nonce = Vite::cspNonce();
        $scriptSrc = "script-src 'self'" . (is_string($nonce) && $nonce !== '' ? " 'nonce-{$nonce}'" : '');

        $csp = implode('; ', [
            "default-src 'none'",
            "base-uri 'none'",
            'form-action ' . $formAction,
            "frame-ancestors 'none'",
            "img-src 'self' data:",
            // Inline-Styles erlaubt (self-contained Layout), Skripte nur mit Nonce.
            "style-src 'self' 'unsafe-inline'",
            $scriptSrc,
            "font-src 'self'",
            "connect-src 'self'",
        ]);

        $h->set('Content-Security-Policy', $csp);
        $h->set('Referrer-Policy', 'no-referrer');
        $h->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $h->set('Pragma', 'no-cache');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'DENY');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        $h->set('Cross-Origin-Resource-Policy', 'same-origin');

        return $response;
    }
}
