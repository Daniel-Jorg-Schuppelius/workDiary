<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssignRequestId.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Correlation-ID je Request (Feature 041, 041-P0 für MVP-053):
 * akzeptiert ein valides eingehendes X-Request-Id (Proxy/Load-Balancer)
 * oder erzeugt eine ULID. Die ID landet im Log-Kontext, im
 * Response-Header und via Container in Fehlerseiten/Problem-Dialog —
 * damit Support Logeinträge einer konkreten Meldung zuordnen kann.
 */
class AssignRequestId {
    public const HEADER = 'X-Request-Id';

    public const CONTAINER_KEY = 'requestId';

    public function handle(Request $request, Closure $next): Response {
        $incoming = (string) $request->headers->get(self::HEADER, '');
        $requestId = preg_match('/^[A-Za-z0-9\-_.]{8,64}$/', $incoming) === 1
            ? $incoming
            : (string) Str::ulid();

        app()->instance(self::CONTAINER_KEY, $requestId);
        Log::shareContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}
