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

use App\Http\Controllers\Auth\Concerns\{CompletesLogin, ResolvesWorkMode};
use App\Http\Controllers\Auth\Concerns\SendsEmailOtpChallenge;
use App\Http\Controllers\Controller;
use App\Models\Platform\User;
use App\Services\Auth\{EmailOtpService, TwoFactorService, WebAuthnService};
use App\Services\Auth\LoginPolicy\{LoginPolicyEvaluator, LoginPolicyGate};
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, RateLimiter};
use Illuminate\View\View;

/**
 * Zweiter Login-Schritt: TOTP- oder Recovery-Code. Die zu authentifizierende
 * Identität liegt in der Session (auth.2fa.id) und wird erst hier eingeloggt.
 */
class TwoFactorChallengeController extends Controller {
    use CompletesLogin;

    use ResolvesWorkMode;

    use SendsEmailOtpChallenge;

    private const int MAX_ATTEMPTS = 5;

    /**
     * Zusätzlicher Zähler je Konto — unabhängig von der Adresse. Der
     * Nutzer+IP-Zähler allein ließ verteiltes Raten des sechsstelligen Codes zu,
     * sobald das Passwort bekannt war (Sicherheitsaudit 2026-09-17, 2fa-1).
     */
    private const int MAX_USER_ATTEMPTS = 10;

    private const int USER_DECAY_SECONDS = 900;

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

        // Anmelderichtlinie (Feature 205): erst nach vollständigem Nachweis
        // entscheidet sich auch die Gerätefreigabe.
        $policy = app(LoginPolicyGate::class)->checkLogin($user, $request, 'webauthn');
        if (! $policy->allowed()) {
            $request->session()->forget(['auth.2fa.id', 'auth.2fa.remember', 'auth.2fa.username']);

            return response()->json(['message' => $policy->message(), 'redirect' => route('login')], 403);
        }

        $remember = (bool) $request->session()->pull('auth.2fa.remember', false);
        $username = (string) $request->session()->pull('auth.2fa.username', '');
        $request->session()->forget('auth.2fa.id');
        Auth::login($user, $remember);
        $request->session()->regenerate();

        // Erst jetzt ist der Login abgeschlossen — vorher liefen diese
        // Schritte für Versuche, die nie ankamen (S-51).
        $this->auditBreakGlassIfApplicable($user);
        $this->syncLegacyUserIdIfMissing($user, $username);

