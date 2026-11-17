<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoginPolicyEvaluator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Auth\LoginPolicy;

use App\Enums\Auth\{LoginCheckpoint, LoginPolicyReason, TwoFactorType};
use App\Enums\User\{Permission, UserRole};
use App\Models\Auth\OrganizationLoginPolicy;
use App\Models\Platform\{Organization, User};
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\PermissionRegistrar;

/**
 * Wertet die Anmelderichtlinie einer Organisation aus (Feature 205,
 * MVP-1654–1657) — ohne Nebenwirkungen; Ereignisse, Freigabeanfragen und
 * Abmeldung übernimmt {@see LoginPolicyGate}.
 *
 * Maßgeblich ist immer die Organisation des Kontos (`users.organization_id`).
 * Mitglieder gehören genau einer Organisation an; den aktiven Kontext wechseln
 * kann nur der Plattformbetrieb, und der ist von der Richtlinie ausgenommen.
 * Rollen und Rechte werden deshalb im Team-Kontext dieser Organisation
 * gelesen, auch bei der Anmeldung, wenn noch kein Kontext gebunden ist.
 *
 * Reihenfolge: Notaus (gilt auch für Org-Admins, die nur eine neue Anmeldung
 * brauchen) → Ausnahme Org-Admin → Zeitfenster → Netz/Gerät.
 */
final class LoginPolicyEvaluator {
    /** @var array<int, array{admin: bool, lockdown: bool, remote: bool, remote_token: bool, roles: list<string>}> */
    private array $subjects = [];

    public function __construct(private readonly LoginNetworks $networks, private readonly DeviceApprovalService $devices) {}

    /**
     * Nie betroffen: Portal-Konten (eigene Regeln), der Plattformbetrieb und
     * Konten ohne Organisation (Installationslauf, Legacy-Schattenkonten).
     */
    public function isExempt(User $user): bool {
        return $user->isCustomer() || $user->isGlobalAdmin() || $user->organization_id === null;
    }

    public function policyFor(User $user): ?OrganizationLoginPolicy {
        return $this->isExempt($user) ? null : OrganizationLoginPolicy::forOrganization((int) $user->organization_id);
    }

    /** Muss sich die Person bei aktiver Netzregel über ein Gerät ausweisen können? */
    public function usesDeviceCheck(User $user): bool {
        return (bool) $this->policyFor($user)?->network_mode->isActive();
    }

    public function evaluate(User $user, Request $request, LoginCheckpoint $checkpoint): LoginPolicyDecision {
        $policy = $this->policyFor($user);
        if ($policy === null || (! $policy->isLockedDown() && ! $policy->hours_mode->isActive() && ! $policy->network_mode->isActive())) {
            return LoginPolicyDecision::allow();
        }

        $subject = $this->subject($user);

        if ($policy->isLockedDown()) {
            // Neue Anmeldungen nur für Org-Admins und wer den Notaus aufheben darf.
            if (! $subject['admin'] && ! $subject['lockdown']) {
                return LoginPolicyDecision::deny(LoginPolicyReason::Lockdown);
            }
            // Auch deren Sitzungen und Tokens von vor dem Notaus enden — bis auf
            // die Sitzung, in der er ausgelöst wurde (dort neu gestempelt).
            if ($this->predatesLockdown($user, $request, $checkpoint, $policy)) {
                return LoginPolicyDecision::deny(LoginPolicyReason::Lockdown);
            }
        }

        // Org-Admins sind von Zeitfenster und Netz ausgenommen: kein Selbstaussperren.
        if ($subject['admin']) {
            return LoginPolicyDecision::allow();
        }

        $logged = [];

        if ($policy->hours_mode->isActive() && $this->hoursApply($policy, $subject['roles'])) {
            $local = CarbonImmutable::now($this->timezone($user));
            $hours = $policy->hoursByWeekday();
            if (! LoginHours::isOpen($hours, $local)) {
                if ($policy->hours_mode->enforces()) {
                    return LoginPolicyDecision::deny(LoginPolicyReason::Hours, $logged, LoginHours::nextOpening($hours, $local));
                }
                $logged[] = LoginPolicyReason::Hours;
            }
        }

        if ($policy->network_mode->isActive()) {
            $reason = $this->networkReason($user, $request, $checkpoint, $policy, $subject);
            if ($reason !== null) {
                if ($policy->network_mode->enforces()) {
                    return LoginPolicyDecision::deny($reason, $logged);
                }
                $logged[] = $reason;
            }
        }

        return LoginPolicyDecision::allow($logged);
    }

