<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PasskeyLoginController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\ResolvesWorkMode;
use App\Http\Controllers\Controller;
use App\Models\{SsoConnection, User};
use App\Services\Auth\WebAuthnService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Auth;

/**
 * Passwortloser Passkey-Primär-Login (MS365-Plan G3) auf dem vorhandenen
 * WebAuthn-Stack: Discoverable Credentials (der Authenticator wählt den
 * Passkey), User Verification PFLICHT — der Passkey ersetzt Passwort UND
 * zweiten Faktor, daher keine 2FA-Parkschleife.
 *
 * Leitplanken:
 * - Portal-Konten (customer_id) und deaktivierte Konten sind ausgeschlossen
 *   ({@see User::canLogin()}), wie beim Passwort-Login.
 * - SSO-Zwang (enforced) sperrt auch den Passkey-Weg — Ausnahme sso_exempt
 *   (Break-Glass-Semantik wie im LoginController).
 * - Optionen sind einmalig (Session-pull) und tragen die Challenge; die
 *   Signaturprüfung läuft über den geteilten {@see WebAuthnService}.
 */
class PasskeyLoginController extends Controller {
    use ResolvesWorkMode;

    public function __construct(private readonly WebAuthnService $webauthn) {}

    /** Assertion-Optionen ohne Vorab-Identität (Discoverable Credentials). */
    public function options(Request $request): JsonResponse {
        abort_if(Auth::check(), 400);

        $options = $this->webauthn->discoverableRequestOptions($request->getSchemeAndHttpHost());
        $json = $this->webauthn->optionsToJson($options);
        $request->session()->put('auth.passkey.assert', $json);

        return response()->json(json_decode($json, true));
    }

    /** Passkey-Assertion prüfen → voll einloggen (kein 2FA-Parken nötig). */
    public function verify(Request $request): JsonResponse {
        abort_if(Auth::check(), 400);

        $optionsJson = $request->session()->pull('auth.passkey.assert');
        if (! is_string($optionsJson) || $optionsJson === '') {
            return response()->json(['message' => __('Sitzung abgelaufen.')], 422);
        }

        $browserJson = (string) $request->getContent();
        $credential = $this->webauthn->credentialOwner($browserJson);
        $user = $credential?->user()->withoutGlobalScopes()->first();

        if (! $user instanceof User || $user->customer_id !== null || ! $user->canLogin()) {
            $this->logFailure($request, 'unknown_or_blocked');

            return response()->json(['message' => __('auth.failed')], 422);
        }

        // SSO-Zwang gilt auch für Passkeys (Break-Glass: sso_exempt).
        if (! $user->sso_exempt && SsoConnection::enforcementActiveFor($user->organization_id)) {
            $this->logFailure($request, 'sso_enforced', $user);

            return response()->json(['message' => __('sso.error.no_account')], 403);
        }

        try {
            $options = $this->webauthn->requestOptionsFromJson($optionsJson);
            $ok = $this->webauthn->verifyAssertion($user, $browserJson, $options, $request->getSchemeAndHttpHost());
        } catch (\Throwable) {
            $ok = false;
        }
        if (! $ok) {
            $this->logFailure($request, 'assertion_invalid', $user);

            return response()->json(['message' => __('Sicherheitsschlüssel ungültig.')], 422);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['redirect' => $this->applyWorkModeAndRedirect($request, $user)->getTargetUrl()]);
    }

    private function logFailure(Request $request, string $reason, ?User $user = null): void {
        app(\App\Services\Security\SecurityEventLogger::class)->log(
            \App\Enums\Security\SecurityEventType::TwoFactorFailed,
            array_filter(['method' => 'passkey_login', 'reason' => $reason, 'user' => $user?->email, 'ip' => $request->ip()]),
        );
    }
}
