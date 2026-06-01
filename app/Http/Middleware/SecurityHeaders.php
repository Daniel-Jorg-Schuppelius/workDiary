<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityHeaders.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders {
    public function handle(Request $request, Closure $next): Response {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->buildCsp($request));
        }

        // HSTS nur über HTTPS aktiv schalten (verhindert Bruch bei lokaler HTTP-Entwicklung)
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function buildCsp(Request $request): string {
        // Vite Dev-Server (HMR) im non-prod Modus zulassen.
        $viteDev = app()->environment('production')
            ? ''
            : ' http://127.0.0.1:5173 http://localhost:5173 ws://127.0.0.1:5173 ws://localhost:5173';

        // Kartenkacheln werden clientseitig als <img> vom (ggf. self-hosted)
        // Tile-Server geladen. Dessen Origin muss explizit in img-src stehen,
        // sonst blockiert der Browser die Tiles und die Karte bleibt grau.
        $tileOrigin = $this->originFromUrl(\App\Support\Setting::get('routing.tiles.url'));
        $imgHosts = $tileOrigin !== '' ? ' ' . $tileOrigin : '';

        $directives = [
            "default-src 'self'",
            // Tailwind/daisyUI sind kompiliert; inline Styles für Alpine x-bind/Color-Tokens noch erlaubt.
            // Fonts (IBM Plex Sans, Space Grotesk, Material Symbols) werden lokal aus dem App-Bundle ausgeliefert.
            "style-src 'self' 'unsafe-inline'" . $viteDev,
            // Inline-Scripts in Auth-/Legacy-Views vorhanden; bis Refactor kompatibel halten.
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'" . $viteDev,
            "img-src 'self' data: blob:" . $imgHosts,
            // Webfonts werden im Production-Build aus /build/assets/ geladen.
            // Im Dev-Modus liefert Vite die Fonts unter $viteDev aus
            // (node_modules/@fontsource/… und node_modules/material-symbols/…),
            // daher muss die gleiche Origin auch hier zugelassen sein, sonst
            // blockiert der Browser die woff2-Requests still und fällt auf
            // Times / unrenderte Material-Symbol-Ligaturen zurück.
            "font-src 'self' data:" . $viteDev,
            "connect-src 'self'" . $viteDev,
            "media-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "worker-src 'self' blob:",
            "manifest-src 'self'",
        ];

        return implode('; ', $directives);
    }

    /**
     * Reduziert eine (ggf. Platzhalter enthaltende) URL auf ihre CSP-Origin
     * scheme://host[:port]. Leerstring, wenn keine gültige http(s)-Origin.
     */
    private function originFromUrl(mixed $url): string {
        if (! is_string($url) || $url === '') {
            return '';
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        $origin = $scheme . '://' . $parts['host'];
        if (! empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin;
    }
}