    /** Gilt für die Person eine Passkey-Pflicht (Rolle in der Liste der Richtlinie)? */
    public function requiresPasskey(User $user): bool {
        $policy = $this->policyFor($user);
        $roles = $policy?->passkeyRoleNames() ?? [];
        if ($roles === []) {
            return false;
        }

        return array_intersect($this->subject($user)['roles'], $roles) !== [];
    }

    /** Hat die Person einen bestätigten Passkey bzw. Sicherheitsschlüssel? */
    public function hasPasskey(User $user): bool {
        return $user->twoFactorCredentials()
            ->where('type', TwoFactorType::Webauthn->value)
            ->whereNotNull('confirmed_at')
            ->exists();
    }

    /**
     * Rollen und Rechte im Team-Kontext der eigenen Organisation. Der
     * vorherige Kontext wird wiederhergestellt; die geladenen Relationen
     * werden verworfen, damit sie danach im richtigen Kontext neu laden.
     *
     * @return array{admin: bool, lockdown: bool, remote: bool, remote_token: bool, roles: list<string>}
     */
    public function subject(User $user): array {
        $key = (int) $user->id;
        if (isset($this->subjects[$key])) {
            return $this->subjects[$key];
        }

        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $organizationId = (int) $user->organization_id;
        $switch = (int) $previous !== $organizationId;
        if ($switch) {
            $registrar->setPermissionsTeamId($organizationId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }

        try {
            /** @var list<string> $roles */
            $roles = array_values(array_map('strval', $user->getRoleNames()->all()));

            return $this->subjects[$key] = [
                'admin' => in_array(UserRole::Admin->value, $roles, true),
                'lockdown' => $user->hasEffectivePermission(Permission::AuthLockdown->value),
                'remote' => $user->hasEffectivePermission(Permission::AuthRemote->value),
                'remote_token' => $user->hasEffectivePermission(Permission::AuthRemoteToken->value),
                'roles' => $roles,
            ];
        } finally {
            if ($switch) {
                $registrar->setPermissionsTeamId($previous);
                $user->unsetRelation('roles')->unsetRelation('permissions');
            }
        }
    }

    /** @param list<string> $roles */
    private function hoursApply(OrganizationLoginPolicy $policy, array $roles): bool {
        $applies = $policy->hourRoleNames();

        return $applies === [] || array_intersect($roles, $applies) !== [];
    }

    /** @param array{admin: bool, lockdown: bool, remote: bool, remote_token: bool, roles: list<string>} $subject */
    private function networkReason(User $user, Request $request, LoginCheckpoint $checkpoint, OrganizationLoginPolicy $policy, array $subject): ?LoginPolicyReason {
        if ($this->networks->contains((int) $user->organization_id, $policy->networkEntries(), $request->ip())) {
            return null;
        }
        // Persönliche Tokens (API, MCP) tragen kein Gerätecookie: außerhalb der
        // Büronetze nur mit eigenem Recht `auth.remote.token` (Sicherheitsprüfung, M2).
        if ($checkpoint === LoginCheckpoint::Token) {
            return $subject['remote_token'] ? null : LoginPolicyReason::Network;
        }
        if (! $subject['remote']) {
            return LoginPolicyReason::Network;
        }

        $hash = LoginDeviceCookie::hashFrom($request);
        $device = $hash !== null ? $this->devices->find($user, $hash) : null;
        if ($device?->approved_at !== null) {
            return null;
        }

        return $device?->rejected_at !== null ? LoginPolicyReason::DeviceRejected : LoginPolicyReason::Device;
    }

    private function predatesLockdown(User $user, Request $request, LoginCheckpoint $checkpoint, OrganizationLoginPolicy $policy): bool {
        $since = $policy->lockdown_at?->getTimestamp();
        if ($since === null) {
            return false;
        }

        if ($checkpoint === LoginCheckpoint::Session) {
            $stamp = $request->hasSession() ? $request->session()->get(LoginPolicyGate::SESSION_AUTHENTICATED_AT) : null;

            return ! is_numeric($stamp) || (int) $stamp < $since;
        }

        if ($checkpoint === LoginCheckpoint::Token) {
            // Der Notaus löscht die Tokens der Mitglieder; ein älteres Token, das
            // es dennoch gibt, gilt nicht (Verteidigung in der Tiefe).
            $token = PersonalAccessToken::findToken((string) $request->bearerToken());

            return $token !== null && ($token->created_at === null || $token->created_at->getTimestamp() < $since);
        }

        return false;
    }

    private function timezone(User $user): string {
        $organization = $user->organization;

        return $organization instanceof Organization ? Tz::ofOrganization($organization) : Tz::FALLBACK;
    }
}
