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

use App\Enums\Auth\TwoFactorType;
use App\Http\Controllers\Auth\Concerns\ResolvesWorkMode;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\{EmailOtpService, TwoFactorService, WebAuthnService};
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, RateLimiter};
use Illuminate\View\View;

/**
 * Zweiter Login-Schritt: TOTP- oder Recovery-Code. Die zu authentifizierende
 * Identität liegt in der Session (auth.2fa.id) und wird erst hier eingeloggt.
 */
class TwoFactorChallengeController extends Controller {
    use ResolvesWorkMode;

    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly EmailOtpService $emailOtp,
        private readonly WebAuthnService $webauthn,
    ) {}

    public function create(Request $request): View|RedirectResponse {
        $user = $this->parkedUser($request);
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge', [
            'hasTotp' => $user->two_factor_confirmed_at !== null && filled($user->two_factor_secret),
            'hasEmail' => $this->hasEmailFactor($user),
            'hasWebauthn' => $this->hasWebauthnFactor($user),
        ]);
    }

    /** Assertion-Optionen für den geparkten Nutzer (Passkey-Login). */
    public function webauthnOptions(Request $request): JsonResponse {
        $user = $this->parkedUser($request);
        if (! $user instanceof User) {
            return response()->json(['message' => __('Sitzung abgelaufen.')], 401);
        }
        $options = $this->webauthn->requestOptions($user, $request->getSchemeAndHttpHost());
        $json = $this->webauthn->optionsToJson($options);
        $request->session()->put('webauthn.assert', $json);

        return response()->json(json_decode($json, true));
    }

    /** Passkey-Assertion prüfen → einloggen (geparkter Flow). */
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
            app(\App\Services\Security\SecurityEventLogger::class)->log(
                \App\Enums\Security\SecurityEventType::TwoFactorFailed,
                ['user' => $user->email, 'method' => 'webauthn'],
            );

            return response()->json(['message' => __('Sicherheitsschlüssel ungültig.')], 422);
        }

        $remember = (bool) $request->session()->pull('auth.2fa.remember', false);
        $request->session()->forget('auth.2fa.id');
        Auth::login($user, $remember);
        $request->session()->regenerate();

        return response()->json(['redirect' => $this->applyWorkModeAndRedirect($request, $user)->getTargetUrl()]);
    }

    private function hasWebauthnFactor(User $user): bool {
        return $user->twoFactorCredentials()
            ->where('type', \App\Enums\Auth\TwoFactorType::Webauthn->value)->whereNotNull('confirmed_at')->exists();
    }

    /** Sendet einen E-Mail-Einmalcode an die geparkte Identität. */
    public function email(Request $request): RedirectResponse {
        $user = $this->parkedUser($request);
        if (! $user instanceof User) {
            return redirect()->route('login');
        }
        if (! $this->hasEmailFactor($user) || ! $this->emailOtp->canSend($user)) {
            return back()->withErrors(['email_code' => __('Code konnte nicht gesendet werden.')]);
        }
        if (! $this->emailOtp->send($user)) {
            return back()->withErrors(['email_code' => __('E-Mail-Versand fehlgeschlagen — Mailserver nicht erreichbar oder falsch konfiguriert. Bitte informieren Sie Ihre Administration.')]);
        }

        return back()->with('success', __('Code an Ihre E-Mail gesendet.'));
    }

    private function parkedUser(Request $request): ?User {
        $userId = $request->session()->get('auth.2fa.id');

        return $userId !== null ? User::query()->whereKey($userId)->first() : null;
    }

    private function hasEmailFactor(User $user): bool {
        return $user->twoFactorCredentials()
            ->where('type', TwoFactorType::Email->value)->whereNotNull('confirmed_at')->exists();
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
            'email_code' => ['nullable', 'string'],
        ]);

        $user = User::query()->find($userId);
        if (! $user instanceof User || ! $user->hasTwoFactorEnabled()) {
            $request->session()->forget(['auth.2fa.id', 'auth.2fa.remember']);

            return redirect()->route('login');
        }

        $code = trim((string) $request->input('code', ''));
        $recovery = trim((string) $request->input('recovery_code', ''));
        $emailCode = trim((string) $request->input('email_code', ''));
        $passed = false;

        if ($code !== '' && filled($user->two_factor_secret) && $this->twoFactor->verifyForUser($user, (string) $user->two_factor_secret, $code)) {
            $passed = true;
        } elseif ($emailCode !== '' && $this->emailOtp->verify($user, $emailCode)) {
            $passed = true;
        } elseif ($recovery !== '' && $this->twoFactor->consumeRecoveryCode($user, $recovery)) {
            $passed = true;
        }

        if (! $passed) {
            RateLimiter::hit($throttleKey, 60);
            app(\App\Services\Security\SecurityEventLogger::class)->log(
                \App\Enums\Security\SecurityEventType::TwoFactorFailed,
                ['user' => $user->email, 'method' => $recovery !== '' ? 'recovery' : ($emailCode !== '' ? 'email' : 'totp')],
            );

            return back()->withErrors(['code' => __('Der Code ist ungültig.')]);
        }

        RateLimiter::clear($throttleKey);
        $remember = (bool) $request->session()->pull('auth.2fa.remember', false);
        $request->session()->forget('auth.2fa.id');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return $this->applyWorkModeAndRedirect($request, $user);
    }
}
