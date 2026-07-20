<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CareerPortalSecurityHeaders.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware\Careers;

use App\Support\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sicherheits-Header des öffentlichen Karrierebereichs (MVP-437).
 *
 * Strikte CSP ohne Drittanbieter-Ressourcen. Der **Unterschied** zum
 * Meldeportal: die einbettbare Ansicht (`careers.embed`) erhält eine dynamische
 * `frame-ancestors`-Liste aus den je Organisation freigegebenen HTTPS-Origins
 * — und **nur dort** wird der widersprechende `X-Frame-Options`-Header
 * entfernt. Die kanonische Seite bleibt `frame-ancestors 'self'` (nicht
 * fremd-einbettbar). Weder pauschales `*` noch fremde Skripte/Tracker.
 */
class CareerPortalSecurityHeaders {
    public function handle(Request $request, Closure $next): Response {
        $response = $next($request);
        $h = $response->headers;

        $isEmbed = $request->routeIs('careers.embed');
        $frameAncestors = $isEmbed ? $this->embedAncestors() : "'self'";

        $csp = implode('; ', [
            "default-src 'none'",
            "base-uri 'none'",
            "form-action 'self'",
            'frame-ancestors ' . $frameAncestors,
            "img-src 'self' data:",
            // Inline-Styles erlaubt (self-contained Layout), aber KEINE fremden
            // Skripte/Tracker — script-src bleibt strikt 'self'.
            "style-src 'self' 'unsafe-inline'",
            "script-src 'self'",
            "font-src 'self'",
            "connect-src 'self'",
        ]);

        $h->set('Content-Security-Policy', $csp);
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), interest-cohort=()');

        if ($isEmbed) {
            // Einbettung ausdrücklich erlaubt → widersprechenden Header entfernen.
            $h->remove('X-Frame-Options');
        } else {
            $h->set('X-Frame-Options', 'SAMEORIGIN');
        }

        return $response;
    }

    /**
     * `frame-ancestors`-Quelle aus den freigegebenen HTTPS-Origins der
     * Organisation. Leer/kein Treffer → `'none'` (kein pauschales `*`).
     */
    private function embedAncestors(): string {
        $origins = self::parseEmbedOrigins((string) Setting::get('applications.portal.embed_origins', ''));

        return $origins === [] ? "'none'" : implode(' ', $origins);
    }

    /**
     * Zerlegt die konfigurierten Origins (Zeilen-/Komma-getrennt) und behält nur
     * gültige `https://host[:port]`-Origins (ohne Pfad, kein `*`).
     *
     * @return list<string>
     */
    public static function parseEmbedOrigins(string $raw): array {
        $parts = preg_split('/[\s,]+/', trim($raw)) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || $part === '*') {
                continue;
            }
            $parsed = parse_url($part);
            if ($parsed === false || ($parsed['scheme'] ?? '') !== 'https' || ! isset($parsed['host'])) {
                continue;
            }
            $origin = 'https://' . $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
            if (! in_array($origin, $out, true)) {
                $out[] = $origin;
            }
        }

        return $out;
    }
}
