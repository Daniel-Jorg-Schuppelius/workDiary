<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalEmailChangeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\CustomerPortal;

use App\Mail\{PortalEmailChangeConfirmMail, PortalEmailChangedNoticeMail};
use App\Models\User;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Mail, URL};

/**
 * E-Mail-Änderung durch den Portalnutzer selbst (Feature 012-Ausbau, MVP-712):
 * neue Adresse → Bestätigungslink (signierte URL, 24 h) an die NEUE Adresse;
 * der Wechsel passiert erst beim Klick, die alte Adresse erhält eine
 * Info-Mail. Kollisionen (users.email ist global eindeutig) werden NICHT
 * verraten — die Portal-Antwort ist in beiden Fällen identisch, nur das
 * interne Audit kennt den Grund. Deaktivierte Zugänge ändern nichts.
 */
class PortalEmailChangeService {
    public const TTL_HOURS = 24;

    /**
     * Startet die Änderung. Liefert true, wenn eine Bestätigungs-Mail
     * versendet wurde — der Aufrufer antwortet trotzdem neutral.
     */
    public function request(User $portalUser, string $newEmail): bool {
        $this->assertPortalUser($portalUser);
        if ($portalUser->isDeactivated()) {
            throw new \RuntimeException((string) __('Dieser Zugang ist deaktiviert.'));
        }

        $newEmail = mb_strtolower(trim($newEmail));

        if ($newEmail === mb_strtolower((string) $portalUser->email)) {
            $portalUser->audit('portal.profile.email_change_blocked', ['reason' => 'unchanged']);

            return false;
        }

        if ($this->emailTaken($newEmail, $portalUser)) {
            // Keine Enumeration: still protokollieren, nichts speichern.
            $portalUser->audit('portal.profile.email_change_blocked', ['reason' => 'taken']);

            return false;
        }

        $portalUser->forceFill([
            'portal_pending_email' => $newEmail,
            'portal_pending_email_requested_at' => Carbon::now(),
        ])->save();
        $portalUser->audit('portal.profile.email_change_requested', ['new_email' => $newEmail]);

        Mail::to($newEmail)->send(new PortalEmailChangeConfirmMail($portalUser, $newEmail, $this->confirmUrl($portalUser, $newEmail)));

        return true;
    }

    /**
     * Schließt die Änderung ab: schwebende Adresse muss zum Hash passen,
     * innerhalb der Frist liegen und (Race) weiterhin frei sein. Liefert die
     * alte Adresse oder null, wenn nichts geändert wurde (Aufrufer: 404).
     */
    public function confirm(User $portalUser, string $hash): ?string {
        $this->assertPortalUser($portalUser);

        $pending = (string) $portalUser->portal_pending_email;
        $requestedAt = $portalUser->portal_pending_email_requested_at;

        if ($portalUser->isDeactivated() || $pending === '' || $requestedAt === null || ! hash_equals($this->hashFor($pending), $hash)) {
            return null;
        }
        if ($requestedAt->copy()->addHours(self::TTL_HOURS)->isPast()) {
            $this->clearPending($portalUser);

            return null;
        }
        if ($this->emailTaken($pending, $portalUser)) {
            $this->clearPending($portalUser);
            $portalUser->audit('portal.profile.email_change_blocked', ['reason' => 'taken_on_confirm']);

            return null;
        }

        $oldEmail = (string) $portalUser->email;
        $portalUser->forceFill([
            'email' => $pending,
            'email_verified_at' => Carbon::now(),
            'portal_pending_email' => null,
            'portal_pending_email_requested_at' => null,
        ])->save();
        $portalUser->audit('portal.profile.email_changed', ['from' => $oldEmail, 'to' => $pending]);

        if ($oldEmail !== '') {
            Mail::to($oldEmail)->send(new PortalEmailChangedNoticeMail($portalUser, $oldEmail, $pending));
        }

        return $oldEmail;
    }

    public function confirmUrl(User $portalUser, string $newEmail): string {
        return URL::temporarySignedRoute('customer.profile.email.confirm', Carbon::now()->addHours(self::TTL_HOURS), [
            'user' => $portalUser->getRouteKey(),
            'hash' => $this->hashFor($newEmail),
        ]);
    }

    /** Öffentlicher Hash-Parameter — enthält die Adresse nicht im Klartext. */
    public function hashFor(string $email): string {
        return CryptoHelper::hash(mb_strtolower(trim($email)));
    }

    private function emailTaken(string $email, User $except): bool {
        return User::query()
            ->withoutGlobalScopes()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereKeyNot($except->getKey())
            ->exists();
    }

    private function clearPending(User $portalUser): void {
        $portalUser->forceFill(['portal_pending_email' => null, 'portal_pending_email_requested_at' => null])->save();
    }

    private function assertPortalUser(User $user): void {
        if (! $user->isCustomer()) {
            throw new \InvalidArgumentException('PortalEmailChangeService verwaltet ausschließlich Portalkonten (customer_id gesetzt).');
        }
    }
}
