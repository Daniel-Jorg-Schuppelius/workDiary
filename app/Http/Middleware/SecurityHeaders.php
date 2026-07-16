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

    /**
     * script-src: streng (Nonce) oder kompatibel (unsafe-inline). 'unsafe-eval' ist an
     * den Alpine-Build gekoppelt (security.csp_alpine_csp_build / ALPINE_CSP_BUILD, wie
     * der Vite-Build-Switch) — nie hart entfernen, solange der Standard-Build läuft.
     */
    private function scriptSrc(): string {
        // Stufe 2: Alpine-CSP-Build aktiv → kein eval mehr nötig.
        $eval = config('security.csp_alpine_csp_build', false) ? '' : " 'unsafe-eval'";

        $nonce = \Illuminate\Support\Facades\Vite::cspNonce();
        if (config('security.csp_script_nonce', false) && is_string($nonce) && $nonce !== '') {
            // Stufe 1: Nonce ersetzt 'unsafe-inline'.
            return "script-src 'self' 'nonce-{$nonce}'" . $eval;
        }

        return "script-src 'self' 'unsafe-inline'" . $eval;
    }

    private function buildCsp(Request $request): string {
        // Vite Dev-Server (HMR) im non-prod Modus zulassen.
        $viteDev = app()->environment('production')
            ? ''
            : ' http://127.0.0.1:5173 http://localhost:5173 ws://127.0.0.1:5173 ws://localhost:5173';

        // Tile-Server-Origin muss explizit in img-src stehen, sonst blockt der Browser die Kartenkacheln.
        $tileOrigin = $this->originFromUrl(\App\Support\Setting::get('routing.tiles.url'));
        $imgHosts = $tileOrigin !== '' ? ' ' . $tileOrigin : '';

        // Reverb-WebSocket (Chat) für connect-src zulassen, sonst blockt die CSP die WS-Verbindung; Host/Port aus Broadcasting-Config + lokale Varianten.
        $reverbHost = (string) config('broadcasting.connections.reverb.options.host', '127.0.0.1');
        $reverbPort = (string) config('broadcasting.connections.reverb.options.port', '8080');
        $reverbHosts = array_values(array_unique(array_filter([$reverbHost, '127.0.0.1', 'localhost'])));
        $reverbWs = '';
        foreach ($reverbHosts as $h) {
            $reverbWs .= " ws://{$h}:{$reverbPort} wss://{$h}:{$reverbPort}";
        }

        $directives = [
            "default-src 'self'",
            // unsafe-inline für Alpine x-bind/Color-Tokens noch nötig.
            "style-src 'self' 'unsafe-inline'" . $viteDev,
            // Nonce ersetzt 'unsafe-inline' bei aktivem csp_script_nonce; Details in scriptSrc().
            $this->scriptSrc() . $viteDev,
            "img-src 'self' data: blob:" . $imgHosts,
            // Dev-Modus: Vite-Origin ($viteDev) muss auch für Webfonts erlaubt sein, sonst still blockierte woff2-Requests.
            "font-src 'self' data:" . $viteDev,
            "connect-src 'self'" . $viteDev . $reverbWs,
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
