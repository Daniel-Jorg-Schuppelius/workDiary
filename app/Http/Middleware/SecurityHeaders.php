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

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
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

    private function buildCsp(Request $request): string
    {
        // Vite Dev-Server (HMR) im non-prod Modus zulassen.
        $viteDev = app()->environment('production')
            ? ''
            : ' http://127.0.0.1:5173 http://localhost:5173 ws://127.0.0.1:5173 ws://localhost:5173';

        $directives = [
            "default-src 'self'",
            // Tailwind/daisyUI sind kompiliert; inline Styles für Alpine x-bind/Color-Tokens noch erlaubt.
            // Externe Font-Stylesheets (Bunny + Google Material Icons) explizit erlauben.
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com".$viteDev,
            // Inline-Scripts in Auth-/Legacy-Views vorhanden; bis Refactor kompatibel halten.
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'".$viteDev,
            "img-src 'self' data: blob:",
            // Webfonts: Bunny liefert WOFF2 von fonts.bunny.net, Google Material Icons von fonts.gstatic.com.
            "font-src 'self' data: https://fonts.bunny.net https://fonts.gstatic.com",
            "connect-src 'self'".$viteDev,
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
}
