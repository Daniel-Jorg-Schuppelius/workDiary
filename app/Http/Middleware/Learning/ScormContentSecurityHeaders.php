<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScormContentSecurityHeaders.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware\Learning;

use App\Http\Middleware\Concerns\SetsTransportSecurity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allgemeine Sicherheitsheader des Inhalts-Hosts. Die CSP setzt die jeweilige
 * Antwort selbst — Hülle und Paketdateien brauchen verschiedene.
 */
final class ScormContentSecurityHeaders {
    use SetsTransportSecurity;

    public function handle(Request $request, Closure $next): Response {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('Referrer-Policy', 'no-referrer');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if (! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
        }

        $this->applyTransportSecurity($request, $response);

        return $response;
    }
}
