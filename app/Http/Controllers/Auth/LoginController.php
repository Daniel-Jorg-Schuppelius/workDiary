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

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class LoginController extends Controller {
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

        if (Auth::attempt(['username' => $credentials['username'], 'password' => $credentials['password']], $request->boolean('remember'))) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            $this->syncLegacyUserIdIfMissing((string) $credentials['username']);

            /** @var \App\Models\User|null $user */
            $user = Auth::user();
            $legacyConfigured = filled(config('database.connections.legacy.database'));
            $sessionMode = (string) session('work_mode', 'legacy');

            // Modus an die tatsächlichen Zugriffsrechte des Benutzers
            // anpassen. Sonst landet ein Legacy-only-User u. U. auf einer
            // Route hinter `access.new` (oder umgekehrt) und sieht eine
            // 403-Meldung statt seiner Startseite.
            $canLegacy = $legacyConfigured && ($user?->canAccessLegacy() ?? true);
            $canNew = $user?->canAccessNew() ?? true;

            if ($sessionMode === 'legacy' && ! $canLegacy && $canNew) {
                $sessionMode = 'new';
            } elseif ($sessionMode === 'new' && ! $canNew && $canLegacy) {
                $sessionMode = 'legacy';
            } elseif (! $canLegacy && ! $canNew) {
                // Konsistenter Fallback: HomeController zeigt dann eine
                // erklärende Seite ohne 403-Modal.
                $sessionMode = 'legacy';
            }
            $request->session()->put('work_mode', $sessionMode);

            $defaultRoute = ($sessionMode === 'legacy' && $canLegacy)
                ? route('legacy.diary.index')
                : ($canNew ? route('diary.index') : route('home'));

            return redirect()->intended($defaultRoute);
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
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
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
            $legacy = DB::connection('legacy')
                ->table('user')
                ->select(['id', 'uname'])
                ->where('uname', $submittedUsername)
                ->first();

            if (! $legacy && filled($authUser->name)) {
                $legacy = DB::connection('legacy')
                    ->table('user')
                    ->select(['id', 'uname'])
                    ->where('uname', (string) $authUser->name)
                    ->first();
            }

            if ($legacy && (int) $legacy->id > 0) {
                $authUser->legacy_user_id = (int) $legacy->id;
                $authUser->save();
            }
        } catch (\Throwable) {
            // Legacy-Mapping ist ein Best-Effort und darf den Login nicht blockieren.
        }
    }
}
