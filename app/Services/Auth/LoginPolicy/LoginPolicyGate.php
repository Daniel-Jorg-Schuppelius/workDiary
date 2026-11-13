<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoginPolicyGate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Auth\LoginPolicy;

use App\Enums\Auth\{LoginCheckpoint, LoginPolicyReason};
use App\Enums\Notification\NotificationEvent;
use App\Enums\Security\SecurityEventType;
use App\Models\Platform\User;
use App\Notifications\GenericEventNotification;
use App\Services\Security\SecurityEventLogger;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, Log, Notification};

/**
 * Prüfpunkte der Anmelderichtlinie (Feature 205) mit ihren Folgen:
 * Sicherheitsereignis bei Abweisung, Protokoll im Modus „nur
 * protokollieren“, Freigabeanfrage für ein unbekanntes Gerät.
 *
 * - {@see checkLogin()} rufen die Anmeldewege vor `Auth::login()` auf
 *   (Passwort, zweiter Faktor, Passkey, SSO).
 * - {@see checkRequest()} ruft {@see \App\Http\Middleware\EnforceLoginPolicy}
 *   je Anfrage einer Sitzung bzw. eines persönlichen Tokens auf.
 */
final class LoginPolicyGate {
    /** Zeitpunkt der Anmeldung in der Sitzung (Unix-Zeit) — Vergleich mit dem Notaus. */
    public const string SESSION_AUTHENTICATED_AT = 'login_policy.authenticated_at';

    private const string SESSION_LOGGED = 'login_policy.logged';

    /** Protokoll je Token und Grund höchstens stündlich. */
    private const int TOKEN_LOG_SECONDS = 3600;

    public function __construct(
        private readonly LoginPolicyEvaluator $evaluator,
        private readonly DeviceApprovalService $devices,
        private readonly SecurityEventLogger $security,
    ) {}

    /**
     * Anmeldeweg der Sitzung, soweit er für die Richtlinie zählt: `sso` — dort
     * bestimmt der Identitätsanbieter den Faktor, eine Passkey-Pflicht greift
     * nicht (Sicherheitsprüfung Feature 205, N11).
     */
    public const string SESSION_METHOD = 'login_policy.method';

