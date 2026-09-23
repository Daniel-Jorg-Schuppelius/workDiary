<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConfirmPasswordController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Auth\SsoConnection;
use App\Models\Platform\User;
use App\Support\Auth\RecentAuthentication;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Hash, RateLimiter};
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Passwortbestätigung vor besonders heiklen Kontoaktionen (Sicherheitsaudit
 * 2026-09-17, authflow-1): Passkey anlegen, API-Token ausstellen,
 * Anmelde-E-Mail wechseln. Eine frische Anmeldung zählt genauso — der
 * Zeitstempel kommt aus {@see RecentAuthentication}.
 *
 * SSO-pflichtige Konten haben kein nutzbares Passwort; für sie führt der Weg
 * über eine erneute Anmeldung beim Identitätsanbieter.
 */
class ConfirmPasswordController extends Controller {
    private const MAX_ATTEMPTS = 5;

    public function show(Request $request): View|RedirectResponse {
        if (RecentAuthentication::isRecent($request)) {
            return redirect()->intended($this->fallback($request));
        }

        $user = $this->user($request);

        return view($this->isPortal($request) ? 'customer.confirm-password' : 'auth.confirm-password', [
            'ssoSlug' => $this->ssoSlug($user),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        $user = $this->user($request);
        $key = 'password.confirm:' . $user->getKey() . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'password' => __('Zu viele Versuche. Bitte später erneut versuchen.'),
            ]);
        }

        $password = (string) $request->input('password', '');
        if ($password === '' || ! Hash::check($password, (string) $user->password)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'password' => __('Das Passwort ist falsch.'),
            ]);
        }

        RateLimiter::clear($key);
        RecentAuthentication::confirm($request);

        return redirect()->intended($this->fallback($request));
    }

    private function user(Request $request): User {
        /** @var User|null $user */
        $user = $request->user();
        abort_if(! $user instanceof User, 403);

        return $user;
    }

    private function isPortal(Request $request): bool {
        return Auth::guard('customer')->check();
    }

    private function fallback(Request $request): string {
        return $this->isPortal($request) ? route('customer.dashboard') : route('home');
    }

    /** Slug der SSO-Organisation, falls das Konto SSO-pflichtig ist (kein Passwortweg). */
    private function ssoSlug(User $user): ?string {
        if ($user->customer_id !== null || $user->sso_exempt || ! SsoConnection::enforcementActiveFor($user->organization_id)) {
            return null;
        }

        $slug = $user->organization()->withoutGlobalScopes()->value('slug');

        return is_string($slug) && $slug !== '' ? $slug : null;
    }
}
