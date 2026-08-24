<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyAppointmentMatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Calendly\Services;

use App\Models\{Customer, Organization, User};

/**
 * Ordnet einen Calendly-Invitee einem Kunden und den Host einem WorkDiary-
 * Benutzer zu (Feature 095) — Spiegel der `LexofficeContactSync::findLocalMatch`-
 * Semantik, reduziert auf die im Invitee-Payload vorhandenen Merkmale
 * (E-Mail, Name). Es wird NIE geraten: bei Mehrdeutigkeit gibt es keinen
 * Treffer (→ Zuordnungs-Inbox).
 */
class CalendlyAppointmentMatcher {
    /** Invitee-E-Mail → Kunde (E-Mail, Kontaktpersonen-E-Mail, eindeutiger Name). */
    public function matchCustomer(Organization $organization, ?string $email, ?string $name): ?Customer {
        $email = trim((string) $email);
        if ($email !== '') {
            $byEmail = Customer::query()
                ->where('organization_id', $organization->id)
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
                ->first();
            if ($byEmail instanceof Customer) {
                return $byEmail;
            }

            $byContact = Customer::query()
                ->where('organization_id', $organization->id)
                ->whereLikeEscaped('contact_persons', $email)
                ->get()
                ->first(fn(Customer $customer): bool => $this->contactHasEmail($customer, $email));
            if ($byContact instanceof Customer) {
                return $byContact;
            }
        }

        $name = trim((string) $name);
        if ($name !== '') {
            $matches = Customer::query()
                ->where('organization_id', $organization->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->limit(2)
                ->get();
            if ($matches->count() === 1) {
                return $matches->first();
            }
        }

        return null;
    }

    /**
     * Host-E-Mail(s) aus `scheduled_event.event_memberships` → WorkDiary-Benutzer-ID.
     *
     * @param  array<string, mixed>  $scheduledEvent
     */
    public function matchHostUser(Organization $organization, array $scheduledEvent): ?int {
        $memberships = is_array($scheduledEvent['event_memberships'] ?? null) ? $scheduledEvent['event_memberships'] : [];
        foreach ($memberships as $membership) {
            $email = is_array($membership) ? trim((string) ($membership['user_email'] ?? '')) : '';
            if ($email === '') {
                continue;
            }
            $user = User::query()
                ->where('organization_id', $organization->id)
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
                ->first();
            if ($user instanceof User) {
                return (int) $user->id;
            }
        }

        return null;
    }

    private function contactHasEmail(Customer $customer, string $email): bool {
        foreach ((array) $customer->contact_persons as $person) {
            if (mb_strtolower((string) ($person['email'] ?? '')) === mb_strtolower($email)) {
                return true;
            }
        }

        return false;
    }
}
