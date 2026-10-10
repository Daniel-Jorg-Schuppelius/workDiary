<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceTransferGate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\EInvoice;

use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceRecognition};
use App\Models\Invoicing\IncomingEInvoice;
use App\Services\Stammdaten\CollectiveContacts;

/**
 * Tor vor jeder Übergabe an ein Buchhaltungsziel (Feature 163, MVP-1111),
 * gleich für alle Ziele. Ein im Zielsystem angelegter Beleg lässt sich per API
 * nicht mehr löschen — was hier hängen bleibt, klärt erst ein Mensch.
 */
class IncomingInvoiceTransferGate {
    /** @return list<string> Gründe, warum (noch) nicht übergeben wird */
    public function blockers(IncomingEInvoice $incoming): array {
        $flags = (array) (((array) $incoming->summary)['flags'] ?? []);
        $party = $incoming->counterparty();

        return array_values(array_filter([
            $incoming->status === IncomingEInvoiceStatus::Rejected ? (string) __('Der Eingang ist abgelehnt.') : null,
            $party === null ? (string) __('Noch keiner Partei zugeordnet.') : null,
            $incoming->recognition === IncomingInvoiceRecognition::None && ! isset(((array) $incoming->summary)['manual'])
                ? (string) __('Werte fehlen (Klärfall).') : null,
            in_array('not_addressed', $flags, true) ? (string) __('Nicht an uns adressiert.') : null,
            in_array('own_invoice_copy', $flags, true) ? (string) __('Kopie einer eigenen Rechnung.') : null,
            in_array('totals_mismatch', $flags, true) ? (string) __('Summen widersprüchlich.') : null,
            $party !== null && $party->is_collective && CollectiveContacts::requiresNamedContact($incoming)
                ? (string) __('Reverse Charge, innergemeinschaftlicher Fall oder Drittland: Dafür braucht es einen echten Firmenkontakt, keinen Sammelkontakt.') : null,
        ]));
    }
}
