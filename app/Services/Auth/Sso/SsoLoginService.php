<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SsoLoginService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Auth\Sso;

use App\Models\{SsoConnection, SsoIdentity, User};
use Illuminate\Support\Facades\Log;

/**
 * Gemeinsamer Abschluss beider SSO-Protokolle (Feature 057): löst die
 * IdP-Identität (Verbindung, Subject) zu einem WorkDiary-Konto auf und
 * erzwingt die Sicherheitsregeln:
 *
 * - Account-Linking NUR über iss+sub bzw. IdP+NameID — nie über E-Mail
 *   (mutable/unverified, nOAuth-Angriff). E-Mail-Matching ist ein bewusstes
 *   Opt-in je Verbindung, greift nur bei genau einem Treffer in der eigenen
 *   Organisation und ist für Entra-Verbindungen GESPERRT (nOAuth: der
 *   email-Claim ist in Fremd-Tenants frei setzbar — {@see EntraIssuer}).
 * - Konten anlegen nur über das explizite JIT-Opt-in der Verbindung
 *   (MS365-Plan G2): neuer Nutzer mit Standardrolle, Lizenz-Limit-Guard,
 *   niemals stilles Verknüpfen mit einem BESTEHENDEN Konto per E-Mail.
 * - {@see User::canLogin()} (deactivated_at) gilt auch nach erfolgreichem
 *   IdP-Login; Portal-Konten (customer_id) sind ausgeschlossen.
 * - Mandantengrenze: das Konto muss zur Organisation der Verbindung gehören.
 */
class SsoLoginService {
    /**
     * @param array{subject: string, email: string|null, name?: string|null} $identity
     */
    public function resolveUser(SsoConnection $connection, array $identity): User {
        $subject = $identity['subject'];

        $existing = SsoIdentity::query()
            ->where('sso_connection_id', $connection->id)
            ->where('subject', $subject)
            ->first();

        $user = $existing?->user()->withoutGlobalScopes()->first();

        if (! $user instanceof User) {
            $user = $this->linkByEmail($connection, $subject, $identity['email'])
                ?? $this->provisionJit($connection, $subject, $identity);
        }
        if (! $user instanceof User) {
            $this->reject($connection, 'unknown_identity');
        }

        $this->assertLoginAllowed($connection, $user);

        return $user;
    }

    /** Nach erfolgreichem Guard-Login: Nachweise aktualisieren + Audit. */
    public function recordLogin(SsoConnection $connection, User $user): void {
        SsoIdentity::query()
            ->where('sso_connection_id', $connection->id)
            ->where('user_id', $user->id)
            ->update(['last_login_at' => now()]);

        $connection->forceFill(['last_login_at' => now()])->save();
        $connection->audit('sso.login', ['user_id' => $user->id, 'protocol' => $connection->protocol->value]);
    }

    private function linkByEmail(SsoConnection $connection, string $subject, ?string $email): ?User {
        if (! $connection->allow_email_link || ! filled($email)) {
            return null;
        }

        // Entra-Abwehr (nOAuth, MS365-Plan G1): auch für Alt-Konfigurationen,
        // die vor dem Konfigurations-Guard angelegt wurden — der email-Claim
        // aus Entra ist als Matching-Schlüssel grundsätzlich untauglich.
        if (EntraIssuer::isEntra((string) $connection->issuer)) {
            $this->reject($connection, 'email_link_untrusted_idp');
        }

        $candidates = User::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $connection->organization_id)
            ->whereNull('customer_id')
            ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $email)])
            ->whereDoesntHave('ssoIdentities', fn ($query) => $query->where('sso_connection_id', $connection->id))
            ->limit(2)
            ->get();

        if ($candidates->count() > 1) {
            $this->reject($connection, 'ambiguous_email');
        }
        if ($candidates->isEmpty()) {
            return null; // ggf. JIT (Opt-in) — sonst unknown_identity
        }

        /** @var User $user */
        $user = $candidates->first();

        SsoIdentity::query()->create([
            'sso_connection_id' => $connection->id,
            'user_id' => $user->id,
            'subject' => $subject,
        ]);
        $connection->audit('sso.identity_linked', ['user_id' => $user->id]);

        return $user;
    }

    /**
     * JIT-Provisioning (Opt-in je Verbindung, MS365-Plan G2): legt beim ersten
     * IdP-Login ein NEUES Konto an. Bewusst niemals ein Verknüpfen mit einem
     * bestehenden Konto (das wäre E-Mail-Matching durch die Hintertür) —
     * E-Mail-Kollision ⇒ Ablehnung. Lizenz-Nutzerlimit wie bei manueller
     * Anlage ({@see \App\Services\Plan\LimitGuard}).
     *
     * @param array{subject: string, email: string|null, name?: string|null} $identity
     */
    private function provisionJit(SsoConnection $connection, string $subject, array $identity): ?User {
        if (! $connection->jit_provisioning) {
            return null;
        }

        $email = mb_strtolower(trim((string) ($identity['email'] ?? '')));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->reject($connection, 'jit_email_missing');
        }

        if (User::query()->withoutGlobalScopes()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            // Konto existiert bereits (gleiche oder fremde Org) — kein stilles
            // Übernehmen; Verknüpfung nur manuell bzw. via allow_email_link.
            $this->reject($connection, 'jit_email_conflict');
        }

        $organization = $connection->organization()->withoutGlobalScopes()->first();
        if ($organization === null) {
            $this->reject($connection, 'jit_organization_missing');
        }

        try {
            app(\App\Services\Licensing\LimitGuard::class)->ensureCanCreateUser($organization);
        } catch (\Throwable) {
            $this->reject($connection, 'jit_user_limit_reached');
        }

        $name = trim((string) ($identity['name'] ?? ''));
        $user = User::query()->create([
            'organization_id' => $connection->organization_id,
            'name' => $name !== '' ? $name : (string) strstr($email, '@', true),
            'email' => $email,
            // Zufallspasswort: Login läuft über den IdP; Passwort-Reset bleibt
            // möglich, solange kein SSO-Zwang (enforced) greift.
            'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(40)),
            'must_change_password' => false,
            'is_new_system' => true,
        ]);

        $role = trim((string) $connection->jit_role);
        if ($role !== '') {
            app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($connection->organization_id);
            $user->assignRole(\Spatie\Permission\Models\Role::findOrCreate($role, 'web'));
        }

        SsoIdentity::query()->create([
            'sso_connection_id' => $connection->id,
            'user_id' => $user->id,
            'subject' => $subject,
        ]);
        $connection->audit('sso.user_provisioned', ['user_id' => $user->id, 'role' => $role !== '' ? $role : null]);

        return $user;
    }

    private function assertLoginAllowed(SsoConnection $connection, User $user): void {
        if ($user->customer_id !== null || $user->organization_id !== $connection->organization_id) {
            $this->reject($connection, 'tenant_mismatch', $user->id);
        }

        // Zentrale Deaktivierung (Offboarding) gilt AUCH nach IdP-Login.
        if (! $user->canLogin()) {
            $this->reject($connection, 'deactivated', $user->id);
        }
    }

    private function reject(SsoConnection $connection, string $reason, ?int $userId = null): never {
        Log::info('SSO: Login abgelehnt.', [
            'connection_id' => $connection->id,
            'reason' => $reason,
            'user_id' => $userId,
        ]);
        $connection->audit('sso.login_rejected', array_filter(['reason' => $reason, 'user_id' => $userId]));

        throw new SsoLoginException(__('sso.error.no_account'));
    }
}
