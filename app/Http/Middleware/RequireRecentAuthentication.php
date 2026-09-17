<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RequireRecentAuthentication.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Auth\RecentAuthentication;
use Closure;
use Illuminate\Http\{JsonResponse, Request, Response};
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Sperrt Aktionen, die aus einem Sitzungszugriff ein dauerhaftes
 * Anmeldemittel machen (Passkey, API-Token, E-Mail-Adresse), bis die
 * Anmeldung frisch ist (Sicherheitsaudit 2026-09-17, authflow-1).
 *
 * Aufruf: `reauth` (interne Bestätigungsseite) bzw. `reauth:customer` für
 * das Kundenportal. JSON-Aufrufer — die WebAuthn-Ceremony ist einer —
 * bekommen 423 mit dem Ziel der Bestätigungsseite statt einer Umleitung.
 */
class RequireRecentAuthentication {
    public function handle(Request $request, Closure $next, string $area = 'web'): SymfonyResponse {
        if (RecentAuthentication::isRecent($request)) {
            return $next($request);
        }

        $target = $area === 'customer' ? route('customer.password.confirm') : route('password.confirm');

        if ($request->expectsJson()) {
            return new JsonResponse([
                'message' => (string) __('Bitte bestätigen Sie zuerst Ihr Passwort.'),
                'redirect' => $target,
            ], Response::HTTP_LOCKED);
        }

        $request->session()->put('url.intended', $request->fullUrl());

        return redirect($target);
    }
}
