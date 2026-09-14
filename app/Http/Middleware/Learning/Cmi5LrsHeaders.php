<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Cmi5LrsHeaders.php
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
 * Header des cmi5-LRS: eigene CORS-Regel und die xAPI-Version.
 *
 * Die AUs laufen auf dem Inhalts-Host oder ganz extern. Das LRS setzt kein Cookie
 * und kennt keine Sitzung — der Token im Authorization-Header ist die einzige
 * Schranke, deshalb darf jeder Ursprung anfragen.
 */
final class Cmi5LrsHeaders {
    use SetsTransportSecurity;

    public function handle(Request $request, Closure $next): Response {
        $response = $request->isMethod('OPTIONS') ? response()->noContent() : $next($request);
        $headers = $response->headers;

        $headers->set('Access-Control-Allow-Origin', '*');
        $headers->set('Access-Control-Allow-Methods', 'GET, PUT, POST, DELETE, OPTIONS');
        $headers->set('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-Experience-API-Version, If-Match, If-None-Match');
        $headers->set('Access-Control-Expose-Headers', 'ETag, Last-Modified, X-Experience-API-Version, X-Experience-API-Consistent-Through');
        $headers->set('Access-Control-Max-Age', '600');
        $headers->set('X-Experience-API-Version', '1.0.3');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'no-referrer');
        $headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");

        $this->applyTransportSecurity($request, $response);

        return $response;
    }
}
