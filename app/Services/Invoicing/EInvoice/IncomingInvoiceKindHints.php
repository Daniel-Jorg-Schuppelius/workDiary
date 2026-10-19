<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceKindHints.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\EInvoice;

use App\Enums\Billing\DocumentKind;
use App\Models\Invoicing\IncomingEInvoice;

/**
 * Belegart-Hinweise der Erkennung (MVP-1216) in Worten und als Vorschlag für
 * den Zuordnen-Dialog: Mahnung, Lieferschein, Bestätigung, Angebot und
 * Versandmarke sind keine Rechnungen — vorbelegt wird „Keine Rechnung“, die
 * Entscheidung bleibt beim Menschen.
 */
class IncomingInvoiceKindHints {
    public function label(?string $hint): ?string {
        return match ($hint) {
            'reminder' => DocumentKind::Reminder->label(),
            'delivery_note' => DocumentKind::DeliveryNote->label(),
            'order_confirmation' => DocumentKind::OrderConfirmation->label(),
            'quote' => DocumentKind::Quote->label(),
            'shipping_label' => (string) __('Versandmarke'),
            default => null,
        };
    }

    /**
     * Vorschlag für den Dialog: Weg „Keine Rechnung“ mit Begründung, bei
     * Mahnungen mit den angemahnten Rechnungsnummern.
     *
     * @return array{mode: string, note: string}|null
     */
    public function proposal(IncomingEInvoice $incoming): ?array {
        $label = $this->label($incoming->kindHint());
        if ($label === null) {
            return null;
        }
        $numbers = array_values(array_filter(array_map(strval(...), (array) (((array) $incoming->summary)['reminder_for'] ?? []))));
        $note = $incoming->kindHint() === 'reminder' && $numbers !== []
            ? (string) __('Mahnung zu :numbers', ['numbers' => implode(', ', $numbers)])
            : $label;

        return ['mode' => 'not_invoice', 'note' => $note];
    }
}
