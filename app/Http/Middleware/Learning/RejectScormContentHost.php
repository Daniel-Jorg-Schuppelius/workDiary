<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RejectScormContentHost.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware\Learning;

use App\Support\Learning\ScormContentHost;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Am Inhalts-Host antwortet die Anwendung nicht.
 *
 * Die Web- und API-Routen haben keine Domain-Bindung und wären sonst auch unter dem
 * Inhalts-Host erreichbar — mit Anmeldung, Sitzung und Formularen in genau dem
 * Ursprung, in dem fremder Kurscode läuft. Läuft vor der Sitzung, damit dort nie ein
 * Cookie entsteht.
 */
final class RejectScormContentHost {
    public function handle(Request $request, Closure $next): Response {
        $host = ScormContentHost::host();

        if ($host !== null && strcasecmp($request->getHost(), $host) === 0) {
            abort(404);
        }

        return $next($request);
    }
}