        return response()->json(['redirect' => $this->applyWorkModeAndRedirect($request, $user)->getTargetUrl()]);
    }

    private function hasWebauthnFactor(User $user): bool {
        return $user->twoFactorCredentials()
            ->where('type', \App\Enums\Auth\TwoFactorType::Webauthn->value)->whereNotNull('confirmed_at')->exists();
    }

    private function loginRoute(): string {
        return 'login';
    }

    private function parkedUser(Request $request): ?User {
        $userId = $request->session()->get('auth.2fa.id');

        return $userId !== null ? User::query()->whereKey($userId)->first() : null;
    }

    public function store(Request $request): RedirectResponse {
        $userId = $request->session()->get('auth.2fa.id');
        if ($userId === null) {
            return redirect()->route('login');
        }

        $throttleKey = '2fa:' . $userId . '|' . $request->ip();
        $userKey = '2fa-user:' . $userId;
        if (RateLimiter::tooManyAttempts($userKey, self::MAX_USER_ATTEMPTS)) {
            // Geparkte Identität verwerfen: ab hier muss das Passwort erneut her.
            $request->session()->forget(['auth.2fa.id', 'auth.2fa.remember', 'auth.2fa.username']);
            $this->notifyTwoFactorLockout((int) $userId);

            return redirect()->route('login')->withErrors(['username' => __('Zu viele Fehlversuche. Bitte melden Sie sich erneut an.')]);
        }
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
        $attempted = implode('+', array_keys(array_filter(['totp' => $code, 'email' => $emailCode, 'recovery' => $recovery], static fn (string $value): bool => $value !== ''))) ?: 'none';

        // Passkey-Pflicht der Rolle (Feature 205, MVP-1656): wer einen Passkey
        // hat, meldet sich damit an. Ausgewertet wird dann nur noch ein
        // Wiederherstellungscode — der Notweg, wenn das Gerät fehlt; App- und
        // E-Mail-Code werden abgewiesen, auch neben einem Wiederherstellungscode
        // (Sicherheitsprüfung H1). Die Anmeldung per Notweg melden wir der
        // Person und den Org-Admins (N4); einen neuen Passkey richtet die
        // Person danach im Konto ein, den alten entfernt sie erst dann.
        $evaluator = app(LoginPolicyEvaluator::class);
        $passkeyOnly = $evaluator->requiresPasskey($user) && $evaluator->hasPasskey($user);
        if ($passkeyOnly && ($code !== '' || $emailCode !== '' || $recovery === '')) {
            app(LoginPolicyGate::class)->recordPasskeyRefused($user, $attempted);

            return back()->withErrors(['code' => __('login_policy.denied.passkey')]);
        }

        // Erst prüfen, dann die Richtlinie, dann verbrauchen: ein abgewiesener
        // Versuch (z. B. Gerät nicht freigegeben) kostet keinen
        // Wiederherstellungscode (Sicherheitsprüfung N12).
        $method = null;
        if ($code !== '' && filled($user->two_factor_secret) && $this->twoFactor->verifyForUser($user, (string) $user->two_factor_secret, $code)) {
            $method = 'totp';
        } elseif ($emailCode !== '' && $this->emailOtp->verify($user, $emailCode)) {
            $method = 'email';
        } elseif ($recovery !== '' && $this->twoFactor->matchesRecoveryCode($user, $recovery)) {
            $method = 'recovery';
        }

        if ($method === null) {
            RateLimiter::hit($throttleKey, 60);
            RateLimiter::hit($userKey, self::USER_DECAY_SECONDS);
            app(\App\Services\Security\SecurityEventLogger::class)->log(
                \App\Enums\Security\SecurityEventType::TwoFactorFailed,
                ['user' => $user->email, 'method' => $attempted],
            );

            return back()->withErrors(['code' => __('Der Code ist ungültig.')]);
        }

        // Anmelderichtlinie (Feature 205) nach vollständigem Nachweis — erst
        // hier entsteht für ein unbekanntes Gerät die Freigabeanfrage.
        $gate = app(LoginPolicyGate::class);
        $policy = $gate->checkLogin($user, $request, $method);
        if (! $policy->allowed()) {
            $request->session()->forget(['auth.2fa.id', 'auth.2fa.remember', 'auth.2fa.username']);

            return redirect()->route('login')->withErrors(['username' => $policy->message()]);
        }

        // Wiederherstellungscode erst jetzt verbrauchen; scheitert das (zweite
        // Anfrage mit demselben Code), gilt der Versuch als fehlgeschlagen.
        if ($method === 'recovery' && ! $this->twoFactor->consumeRecoveryCode($user, $recovery)) {
            RateLimiter::hit($throttleKey, 60);
            RateLimiter::hit($userKey, self::USER_DECAY_SECONDS);

            return back()->withErrors(['code' => __('Der Code ist ungültig.')]);
        }

        RateLimiter::clear($throttleKey);
        RateLimiter::clear($userKey);

        if ($passkeyOnly) {
            $gate->recordPasskeyRecovery($user, $request);
        }

        $remember = (bool) $request->session()->pull('auth.2fa.remember', false);
        $username = (string) $request->session()->pull('auth.2fa.username', '');
        $request->session()->forget('auth.2fa.id');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        $this->auditBreakGlassIfApplicable($user);
        $this->syncLegacyUserIdIfMissing($user, $username);

        return $this->applyWorkModeAndRedirect($request, $user);
    }

    /** Hinweis an die betroffene Person: ihr zweiter Faktor wird gerade geraten. */
    private function notifyTwoFactorLockout(int $userId): void {
        $user = User::query()->find($userId);
        if (! $user instanceof User) {
            return;
        }

        try {
            app(\App\Services\Security\SecurityEventLogger::class)->log(
                \App\Enums\Security\SecurityEventType::AuthLockout,
                ['user' => $user->email, 'method' => '2fa'],
            );
            $user->notify(new \App\Notifications\GenericEventNotification(
                \App\Enums\Notification\NotificationEvent::SecurityLockout,
                [
                    'title' => (string) __('notification.message.two_factor_lockout_title'),
                    'title_key' => 'notification.message.two_factor_lockout_title',
                    'message' => (string) __('notification.message.two_factor_lockout_message'),
                    'message_key' => 'notification.message.two_factor_lockout_message',
                    'url' => route('account.2fa.show'),
                ],
                ['database', 'mail'],
            ));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('auth.2fa_lockout_notify_failed', ['error' => $e->getMessage()]);
        }
    }
}
