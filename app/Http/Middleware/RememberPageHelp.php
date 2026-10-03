<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RememberPageHelp.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Help\PageHelpAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gibt das Hilfethema einer Seite erst frei, wenn die Seite tatsächlich
 * ausgeliefert wurde — eine abgewiesene Anfrage schaltet nichts frei.
 */
class RememberPageHelp {
    public function __construct(
        private readonly PageHelpAccess $access,
    ) {}

    public function handle(Request $request, Closure $next): Response {
        $response = $next($request);

        if ($response->isSuccessful()) {
            $this->access->remember($request);
        }

        return $response;
    }
}
