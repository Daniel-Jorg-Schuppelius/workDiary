<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnownSenders.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Mail;

use CommonToolkit\Helper\Data\EmailHelper;

/**
 * Gehört ein Absender zum Vorgang? Fortlaufende Ticket- und Rechnungsnummern
 * sind erratbar; Ticket-Faden und Rechnungsnachfragen nehmen eine Mail über
 * die Nummer deshalb nur von bekannten Adressen an (Sicherheitsaudit
 * 2026-09-17, ingress-1). Die Adressen des Kunden liefert
 * {@see \App\Models\Customer\Customer::knownEmailAddresses()}, jeder Vorgang
 * ergänzt seine eigenen.
 */
final class KnownSenders {
    /** @param iterable<string|null> $addresses */
    public static function contains(iterable $addresses, string $sender): bool {
        $sender = EmailHelper::normalize($sender);
        if ($sender === '') {
            return false;
        }

        foreach ($addresses as $candidate) {
            $candidate = EmailHelper::normalize((string) $candidate);
            if ($candidate !== '' && hash_equals($candidate, $sender)) {
                return true;
            }
        }

        return false;
    }
}
