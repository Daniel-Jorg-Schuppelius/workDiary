<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnforceLoginPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Auth\LoginCheckpoint;
use App\Http\Controllers\Admin\SupportImpersonationController;
use App\Models\Platform\User;
use App\Services\Auth\LoginPolicy\{LoginPolicyDecision, LoginPolicyEvaluator, LoginPolicyGate};
use Closure;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anmelderichtlinie je Anfrage (Feature 205, MVP-1654–1657): laufende
 * Sitzungen enden mit dem Zeitfenster, beim Verlassen der Büronetze ohne
 * freigegebenes Gerät und beim Notaus; persönliche API-Tokens bekommen 403.
 *
 * Läuft im Web- und im API-Stapel nach der Organisationsbindung. Nicht
 * betroffen: Gäste, Portal-Konten, der Plattformbetrieb (auch als
 * impersonierende Person einer Support-Sitzung), Konten ohne Organisation
 * sowie Ingest-Wege mit eigenem Gerätetoken (Stempelterminal, Standort, CTI,
 * SCIM) — die tragen keinen angemeldeten Nutzer.
 *
 * Verlangt die Organisation für die Rolle einen Passkey und hat die Person
 * noch keinen, führt jede Seite zur Einrichtung (wie die 2FA-Pflicht).
 */
class EnforceLoginPolicy {
    /** Erreichbar, solange die Passkey-Einrichtung aussteht. */
    private const array PASSKEY_SETUP_ROUTES = [
        'account.2fa.*',
        'two-factor.*',
        'password.confirm',
        'password.confirm.store',
        'logout',
        'locale.switch',
    ];

    public function __construct(
        private readonly LoginPolicyGate $gate,
        private readonly LoginPolicyEvaluator $evaluator,
    ) {}

    public function handle(Request $request, Closure $next): Response {
        // Im Web-Stapel zählt die Sitzung der Mitarbeiterin (Guard `web`), auch
        // auf customer.*-Routen, wo Auth::user() das Portalkonto ist (Vorschau
        // als Kunde, Sicherheitsprüfung N1). Im API-Stapel das Token.
        $user = $request->hasSession() ? Auth::guard('web')->user() : Auth::user();
        if (! $user instanceof User) {
            return $next($request);
        }

        // Support-Sitzung: nur der Plattformbetrieb ist ausgenommen; sonst gilt
        // die Richtlinie der Person, die impersoniert (Sicherheitsprüfung N2).
        $impersonating = false;
        $state = $request->hasSession() ? $request->session()->get(SupportImpersonationController::SESSION_KEY) : null;
        if (is_array($state)) {
            $impersonator = User::query()->whereKey((int) ($state['impersonator_id'] ?? 0))->first();
            if ($impersonator instanceof User && $impersonator->isGlobalAdmin()) {
                return $next($request);
            }
            $user = $impersonator instanceof User ? $impersonator : $user;
            $impersonating = true;
        }

        if ($this->evaluator->isExempt($user)) {
            return $next($request);
        }

        // Ohne Sitzung (API-Stapel) kommt die Anmeldung aus einem persönlichen Token.
        $checkpoint = $request->hasSession() ? LoginCheckpoint::Session : LoginCheckpoint::Token;

        $decision = $this->gate->checkRequest($user, $request, $checkpoint);
        if (! $decision->allowed()) {
            return $this->deny($request, $decision, $checkpoint);
        }

        // Passkey-Pflicht: nicht für SSO-Sitzungen (den Faktor bestimmt der
        // Identitätsanbieter, Hebel ist die SSO-Erzwingung — N11) und nicht
        // in einer Support-Sitzung.
        if ($checkpoint === LoginCheckpoint::Session
            && ! $impersonating
            && ! LoginPolicyGate::viaSso($request)
            && ! $request->routeIs(...self::PASSKEY_SETUP_ROUTES)
            && $this->evaluator->requiresPasskey($user)
            && ! $this->evaluator->hasPasskey($user)) {
            $message = (string) __('login_policy.passkey.setup_required');
            if ($request->expectsJson()) {
                return new JsonResponse(['message' => $message, 'reason' => 'passkey_setup_required'], Response::HTTP_FORBIDDEN);
            }

            return redirect()->route('account.2fa.show')->with('warning', $message);
        }

        return $next($request);
    }

    private function deny(Request $request, LoginPolicyDecision $decision, LoginCheckpoint $checkpoint): Response {
        $message = $decision->message();
        $reason = $decision->denied?->value;

        if ($checkpoint === LoginCheckpoint::Token) {
            return new JsonResponse(['message' => $message, 'reason' => $reason], Response::HTTP_FORBIDDEN);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return new JsonResponse(['message' => $message, 'reason' => $reason], Response::HTTP_UNAUTHORIZED);
        }

        return redirect()->route('login')->withErrors(['username' => $message]);
    }
}
