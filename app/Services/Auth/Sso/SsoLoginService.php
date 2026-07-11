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
 *   Opt-in je Verbindung und greift nur bei genau einem Treffer in der
 *   eigenen Organisation.
 * - SSO legt NIE Konten an und vergibt NIE Rollen (DoD Phase 11).
 * - {@see User::canLogin()} (deactivated_at) gilt auch nach erfolgreichem
 *   IdP-Login; Portal-Konten (customer_id) sind ausgeschlossen.
 * - Mandantengrenze: das Konto muss zur Organisation der Verbindung gehören.
 */
class SsoLoginService {
    /**
     * @param array{subject: string, email: string|null} $identity
     */
    public function resolveUser(SsoConnection $connection, array $identity): User {
        $subject = $identity['subject'];

        $existing = SsoIdentity::query()
            ->where('sso_connection_id', $connection->id)
            ->where('subject', $subject)
            ->first();

        $user = $existing?->user()->withoutGlobalScopes()->first();

        if (! $user instanceof User) {
            $user = $this->linkByEmail($connection, $subject, $identity['email']);
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

    private function linkByEmail(SsoConnection $connection, string $subject, ?string $email): User {
        if (! $connection->allow_email_link || ! filled($email)) {
            $this->reject($connection, 'unknown_identity');
        }

        $candidates = User::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $connection->organization_id)
            ->whereNull('customer_id')
            ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $email)])
            ->whereDoesntHave('ssoIdentities', fn ($query) => $query->where('sso_connection_id', $connection->id))
            ->limit(2)
            ->get();

        if ($candidates->count() !== 1) {
            $this->reject($connection, $candidates->isEmpty() ? 'unknown_identity' : 'ambiguous_email');
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
