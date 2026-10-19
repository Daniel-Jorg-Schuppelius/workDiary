<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalPreview.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\CustomerPortal;

use App\Models\Customer\Customer;
use App\Models\Platform\User;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vorschau als Kunde (Phase 153, MVP-1229): Mitarbeitende sehen das Portal in
 * der Sicht eines Portalkontos. Der Zustand liegt in der Mitarbeiter-Sitzung;
 * das Konto wird je Request per setUser() auf den customer-Guard gesetzt —
 * keine Anmeldung, kein Login-Ereignis, keine Kundensitzung
 * ({@see \App\Http\Middleware\ApplyPortalPreview}). Nur lesend, Sperrliste und
 * Recht je Request erzwingt {@see \App\Http\Middleware\EnforcePortalPreview}.
 */
final class PortalPreview {
    public const SESSION_KEY = 'portal_preview';

    public const REQUEST_ATTRIBUTE = 'portal_preview';

    /** Laufzeit einer Vorschau in Minuten; Verlängern heißt neu starten. */
    public const TTL_MINUTES = 60;

    public const REASON_MANUAL = 'manual';

    public const REASON_REPLACED = 'replaced';

    public const REASON_EXPIRED = 'expired';

    public const REASON_ACTOR_CHANGED = 'actor_changed';

    public const REASON_PERMISSION_LOST = 'permission_lost';

    public const REASON_ORGANIZATION_CHANGED = 'organization_changed';

    public const REASON_ACCOUNT_CHANGED = 'account_changed';

    public const REASON_ACCOUNT_DEACTIVATED = 'account_deactivated';

    /** @return array<string, mixed>|null */
    public function state(Request $request): ?array {
        if (! $request->hasSession()) {
            return null;
        }
        $state = $request->session()->get(self::SESSION_KEY);

        return is_array($state) ? $state : null;
    }

    /** Wahr, wenn dieser Request in der Vorschau läuft (Attribut setzt ApplyPortalPreview). */
    public function isActive(Request $request): bool {
        return is_array($request->attributes->get(self::REQUEST_ATTRIBUTE));
    }

    /** @return array<string, mixed>|null */
    public function activeState(Request $request): ?array {
        $state = $request->attributes->get(self::REQUEST_ATTRIBUTE);

        return is_array($state) ? $state : null;
    }

    /** Läuft für diesen Kunden eine Vorschau in der aktuellen Sitzung? */
    public function runsFor(Request $request, Customer $customer): bool {
        $state = $this->state($request);

        return $state !== null && (int) ($state['customer_id'] ?? 0) === (int) $customer->getKey() && ! $this->isExpired($state);
    }

    /** Beendet eine laufende Vorschau mit `replaced` und startet die neue; Audit vor dem Kontextwechsel. */
    public function start(Request $request, Customer $customer, User $portalUser, User $actor): void {
        if ($this->state($request) !== null) {
            $this->end($request, self::REASON_REPLACED);
        }

        $now = Carbon::now();
        $expiresAt = $now->copy()->addMinutes(self::TTL_MINUTES);

        $customer->audit('customer.portal_preview.started', [
            'portal_user_id' => (int) $portalUser->getKey(),
            'expires_at' => $expiresAt->toIso8601String(),
        ]);

        $request->session()->put(self::SESSION_KEY, [
            'actor_id' => (int) $actor->getKey(),
            'organization_id' => (int) $customer->organization_id,
            'customer_id' => (int) $customer->getKey(),
            'portal_user_id' => (int) $portalUser->getKey(),
            'started_at' => $now->getTimestamp(),
            'expires_at' => $expiresAt->getTimestamp(),
            'account_fingerprint' => $this->fingerprint($portalUser),
        ]);
    }

