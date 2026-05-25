<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RequiresFeature.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Middleware;

use App\Services\Licensing\FeatureFlagResolver;
use Closure;
use Illuminate\Http\{JsonResponse, Request};
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * `requires-feature:code` (Folge zu MVP-047).
 *
 * Verwendung in Routen-Definitionen:
 *   Route::get('/x', ...)->middleware('requires-feature:protocols.signed');
 *
 * Liefert bei deaktiviertem Feature 423 Locked. JSON-Aufrufer bekommen
 * ein `{error: 'feature_disabled', code: ...}`-Body, HTML-Aufrufer eine
 * Standard-HttpException (kann später durch eigene 423-View ersetzt
 * werden).
 */
class RequiresFeature {
    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function handle(Request $request, Closure $next, string $code): Response {
        if ($this->features->isEnabled($code)) {
            return $next($request);
        }

        $message = __('Funktion „:code" ist in der aktuellen Lizenz nicht enthalten.', ['code' => $code]);

        if ($request->expectsJson()) {
            return new JsonResponse([
                'error' => 'feature_disabled',
                'code' => $code,
                'message' => $message,
            ], Response::HTTP_LOCKED);
        }

        throw new HttpException(Response::HTTP_LOCKED, $message);
    }
}
