<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacySupportPortalAuthController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Legacy\Http\Concerns\ResolvesLegacySupportCustomer;
use App\Legacy\Http\Middleware\EnsureLegacySupportCustomer;
use App\Legacy\Http\Requests\LegacySupportPortalLoginRequest;
use App\Legacy\Models\LegacySupportCustomer;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Anmeldung der Kunden am Support-Portal gegen `kp_user` (Klartext wie im
 * Altportal, byte-genau verglichen) und die Passwortänderung des Kunden.
 */
class LegacySupportPortalAuthController extends Controller {
    use ResolvesLegacySupportCustomer;

    private const int MAX_LOGIN_ATTEMPTS = 5;

    public function showLoginForm(Request $request): View|RedirectResponse {
        if ($request->session()->has(EnsureLegacySupportCustomer::SESSION_KEY)) {
            return redirect()->route('legacy.portal.orders.index');
        }

        return view('legacy.support.portal.login');
    }

    public function login(LegacySupportPortalLoginRequest $request): RedirectResponse {
        $credentials = $request->validated();
        $throttleKey = 'legacy-support-portal:' . Str::lower((string) $credentials['username']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            return back()->withErrors([
                'username' => __('legacy_support.portal.throttled', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ])->onlyInput('username');
        }

        if (! filled(config('database.connections.legacy.database'))) {
            return back()->withErrors(['username' => __('legacy_support.portal.unavailable')])->onlyInput('username');
        }

        try {
            // Der Anmeldename trifft wie im Altportal ohne Groß-/Kleinschreibung,
            // das Passwort wird in PHP byte-genau geprüft (S-50).
            $customer = LegacySupportCustomer::query()->customers()
                ->where('usern', (string) $credentials['username'])
                ->get(['id', 'kunde', 'usern', 'passw'])
                ->first(fn(LegacySupportCustomer $candidate): bool => hash_equals((string) $candidate->passw, (string) $credentials['password']));
        } catch (\Throwable) {
            return back()->withErrors(['username' => __('legacy_support.portal.unavailable')])->onlyInput('username');
        }

        if (! $customer instanceof LegacySupportCustomer) {
            RateLimiter::hit($throttleKey, 60);

            return back()->withErrors(['username' => __('legacy_support.portal.login_failed')])->onlyInput('username');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        $request->session()->put(EnsureLegacySupportCustomer::SESSION_KEY, (int) $customer->id);
        $request->session()->put(EnsureLegacySupportCustomer::SESSION_FINGERPRINT, EnsureLegacySupportCustomer::fingerprint($customer));

        return redirect()->route('legacy.portal.orders.index');
    }

    public function logout(Request $request): RedirectResponse {
        $request->session()->forget([EnsureLegacySupportCustomer::SESSION_KEY, EnsureLegacySupportCustomer::SESSION_FINGERPRINT]);
        $request->session()->regenerateToken();

        return redirect()->route('legacy.portal.login')->with('status', __('legacy_support.portal.logged_out'));
    }

    public function editPassword(Request $request): View|RedirectResponse {
        if (! (bool) config('app.legacy_write_enabled')) {
            return redirect()->route('legacy.portal.orders.index');
        }

        return view('legacy.support.portal.password', ['customer' => $this->supportCustomer($request)]);
    }

    public function updatePassword(Request $request): RedirectResponse {
        $customer = $this->supportCustomer($request);
        $data = $request->validate([
            'current_password' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:4', 'max:10', 'confirmed', 'not_regex:/[\x00-\x1F\x7F]/'],
        ]);

        if (! hash_equals((string) $customer->passw, (string) $data['current_password'])) {
            return back()->withErrors(['current_password' => __('legacy_support.portal.wrong_password')]);
        }

        $customer->passw = (string) $data['password'];
        $customer->save();
        $request->session()->put(EnsureLegacySupportCustomer::SESSION_FINGERPRINT, EnsureLegacySupportCustomer::fingerprint($customer));

        return redirect()->route('legacy.portal.orders.index')->with('success', __('legacy_support.portal.password_changed'));
    }
}