    /**
     * Beendet die Vorschau: Zustand und der von AuthenticateSession abgelegte
     * `password_hash_customer` entfallen, der customer-Guard vergisst das Konto
     * ohne Logout-Ereignis, Audit am Kunden mit Grund und Dauer.
     */
    public function end(Request $request, string $reason): void {
        $state = $this->state($request);
        if ($state === null) {
            return;
        }

        $request->session()->forget([self::SESSION_KEY, 'password_hash_customer']);
        $request->attributes->remove(self::REQUEST_ATTRIBUTE);
        Auth::guard('customer')->forgetUser();
        // Akteur des Audits ist die Mitarbeiterin — nach auth:customer stünde der Standard-Guard auf dem Portalkonto.
        Auth::shouldUse('web');

        $this->customerFor($state)?->audit('customer.portal_preview.ended', [
            'portal_user_id' => (int) ($state['portal_user_id'] ?? 0),
            'reason' => $reason,
            'duration_seconds' => max(0, Carbon::now()->getTimestamp() - (int) ($state['started_at'] ?? Carbon::now()->getTimestamp())),
        ]);
    }

    /** Vorschau beenden und den Aufrufer dorthin schicken, wo er weiterarbeitet: Kundenakte, sonst Portal-Anmeldung. */
    public function endAndRedirect(Request $request, string $reason): Response {
        $state = $this->state($request);
        $customer = $state !== null ? $this->customerFor($state) : null;
        $this->end($request, $reason);

        $message = (string) __('customer_portal.preview.ended_' . $reason, ['minutes' => self::TTL_MINUTES]);
        $tone = $reason === self::REASON_MANUAL ? 'success' : 'warning';

        if ($request->expectsJson()) {
            return new JsonResponse(['message' => $message, 'reason' => 'portal_preview_ended'], Response::HTTP_FORBIDDEN);
        }

        if (! Auth::guard('web')->check()) {
            return redirect()->route('customer.login')->with($tone, $message);
        }

        if (! $customer instanceof Customer) {
            return redirect()->toList('customers.index')->with($tone, $message);
        }

        return redirect()->to(route('customers.show', ['customer' => $customer, '#' => 'portal-access']))->with($tone, $message);
    }

    /** @param array<string, mixed> $state */
    public function isExpired(array $state): bool {
        return (int) ($state['expires_at'] ?? 0) <= Carbon::now()->getTimestamp();
    }

    /** @param array<string, mixed> $state */
    public function expiresAt(array $state): Carbon {
        return Carbon::createFromTimestamp((int) ($state['expires_at'] ?? 0));
    }

    /** @param array<string, mixed> $state */
    public function actorMatches(array $state, ?User $actor): bool {
        return $actor instanceof User
            && (int) $actor->getKey() === (int) ($state['actor_id'] ?? 0)
            && ! $actor->isDeactivated();
    }

    /**
     * Portalkonto des Zustands, ungescopt geladen (vor dem Org-Kontext): gehört
     * zu Kunde und Organisation des Zustands und ist nicht deaktiviert.
     *
     * @param array<string, mixed> $state
     */
    public function resolvePortalUser(array $state): ?User {
        $user = User::query()->withoutGlobalScopes()->whereKey((int) ($state['portal_user_id'] ?? 0))->first();

        if (! $user instanceof User
            || ! $user->isCustomer()
            || (int) $user->customer_id !== (int) ($state['customer_id'] ?? 0)
            || (int) $user->organization_id !== (int) ($state['organization_id'] ?? 0)
            || $user->isDeactivated()) {
            return null;
        }

        return $user;
    }

    /** @param array<string, mixed> $state */
    public function accountUnchanged(array $state, User $portalUser): bool {
        return hash_equals((string) ($state['account_fingerprint'] ?? ''), $this->fingerprint($portalUser));
    }

    /** @param array<string, mixed> $state */
    public function customerFor(array $state): ?Customer {
        return Customer::query()->withoutGlobalScopes()->whereKey((int) ($state['customer_id'] ?? 0))->first();
    }

    /**
     * Fingerabdruck des Kontostands (Muster PortalAccessService::passwordFingerprint):
     * Passwort- oder Adresswechsel des Kontos beendet die Vorschau, bevor
     * AuthenticateSession das Konto abmelden und zur Anmeldung werfen würde.
     */
    public function fingerprint(User $portalUser): string {
        return (string) CryptoHelper::hash($portalUser->getAuthPassword() . '|' . mb_strtolower((string) $portalUser->email));
    }
}
