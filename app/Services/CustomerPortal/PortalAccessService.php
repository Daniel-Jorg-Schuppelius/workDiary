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

use App\Mail\{CustomerPortalInvitationMail, CustomerPortalPasswordResetMail, PortalSecondFactorResetNoticeMail};
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Services\Auth\UserSessionInvalidator;
use App\Support\CanonicalUrl;
use CommonToolkit\Helper\Data\{CryptoHelper, EmailHelper};
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\{Hash, Mail, URL};
use Illuminate\Validation\ValidationException;

/**
 * Lebenszyklus der Kundenportal-Zugänge (MVP-510): einladen, erneut senden,
 * deaktivieren/widerrufen, reaktivieren, zurücksetzen und Einladung annehmen;
 * dazu „Passwort vergessen“ für aktive Zugänge (MVP-1096).
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

    /** Gültigkeit eines „Passwort vergessen“-Links in Minuten (wie intern). */
    public const PASSWORD_RESET_TTL_MINUTES = 60;

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
        // Der Sammelkunde (MVP-1109) steht für viele Einmalkunden.
        if ($customer->is_collective) {
            throw ValidationException::withMessages(['email' => (string) __('Der Sammelkunde erhält keinen Portalzugang.')]);
        }
        $email = EmailHelper::normalize($email);

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
     * Setzt einen aktiven Zugang zurück: das bisherige Passwort wird durch ein
     * unbekanntes ersetzt, alle Sitzungen enden, und der Kontakt erhält eine
     * neue Einladung. Zwei-Faktor-Methoden bleiben bestehen.
     */
    public function reset(User $portalUser, User $actor): void {
        $this->assertPortalUser($portalUser);

        $portalUser->forceFill(['password' => Hash::make(Str::random(64))])->save();
        $this->sessions->invalidateAll($portalUser);

        $this->issueInvite($portalUser, $actor, 'portal.access.reset', reset: true);
    }

    /**
     * Entfernt alle Zwei-Faktor-Methoden eines Zugangs, der keinen Faktor mehr
     * besitzt (MVP-1100); Sitzungen enden, der Kunde wird informiert. Verlangt
     * die Organisation 2FA, richtet er sie bei der nächsten Anmeldung neu ein.
     */
    public function resetSecondFactor(User $portalUser, User $actor): void {
        $this->assertPortalUser($portalUser);

        $portalUser->twoFactorCredentials()->delete();
        $portalUser->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        $this->sessions->invalidateAll($portalUser);

        $portalUser->audit('portal.access.second_factor_reset', ['by' => (int) $actor->id]);
        Mail::to($portalUser->email)->send(new PortalSecondFactorResetNoticeMail($portalUser));
    }

    /**
     * „Passwort vergessen“: versendet einen Link nur an aktive Zugänge eines
     * nicht gesperrten Mandanten. Der Aufrufer antwortet immer gleich.
     */
    public function sendPasswordReset(string $email): void {
        $portalUser = User::query()
            ->withoutGlobalScopes()
            ->whereRaw('LOWER(email) = ?', [EmailHelper::normalize($email)])
            ->whereNotNull('customer_id')
            ->first();

        if ($portalUser === null || $this->state($portalUser) !== self::STATE_ACTIVE) {
            return;
        }
        $organization = Organization::query()->withoutGlobalScopes()->find($portalUser->organization_id);
        if ($organization instanceof Organization && ! $organization->publicSurfacesAvailable()) {
            return;
        }

        $portalUser->audit('portal.access.password_reset_requested', []);

        Mail::to($portalUser->email)->send(new CustomerPortalPasswordResetMail(
            $portalUser,
            $this->passwordResetUrl($portalUser),
            self::PASSWORD_RESET_TTL_MINUTES,
        ));
    }

    /**
     * Signierter Link aus der konfigurierten Adresse (S-11: der Aufruf ist
     * anonym, der Host-Header darf das Ziel nicht bestimmen).
     */
    public function passwordResetUrl(User $portalUser): string {
        return CanonicalUrl::base() . URL::temporarySignedRoute(
            'customer.password.reset',
            Carbon::now()->addMinutes(self::PASSWORD_RESET_TTL_MINUTES),
            ['user' => $portalUser->getRouteKey(), 'hash' => $this->passwordFingerprint($portalUser)],
            false,
        );
    }

    /** Gilt der Link (Signatur prüft der Controller) noch für dieses Konto? */
    public function resolvePasswordReset(User $portalUser, string $hash): bool {
        return $portalUser->isCustomer()
            && $this->state($portalUser) === self::STATE_ACTIVE
            && hash_equals($this->passwordFingerprint($portalUser), $hash);
    }

    /** Setzt das neue Passwort und beendet alle Sitzungen; 2FA bleibt unverändert. */
    public function resetPassword(User $portalUser, string $password): void {
        $this->assertPortalUser($portalUser);

        $portalUser->forceFill([
            'password' => Hash::make($password),
            'is_new_system' => true,
            'must_change_password' => false,
        ])->save();
        $this->sessions->invalidateAll($portalUser);

        $portalUser->audit('portal.access.password_reset', []);
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

    /**
     * Bindet den Link an Passwortstand und Adresse: nach dem Setzen, einem
     * Zurücksetzen durch den Auftragnehmer oder einem Adresswechsel gilt er
     * nicht mehr — einmalig ohne gespeicherten Token.
     */
    private function passwordFingerprint(User $portalUser): string {
        return (string) CryptoHelper::hash($portalUser->getAuthPassword() . '|' . mb_strtolower((string) $portalUser->email));
    }

    /** Erzeugt Token + Ablauf, auditiert und versendet die Einladung. */
    private function issueInvite(User $portalUser, User $actor, string $auditEvent, bool $reset = false): void {
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
            $reset,
        ));
    }

    private function assertPortalUser(User $user): void {
        if (! $user->isCustomer()) {
            throw new \InvalidArgumentException('PortalAccessService verwaltet ausschließlich Portalkonten (customer_id gesetzt).');
        }
    }
}
