<?php
/*
 * Created on   : Sat May 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoginController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login/Logout fuer das Customer-Portal. Verwendet den dedizierten
 * `customer`-Guard mit eigenem User-Provider (filter customer_id IS NOT NULL).
 */
class LoginController extends Controller {
    public function showLoginForm(): View {
        return view('customer.login');
    }

    public function login(Request $request): RedirectResponse {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Erst prüfen, dann entscheiden — NICHT anmelden: `attempt()` feuert das
        // Login-Ereignis schon vor dem zweiten Faktor, und die Geräteerkennung
        // merkte sich das Gerät damit ohne ihn (Sicherheitsaudit 2026-09-17,
        // portal-auth-1; Spiegelbild von S-51 im internen Login).
        $guard = Auth::guard('customer');
        // `getLastAttempted()` gehört dem SessionGuard, nicht dem Vertrag.
        $attempted = static fn (): ?\Illuminate\Contracts\Auth\Authenticatable => $guard instanceof SessionGuard ? $guard->getLastAttempted() : null;

        if (! $guard->validate($credentials)) {
            event(new Failed('customer', $attempted(), ['email' => $credentials['email']]));

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = $attempted();
        // Defense-in-Depth: Der CustomerUserProvider filtert bereits auf
        // customer_id IS NOT NULL. Hier zusaetzlich pruefen, ob das Modell
        // tatsaechlich einem Kunden zugeordnet ist (kein Fehlkonfig-Fall).
        if (! $user instanceof \App\Models\User || ! $user->isCustomer()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // Fixation-Schutz: Session-ID unmittelbar nach erfolgreicher
        // Anmeldung rotieren (symmetrisch zum web-Login), damit eine vorab
        // fixierte ID weder den vollen Login noch den 2FA-Park-Marker trägt.
        $request->session()->regenerate();

        // Zwei-Faktor aktiv: Identität parken, erst nach Code-Eingabe einloggen —
        // bis dahin gibt es kein Login-Ereignis und kein bekanntes Gerät.
        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('auth.customer.2fa.id', $user->getKey());
            $request->session()->put('auth.customer.2fa.remember', $request->boolean('remember'));

            return redirect()->route('customer.two-factor.login');
        }

        $guard->login($user, $request->boolean('remember'));

        return redirect()->intended(route('customer.dashboard'));
    }

    public function logout(Request $request): RedirectResponse {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('customer.login');
    }
}
