<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RequireMeldestelleTwoFactor.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware\Whistleblowing;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Erzwingt aktive Zwei-Faktor-Authentifizierung fuer die interne Fallbearbeitung
 * (Abschnitt 5: „verpflichtende 2FA" fuer die Meldestelle). Greift NUR fuer
 * Personen, die ueberhaupt eine Meldestellen-Permission besitzen – Nicht-
 * Berechtigte fallen unveraendert auf die 403-Pruefung der Policy durch.
 */
class RequireMeldestelleTwoFactor {
    public function handle(Request $request, Closure $next): Response {
        $user = $request->user();

        if ($user instanceof User
            && $user->hasEffectivePermission('whistleblowing.case.viewAny')
            && ! $user->hasTwoFactorEnabled()
        ) {
            return redirect()->route('account.2fa.show')
                ->with('error', __('Die Meldestelle erfordert aktive Zwei-Faktor-Authentifizierung. Bitte richten Sie 2FA ein.'));
        }

        return $next($request);
    }
}
