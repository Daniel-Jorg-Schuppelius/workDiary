<?php
/*
 * Created on   : Sun Jun 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TwoFactorChallengeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, RateLimiter};
use Illuminate\View\View;

/**
 * Zweiter Login-Schritt fürs Customer-Portal (guard 'customer'). Identität liegt
 * in der Session (auth.customer.2fa.id) und wird erst nach gültigem Code eingeloggt.
 */
class TwoFactorChallengeController extends Controller {
    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function create(Request $request): View|RedirectResponse {
        if (! $request->session()->has('auth.customer.2fa.id')) {
            return redirect()->route('customer.login');
        }

        return view('customer.two-factor-challenge');
    }

    public function store(Request $request): RedirectResponse {
        $userId = $request->session()->get('auth.customer.2fa.id');
        if ($userId === null) {
            return redirect()->route('customer.login');
        }

        $throttleKey = 'customer-2fa:' . $userId . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            return back()->withErrors(['code' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)])]);
        }

        $user = User::query()->find($userId);
        if (! $user instanceof User || ! $user->isCustomer() || ! $user->hasTwoFactorEnabled()) {
            $request->session()->forget(['auth.customer.2fa.id', 'auth.customer.2fa.remember']);

            return redirect()->route('customer.login');
        }

        $code = trim((string) $request->input('code', ''));
        $recovery = trim((string) $request->input('recovery_code', ''));
        $passed = ($code !== '' && $this->twoFactor->verify((string) $user->two_factor_secret, $code))
            || ($recovery !== '' && $this->consumeRecoveryCode($user, $recovery));

        if (! $passed) {
            RateLimiter::hit($throttleKey, 60);

            return back()->withErrors(['code' => __('Der Code ist ungültig.')]);
        }

        RateLimiter::clear($throttleKey);
        $remember = (bool) $request->session()->pull('auth.customer.2fa.remember', false);
        $request->session()->forget('auth.customer.2fa.id');

        Auth::guard('customer')->login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('customer.dashboard'));
    }

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
