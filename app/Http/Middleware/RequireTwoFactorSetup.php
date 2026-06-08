<?php
/*
 * Created on   : Sun Jun 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RequireTwoFactorSetup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verlangt die 2FA-Einrichtung, wenn die Organisation des Nutzers sie erzwingt
 * und der Nutzer noch kein bestätigtes 2FA hat. Leitet auf die Einrichtungsseite
 * um (analog zum erzwungenen Passwortwechsel).
 */
class RequireTwoFactorSetup {
    public function handle(Request $request, Closure $next, ?string $guard = null): Response {
        $user = Auth::guard($guard)->user();
        $isCustomer = $guard === 'customer';
        $setupRoute = $isCustomer ? 'customer.2fa.show' : 'account.2fa.show';

        if ($user instanceof User
            && $user->organization?->two_factor_required
            && ! $user->hasTwoFactorEnabled()
            && ! $this->isExempt($request, $isCustomer)) {
            return redirect()->route($setupRoute)
                ->with('warning', __('Ihre Organisation verlangt Zwei-Faktor-Authentifizierung. Bitte richten Sie diese jetzt ein.'));
        }

        return $next($request);
    }

    /** Routen, die auch ohne eingerichtetes 2FA erreichbar bleiben müssen. */
    private function isExempt(Request $request, bool $isCustomer): bool {
        if ($isCustomer) {
            return $request->routeIs('customer.2fa.*')
                || $request->routeIs('customer.two-factor.*')
                || $request->routeIs('customer.logout');
        }

        return $request->routeIs('account.2fa.*')
            || $request->routeIs('logout')
            || $request->routeIs('two-factor.*');
    }
}
