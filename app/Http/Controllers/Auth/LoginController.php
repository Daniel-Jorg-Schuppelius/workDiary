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

use App\Http\Controllers\Auth\Concerns\{CompletesLogin, ResolvesWorkMode};
use App\Http\Controllers\Controller;
use App\Legacy\Auth\LegacyUserProvider;
use App\Models\{SsoConnection, User};
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, RateLimiter};
use Illuminate\View\View;

class LoginController extends Controller {
    use CompletesLogin;

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

        // Passwort prüfen, ohne einzuloggen (Sicherheitsscan 2026-08-23, S-51).
        // `attempt()` feuerte das Login-Ereignis schon bei richtigem Passwort:
        // der Audit-Eintrag „auth.login" entstand, Impossible-Travel lief, und
        // das Gerät wurde als bekannt vermerkt — auch wenn danach die
        // Zwei-Faktor-Abfrage kam und niemand sie bestand. Wer nur das Passwort
        // hatte, konnte damit sein Gerät als vertrauenswürdig eintragen und die
        // spätere „Neues Gerät"-Warnung für sich abschalten.
        // Die SSO-Sperre des Providers hier bewusst aussetzen: der Controller
        // muss das Passwort prüfen dürfen, um überhaupt entscheiden zu können,
        // ob er freundlich umleitet (S-41). Eingeloggt wird in dem Fall nicht.
        $validated = LegacyUserProvider::ignoringSsoEnforcement(
            static fn (): bool => Auth::validate(['username' => $credentials['username'], 'password' => $credentials['password']]),
        );

        if ($validated) {
            /** @var User|null $user */
            $user = Auth::getLastAttempted();

            // SSO-Pflicht (Feature 057) ERST JETZT prüfen — nach der
            // Passwortprüfung (Sicherheitsscan 2026-08-23, S-41). Davor
            // antwortete der Login für vorhandene Konten SSO-pflichtiger
            // Mandanten mit einer Umleitung, für unbekannte mit der
            // generischen Fehlermeldung: der Unterschied ließ sich zum
            // Aufzählen gültiger Benutzernamen samt Org-Slug benutzen, ganz
            // ohne Passwort. Wer bis hierher kommt, kennt das Passwort bereits.
            // Die harte Sperre sitzt zusätzlich im LegacyUserProvider.
            $ssoRedirect = $this->ssoEnforcedRedirect($credentials['username']);
            if ($ssoRedirect !== null) {
                // Zähler weiterzählen statt leeren: eine Umleitung ist kein
                // Login, und der Pfad darf nicht ungebremst laufen.
                RateLimiter::hit($throttleKey, 60);

                return $ssoRedirect;
            }

            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            // Zwei-Faktor aktiv: Identität bis zur Code-Eingabe in der Session
            // parken. Eingeloggt — und damit protokolliert — wird erst im
            // Challenge-Controller.
            if ($user instanceof User && $user->hasTwoFactorEnabled()) {
                $request->session()->put('auth.2fa.id', $user->getKey());
                $request->session()->put('auth.2fa.remember', $request->boolean('remember'));
                $request->session()->put('auth.2fa.username', (string) $credentials['username']);

                return redirect()->route('two-factor.login');
            }

            if ($user instanceof User) {
                // Erst hier feuert das Login-Ereignis.
                Auth::login($user, $request->boolean('remember'));

                $this->auditBreakGlassIfApplicable($user);
                $this->syncLegacyUserIdIfMissing($user, (string) $credentials['username']);

                return $this->applyWorkModeAndRedirect($request, $user);
            }

            return redirect()->intended(route('home'));
        }

        RateLimiter::hit($throttleKey, 60);

        // `validate()` feuert — anders als `attempt()` — kein Failed-Ereignis.
        // Ohne diese Zeile verschwände das Sicherheitsprotokoll für
        // Fehllogins, und mit ihm die fail2ban-Korrelation. Bewusst OHNE das
        // Passwort im Payload: der Subscriber braucht nur den Anmeldenamen.
        event(new Failed('web', Auth::getLastAttempted(), ['username' => $credentials['username']]));

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
}
