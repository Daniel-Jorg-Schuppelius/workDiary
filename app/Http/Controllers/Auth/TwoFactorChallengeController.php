<?php
/*
 * Created on   : Sun Jun 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TwoFactorChallengeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\ResolvesWorkMode;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, RateLimiter};
use Illuminate\View\View;

/**
 * Zweiter Login-Schritt: TOTP- oder Recovery-Code. Die zu authentifizierende
 * Identität liegt in der Session (auth.2fa.id) und wird erst hier eingeloggt.
 */
class TwoFactorChallengeController extends Controller {
    use ResolvesWorkMode;

    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function create(Request $request): View|RedirectResponse {
        if (! $request->session()->has('auth.2fa.id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request): RedirectResponse {
        $userId = $request->session()->get('auth.2fa.id');
        if ($userId === null) {
            return redirect()->route('login');
        }

        $throttleKey = '2fa:' . $userId . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors(['code' => __('auth.throttle', ['seconds' => $seconds])]);
        }

        $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $user = User::query()->find($userId);
        if (! $user instanceof User || ! $user->hasTwoFactorEnabled()) {
            $request->session()->forget(['auth.2fa.id', 'auth.2fa.remember']);

            return redirect()->route('login');
        }

        $code = trim((string) $request->input('code', ''));
        $recovery = trim((string) $request->input('recovery_code', ''));
        $passed = false;

        if ($code !== '' && $this->twoFactor->verify((string) $user->two_factor_secret, $code)) {
            $passed = true;
        } elseif ($recovery !== '' && $this->consumeRecoveryCode($user, $recovery)) {
            $passed = true;
        }

        if (! $passed) {
            RateLimiter::hit($throttleKey, 60);

            return back()->withErrors(['code' => __('Der Code ist ungültig.')]);
        }

        RateLimiter::clear($throttleKey);
        $remember = (bool) $request->session()->pull('auth.2fa.remember', false);
        $request->session()->forget('auth.2fa.id');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return $this->applyWorkModeAndRedirect($request, $user);
    }

    /** Prüft einen Recovery-Code und verbraucht ihn (einmalig). */
    private function consumeRecoveryCode(User $user, string $recovery): bool {
        /** @var list<string> $codes */
        $codes = (array) ($user->two_factor_recovery_codes ?? []);
        $index = array_search($recovery, $codes, true);
        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

        return true;
    }
}
