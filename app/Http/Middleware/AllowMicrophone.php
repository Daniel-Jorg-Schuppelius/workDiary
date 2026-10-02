<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AllowMicrophone.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Öffnet das Mikrofon für die eigene Herkunft (MVP-1060) auf den Seiten der
 * internen App: Formulare mit Diktat öffnen als Dialog auf beliebigen Seiten,
 * und die Freigabe gilt für das Dokument, nicht für den Dialog. Portale und
 * öffentliche Seiten behalten die Vorgabe von {@see SecurityHeaders}.
 */
class AllowMicrophone {
    public function handle(Request $request, Closure $next): Response {
        $response = $next($request);
        if (! $response->headers->has('Permissions-Policy')) {
            $response->headers->set('Permissions-Policy', 'camera=(), microphone=(self), geolocation=(), payment=()');
        }

        return $response;
    }
}
