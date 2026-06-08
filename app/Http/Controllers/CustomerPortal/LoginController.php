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

        if (! Auth::guard('customer')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = Auth::guard('customer')->user();
        // Defense-in-Depth: Der CustomerUserProvider filtert bereits auf
        // customer_id IS NOT NULL. Hier zusaetzlich pruefen, ob das Modell
        // tatsaechlich einem Kunden zugeordnet ist (kein Fehlkonfig-Fall).
        if (! $user instanceof \App\Models\User || ! $user->isCustomer()) {
            Auth::guard('customer')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // Zwei-Faktor aktiv: Identität parken, erst nach Code-Eingabe voll einloggen.
        if ($user->hasTwoFactorEnabled()) {
            $remember = $request->boolean('remember');
            Auth::guard('customer')->logout();
            $request->session()->put('auth.customer.2fa.id', $user->getKey());
            $request->session()->put('auth.customer.2fa.remember', $remember);

            return redirect()->route('customer.two-factor.login');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('customer.dashboard'));
    }

    public function logout(Request $request): RedirectResponse {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('customer.login');
    }
}
