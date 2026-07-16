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

use App\Enums\Auth\TwoFactorType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\{EmailOtpService, TwoFactorService, WebAuthnService};
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, RateLimiter};
use Illuminate\View\View;

/**
 * Zweiter Login-Schritt fürs Customer-Portal (guard 'customer'). Identität liegt
 * in der Session (auth.customer.2fa.id) und wird erst nach gültigem Code eingeloggt.
 */
class TwoFactorChallengeController extends Controller {
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly EmailOtpService $emailOtp,
        private readonly WebAuthnService $webauthn,
    ) {}

    public function create(Request $request): View|RedirectResponse {
        $user = $this->parkedUser($request);
        if (! $user instanceof User) {
            return redirect()->route('customer.login');
        }

        return view('customer.two-factor-challenge', [
            'hasTotp' => $user->two_factor_confirmed_at !== null && filled($user->two_factor_secret),
            'hasEmail' => $this->hasEmailFactor($user),
            'hasWebauthn' => $user->twoFactorCredentials()->where('type', \App\Enums\Auth\TwoFactorType::Webauthn->value)->whereNotNull('confirmed_at')->exists(),
        ]);
    }

    /** Assertion-Optionen für den geparkten Kunden (Passkey-Login). */
    public function webauthnOptions(Request $request): JsonResponse {
        $user = $this->parkedUser($request);
        if (! $user instanceof User) {
            return response()->json(['message' => __('Sitzung abgelaufen.')], 401);
        }
        $json = $this->webauthn->optionsToJson($this->webauthn->requestOptions($user, $request->getSchemeAndHttpHost()));
        $request->session()->put('webauthn.assert', $json);

        return response()->json(json_decode($json, true));
    }

    /** Passkey-Assertion prüfen → Kunde einloggen. */
    public function webauthnVerify(Request $request): JsonResponse {
        $user = $this->parkedUser($request);
        $optionsJson = $request->session()->pull('webauthn.assert');
        if (! $user instanceof User || ! is_string($optionsJson)) {
            return response()->json(['message' => __('Sitzung abgelaufen.')], 422);
        }
        try {
            $options = $this->webauthn->requestOptionsFromJson($optionsJson);
            $ok = $this->webauthn->verifyAssertion($user, $request->getContent(), $options, $request->getSchemeAndHttpHost());
        } catch (\Throwable) {
            $ok = false;
        }
        if (! $ok) {
            return response()->json(['message' => __('Sicherheitsschlüssel ungültig.')], 422);
        }

        $remember = (bool) $request->session()->pull('auth.customer.2fa.remember', false);
        $request->session()->forget('auth.customer.2fa.id');
        Auth::guard('customer')->login($user, $remember);
        $request->session()->regenerate();

        return response()->json(['redirect' => route('customer.dashboard')]);
    }

    public function email(Request $request): RedirectResponse {
        $user = $this->parkedUser($request);
        if (! $user instanceof User) {
            return redirect()->route('customer.login');
        }
        if (! $this->hasEmailFactor($user) || ! $this->emailOtp->canSend($user)) {
            return back()->withErrors(['email_code' => __('Code konnte nicht gesendet werden.')]);
        }
        if (! $this->emailOtp->send($user)) {
            return back()->withErrors(['email_code' => __('Code konnte nicht gesendet werden.')]);
        }

        return back()->with('success', __('Code an Ihre E-Mail gesendet.'));
    }

    private function parkedUser(Request $request): ?User {
        $userId = $request->session()->get('auth.customer.2fa.id');
        $user = $userId !== null ? User::query()->whereKey($userId)->first() : null;

        return ($user instanceof User && $user->isCustomer()) ? $user : null;
    }

    private function hasEmailFactor(User $user): bool {
        return $user->twoFactorCredentials()
            ->where('type', TwoFactorType::Email->value)->whereNotNull('confirmed_at')->exists();
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
        $emailCode = trim((string) $request->input('email_code', ''));
        $passed = ($code !== '' && filled($user->two_factor_secret) && $this->twoFactor->verifyForUser($user, (string) $user->two_factor_secret, $code))
            || ($emailCode !== '' && $this->emailOtp->verify($user, $emailCode))
            || ($recovery !== '' && $this->twoFactor->consumeRecoveryCode($user, $recovery));

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
}