    /** Beim Abschluss einer Anmeldung: Sitzung mit dem Anmeldezeitpunkt stempeln, Anmeldeweg zurücksetzen. */
    public static function stampSession(Request $request): void {
        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_AUTHENTICATED_AT, time());
            $request->session()->forget(self::SESSION_METHOD);
        }
    }

    /** Nach `Auth::login()` über SSO: Sitzung als SSO-Anmeldung kennzeichnen. */
    public static function markSso(Request $request): void {
        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_METHOD, 'sso');
        }
    }

    public static function viaSso(Request $request): bool {
        return $request->hasSession() && $request->session()->get(self::SESSION_METHOD) === 'sso';
    }

    /**
     * Prüfung bei der Anmeldung.
     *
     * `$final` heißt: die Identität ist vollständig nachgewiesen (zweiter
     * Faktor, Passkey, SSO oder Konto ohne zweiten Faktor). Erst dann wird für
     * ein unbekanntes Gerät eine Freigabeanfrage gestellt — wer nur das
     * Passwort kennt, soll den Org-Admins keine Anfragen schicken können. Vorher
     * entscheidet die Gerätefrage noch nicht.
     */
    public function checkLogin(User $user, Request $request, string $method, bool $final = true): LoginPolicyDecision {
        if ($final && $this->evaluator->usesDeviceCheck($user)) {
            LoginDeviceCookie::ensure($request);
        }

        $decision = $this->evaluator->evaluate($user, $request, LoginCheckpoint::Login);

        if (! $final) {
            // Die Gerätefrage (auch „abgelehnt“) erst nach dem zweiten Faktor beantworten.
            if (in_array($decision->denied, [LoginPolicyReason::Device, LoginPolicyReason::DeviceRejected], true)) {
                return LoginPolicyDecision::allow($decision->logged);
            }
            if (! $decision->allowed()) {
                $this->recordDenied($user, $decision, LoginCheckpoint::Login, $method);
            }

            return $decision;
        }

        if ($decision->concerns(LoginPolicyReason::Device)) {
            $this->requestDevice($user, $request);
        }

        if (! $decision->allowed()) {
            $this->recordDenied($user, $decision, LoginCheckpoint::Login, $method);
        }
        // Die Sitzung merkt sich die protokollierten Gründe, damit ihre
        // Folgeanfragen sie nicht erneut melden.
        foreach ($decision->logged as $reason) {
            $this->firstLogFor($user, $request, LoginCheckpoint::Session, $reason, 'logged');
            $this->recordLogged($user, $reason, LoginCheckpoint::Login, $method);
        }

        return $decision;
    }

    /** Prüfung je Anfrage einer laufenden Sitzung bzw. eines persönlichen Tokens. */
    public function checkRequest(User $user, Request $request, LoginCheckpoint $checkpoint): LoginPolicyDecision {
        $decision = $this->evaluator->evaluate($user, $request, $checkpoint);

        // Eine abgewiesene Sitzung endet sofort, ein Token kann es weiter
        // versuchen: dessen Ereignisse höchstens stündlich je Grund.
        if (! $decision->allowed() && ($checkpoint === LoginCheckpoint::Session || $this->firstLogFor($user, $request, $checkpoint, $decision->denied, 'denied'))) {
            if ($decision->denied === LoginPolicyReason::Device && $checkpoint === LoginCheckpoint::Session) {
                $this->requestDevice($user, $request);
            }
            $this->recordDenied($user, $decision, $checkpoint, null);
        }

        foreach ($decision->logged as $reason) {
            if (! $this->firstLogFor($user, $request, $checkpoint, $reason, 'logged')) {
                continue;
            }
            if ($reason === LoginPolicyReason::Device && $checkpoint === LoginCheckpoint::Session) {
                $this->requestDevice($user, $request);
            }
            $this->recordLogged($user, $reason, $checkpoint, null);
        }

        return $decision;
    }

    /**
     * Zweiter Faktor per App- oder E-Mail-Code trotz Passkey-Pflicht abgewiesen
     * (Sicherheitsprüfung H1); `$method` nennt die übermittelten Codes.
     */
    public function recordPasskeyRefused(User $user, string $method): void {
        $this->security->log(SecurityEventType::LoginPolicyDenied, $this->context($user, LoginPolicyReason::Passkey, LoginCheckpoint::Login, $method));
    }

    /**
     * Anmeldung per Wiederherstellungscode trotz Passkey-Pflicht (Notweg):
     * Sicherheitsereignis und Meldung an die Person und alle Org-Admins
     * (Sicherheitsprüfung Feature 205, N4).
     */
    public function recordPasskeyRecovery(User $user, Request $request): void {
        $this->security->log(SecurityEventType::PasskeyRecoveryUsed, [
            'user' => $user->email,
            'user_id' => (int) $user->id,
            'organization_id' => $user->organization_id,
            'ua' => mb_substr((string) $request->userAgent(), 0, 120),
        ]);

        $recipients = OrganizationLockdownService::organizationAdmins((int) $user->organization_id)
            ->push($user)
            ->unique(static fn (User $recipient): int => (int) $recipient->id)
            ->values();
        $params = ['name' => $user->name];

        try {
            Notification::send($recipients, new GenericEventNotification(
                NotificationEvent::SecurityPasskeyRecovery,
                [
                    'title' => (string) __('login_policy.notification.passkey_recovery_title', $params),
                    'title_key' => 'login_policy.notification.passkey_recovery_title',
                    'title_params' => $params,
                    'message' => (string) __('login_policy.notification.passkey_recovery_message', $params),
                    'message_key' => 'login_policy.notification.passkey_recovery_message',
                    'message_params' => $params,
                    'url' => route('account.2fa.show'),
                ],
                ['database', 'mail'],
            ));
        } catch (\Throwable $e) {
            // Die Anmeldung ist geschehen; eine gescheiterte Mail darf sie nicht brechen.
            Log::warning('login_policy.passkey_recovery_notify_failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Unbekanntes Gerät außerhalb der Büronetze: Cookie ausstellen und
     * Freigabe anfragen — auch im Modus „nur protokollieren“, damit Org-Admins
     * die Geräte vor dem Scharfschalten freigeben können.
     */
    private function requestDevice(User $user, Request $request): void {
        $this->devices->request($user, LoginDeviceCookie::ensure($request), $request->userAgent());
    }

    private function recordDenied(User $user, LoginPolicyDecision $decision, LoginCheckpoint $checkpoint, ?string $method): void {
        $this->security->log(SecurityEventType::LoginPolicyDenied, $this->context($user, $decision->denied, $checkpoint, $method));
    }

    private function recordLogged(User $user, LoginPolicyReason $reason, LoginCheckpoint $checkpoint, ?string $method): void {
        $this->security->log(SecurityEventType::LoginPolicyLogged, $this->context($user, $reason, $checkpoint, $method));
    }

    /**
     * Ereignisse nicht für jede Anfrage einzeln: je Sitzung einmal je Grund,
     * je Token höchstens stündlich.
     */
    private function firstLogFor(User $user, Request $request, LoginCheckpoint $checkpoint, ?LoginPolicyReason $reason, string $kind): bool {
        $suffix = $kind . '.' . ($reason->value ?? '-');
        if ($checkpoint === LoginCheckpoint::Session && $request->hasSession()) {
            $key = self::SESSION_LOGGED . '.' . $suffix;
            if ($request->session()->has($key)) {
                return false;
            }
            $request->session()->put($key, time());

            return true;
        }

        $tokenKey = substr((string) CryptoHelper::hash((string) $request->bearerToken()), 0, 16);

        return Cache::add('login-policy-log:1:' . $user->id . ':' . $tokenKey . ':' . $suffix, 1, self::TOKEN_LOG_SECONDS);
    }

    /** @return array<string, scalar|null> */
    private function context(User $user, ?LoginPolicyReason $reason, LoginCheckpoint $checkpoint, ?string $method): array {
        return [
            'user' => $user->email,
            'user_id' => (int) $user->id,
            'organization_id' => $user->organization_id,
            'reason' => $reason?->value,
            'checkpoint' => $checkpoint->value,
            'method' => $method,
        ];
    }
}
