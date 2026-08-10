<?php
/*
 * Created on   : Mon Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalAccessService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\CustomerPortal;

use App\Mail\CustomerPortalInvitationMail;
use App\Models\{Customer, User};
use App\Services\Auth\UserSessionInvalidator;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\{Hash, Mail};
use Illuminate\Validation\ValidationException;

/**
 * Lebenszyklus der Kundenportal-Zugänge (MVP-510): einladen, erneut senden,
 * deaktivieren/widerrufen, reaktivieren und Einladung annehmen.
 *
 * Portalkonten sind users mit customer_id (einzige Trennlinie zum internen
 * Konto, {@see \App\Auth\CustomerUserProvider}). Der Einladungs-Token wird nur
 * als SHA-256-Hash gespeichert (Muster ExternalParticipantService); bis zur
 * Annahme trägt das Konto ein zufälliges, niemandem bekanntes Passwort —
 * keine Klartext- oder Admin-Startpasswörter per E-Mail.
 */
class PortalAccessService {
    public const STATE_INVITED = 'invited';

    public const STATE_EXPIRED = 'expired';

    public const STATE_ACTIVE = 'active';

    public const STATE_DEACTIVATED = 'deactivated';

    /** Gültigkeit eines Einladungs-Links in Tagen. */
    public const INVITE_TTL_DAYS = 7;

    public function __construct(private readonly UserSessionInvalidator $sessions) {}

    /**
     * Lädt einen neuen Portalzugang für den Kunden ein und versendet den
     * Einmal-Link. Gibt das angelegte Konto zurück; der Klartext-Token
     * verlässt die Methode nur in der E-Mail.
     *
     * @throws ValidationException bei nicht verwendbarer E-Mail (bewusst ohne
     *                             Grund — keine Konten-Enumeration)
     */
    public function invite(Customer $customer, string $name, string $email, User $actor): User {
        $email = mb_strtolower(trim($email));

        // users.email ist global eindeutig. Die Antwort verrät nicht, ob die
        // Adresse intern, in einer anderen Organisation oder bereits als
        // Portalkonto existiert.
        if (User::query()->withoutGlobalScopes()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages([
                'email' => (string) __('Für diese E-Mail-Adresse kann kein Portalzugang erstellt werden.'),
            ]);
        }

        $user = User::query()->create([
            'organization_id' => $customer->organization_id,
            'customer_id' => $customer->id,
            'name' => trim($name),
            'email' => $email,
            // Zufällig und niemandem bekannt — Login erst nach Annahme möglich.
            'password' => Hash::make(Str::random(64)),
            'is_new_system' => true,
        ]);

        $this->issueInvite($user, $actor, 'portal.access.invited');

        return $user;
    }

    /** Erneuert Token/Ablauf und versendet die Einladung erneut. */
    public function resend(User $portalUser, User $actor): void {
        $this->assertPortalUser($portalUser);
        $this->issueInvite($portalUser, $actor, 'portal.access.invite_resent');
    }

    /**
     * Widerruft den Zugang: sofortige Fernabmeldung aller Sessions, kein
     * weiterer Login, offene Einladung ungültig. Fachnachweise bleiben.
     */
    public function deactivate(User $portalUser, User $actor): void {
        $this->assertPortalUser($portalUser);

        $portalUser->forceFill([
            'deactivated_at' => Carbon::now(),
            'portal_invite_token_hash' => null,
            'portal_invite_expires_at' => null,
        ])->save();

        // Der Provider blockt Re-Logins über deactivated_at; laufende Sessions
        // beendet nur der Purge (retrieveById prüft das Feld nicht).
        $this->sessions->invalidateAll($portalUser);

        $portalUser->audit('portal.access.deactivated', ['by' => (int) $actor->id]);
    }

    /** Reaktiviert einen widerrufenen Zugang (ohne neue Einladung). */
    public function reactivate(User $portalUser, User $actor): void {
        $this->assertPortalUser($portalUser);

        $portalUser->forceFill(['deactivated_at' => null])->save();
        $portalUser->audit('portal.access.reactivated', ['by' => (int) $actor->id]);
    }

    /**
     * Löst einen Klartext-Token auf: Hash-Match + nicht abgelaufen + Konto
     * nicht deaktiviert — sonst null (Controller antwortet neutral mit 404).
     */
    public function resolveInvite(string $token): ?User {
        if ($token === '') {
            return null;
        }

        $user = User::query()
            ->withoutGlobalScopes()
            ->where('portal_invite_token_hash', CryptoHelper::hash($token))
            ->whereNotNull('customer_id')
            ->whereNull('deactivated_at')
            ->first();

        if ($user === null || $user->portal_invite_expires_at === null || $user->portal_invite_expires_at->isPast()) {
            return null;
        }

        return $user;
    }

    /**
     * Nimmt die Einladung an: setzt das selbst gewählte Passwort und
     * entwertet den Token endgültig.
     */
    public function accept(User $portalUser, string $password): void {
        $portalUser->forceFill([
            'password' => Hash::make($password),
            'portal_invite_token_hash' => null,
            'portal_invite_expires_at' => null,
            'email_verified_at' => Carbon::now(),
            'is_new_system' => true,
            'must_change_password' => false,
        ])->save();

        $portalUser->audit('portal.access.invite_accepted', []);
    }

    /** Zustand fürs Verwaltungs-Panel: invited | expired | active | deactivated. */
    public function state(User $portalUser): string {
        if ($portalUser->isDeactivated()) {
            return self::STATE_DEACTIVATED;
        }
        if ($portalUser->portal_invite_token_hash !== null) {
            return $portalUser->portal_invite_expires_at !== null && $portalUser->portal_invite_expires_at->isPast()
                ? self::STATE_EXPIRED
                : self::STATE_INVITED;
        }

        return self::STATE_ACTIVE;
    }

    /** Erzeugt Token + Ablauf, auditiert und versendet die Einladung. */
    private function issueInvite(User $portalUser, User $actor, string $auditEvent): void {
        $token = Str::random(48);

        $portalUser->forceFill([
            'portal_invite_token_hash' => CryptoHelper::hash($token),
            'portal_invite_expires_at' => Carbon::now()->addDays(self::INVITE_TTL_DAYS),
            'portal_invited_at' => Carbon::now(),
        ])->save();

        $portalUser->audit($auditEvent, [
            'by' => (int) $actor->id,
            'expires_at' => $portalUser->portal_invite_expires_at?->toIso8601String(),
        ]);

        Mail::to($portalUser->email)->send(new CustomerPortalInvitationMail(
            $portalUser,
            route('customer.invitation.show', ['token' => $token]),
        ));
    }

    private function assertPortalUser(User $user): void {
        if (! $user->isCustomer()) {
            throw new \InvalidArgumentException('PortalAccessService verwaltet ausschließlich Portalkonten (customer_id gesetzt).');
        }
    }
}
