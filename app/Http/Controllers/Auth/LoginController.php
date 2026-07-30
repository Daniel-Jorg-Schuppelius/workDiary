<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoginController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\ResolvesWorkMode;
use App\Http\Controllers\Controller;
use App\Legacy\LegacyBridge;
use App\Models\{SsoConnection, User};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, DB, RateLimiter};
use Illuminate\View\View;

class LoginController extends Controller {
    use ResolvesWorkMode;

    private const MAX_LOGIN_ATTEMPTS = 5;

    public function showLoginForm(): View {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'username' => __('auth.throttle', ['seconds' => $seconds]),
            ])->onlyInput('username');
        }

        // SSO-Pflicht (Feature 057): zum SSO-Start umleiten; harte Sperre sitzt zusätzlich im LegacyUserProvider.
        $ssoRedirect = $this->ssoEnforcedRedirect($credentials['username']);
        if ($ssoRedirect !== null) {
            return $ssoRedirect;
        }

        if (Auth::attempt(['username' => $credentials['username'], 'password' => $credentials['password']], $request->boolean('remember'))) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();
            $this->auditBreakGlassIfApplicable();

            $this->syncLegacyUserIdIfMissing((string) $credentials['username']);

            /** @var User|null $user */
            $user = Auth::user();

            // Zwei-Faktor aktiv: noch NICHT voll einloggen, Identität bis zur Code-Eingabe in der Session parken.
            if ($user instanceof User && $user->hasTwoFactorEnabled()) {
                $remember = $request->boolean('remember');
                Auth::logout();
                $request->session()->put('auth.2fa.id', $user->getKey());
                $request->session()->put('auth.2fa.remember', $remember);

                return redirect()->route('two-factor.login');
            }

            if ($user instanceof User) {
                return $this->applyWorkModeAndRedirect($request, $user);
            }

            return redirect()->intended(route('home'));
        }

        RateLimiter::hit($throttleKey, 60);

        return back()->withErrors([
            'username' => 'Benutzername oder Passwort ist falsch.',
        ])->onlyInput('username');
    }

    private function throttleKey(Request $request): string {
        return 'login:' . mb_strtolower((string) $request->input('username', '')) . '|' . $request->ip();
    }

    public function logout(Request $request): RedirectResponse {
        // OIDC-RP-initiated Logout (Feature 057): end_session-Daten VOR dem Invalidieren der Session sichern.
        /** @var array{end_session_endpoint?: string, id_token?: string} $ssoLogout */
        $ssoLogout = (array) $request->session()->get('sso.logout', []);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $endSession = (string) ($ssoLogout['end_session_endpoint'] ?? '');
        if ($endSession !== '') {
            $params = array_filter([
                'id_token_hint' => (string) ($ssoLogout['id_token'] ?? ''),
                'post_logout_redirect_uri' => route('home'),
            ]);

            return redirect()->away($endSession . (str_contains($endSession, '?') ? '&' : '?') . http_build_query($params));
        }

        return redirect()->route('home');
    }

    /**
     * Erzwingt eine Organisation SSO und ist das Konto kein Break-Glass-Konto,
     * wird der Passwort-Login gar nicht erst versucht, sondern zum SSO-Start
     * umgeleitet. Lookup wie im Provider: Legacy-Name ODER E-Mail.
     */
    private function ssoEnforcedRedirect(string $username): ?RedirectResponse {
        $user = User::query()
            ->withoutGlobalScopes()
            ->whereNull('customer_id')
            ->where(fn ($query) => $query->where('name', $username)->orWhere('email', $username))
            ->first();

        if (
            ! $user instanceof User
            || $user->sso_exempt
            || ! SsoConnection::enforcementActiveFor($user->organization_id)
        ) {
            return null;
        }

        $slug = $user->organization()->withoutGlobalScopes()->value('slug');

        return is_string($slug) && $slug !== ''
            ? redirect()->route('sso.start', ['slug' => $slug])
            : null;
    }

    /**
     * Break-Glass-Nachweis: erfolgreicher Passwort-Login eines sso_exempt-
     * Kontos bei aktiver SSO-Pflicht wird auditiert (DoD MVP-120).
     */
    private function auditBreakGlassIfApplicable(): void {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->sso_exempt) {
            return;
        }

        $connection = SsoConnection::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $user->organization_id)
            ->where('active', true)
            ->where('enforced', true)
            ->first();

        $connection?->audit('sso.break_glass_used', ['user_id' => $user->id]);
    }

    private function syncLegacyUserIdIfMissing(string $submittedUsername): void {
        $authUser = Auth::user();

        if (! $authUser instanceof User || (int) ($authUser->legacy_user_id ?? 0) > 0) {
            return;
        }

        if (! filled(config('database.connections.legacy.database'))) {
            return;
        }

        try {
            // attempt(): kein Connect-Versuch bei als down markierter legacy-DB; Mapping ist Best-Effort.
            $legacy = LegacyBridge::attempt(function () use ($submittedUsername, $authUser): ?object {
                $found = DB::connection('legacy')
                    ->table('user')
                    ->select(['id', 'uname'])
                    ->where('uname', $submittedUsername)
                    ->first();

                if (! $found && filled($authUser->name)) {
                    $found = DB::connection('legacy')
                        ->table('user')
                        ->select(['id', 'uname'])
                        ->where('uname', (string) $authUser->name)
                        ->first();
                }

                return $found;
            }, null);

            if ($legacy && (int) $legacy->id > 0) {
                $authUser->legacy_user_id = (int) $legacy->id;
                $authUser->save();
            }
        } catch (\Throwable) {
            // Legacy-Mapping ist ein Best-Effort und darf den Login nicht blockieren.
        }
    }
}
