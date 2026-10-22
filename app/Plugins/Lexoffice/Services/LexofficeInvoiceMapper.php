<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeInvoiceMapper.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Services;

use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;

/**
 * Übersetzt eine lokale workDiary-Invoice in den JSON-Payload, den
 * Lexoffice unter POST /v1/invoices erwartet.
 *
 * Doku: https://developers.lexware.io/docs/#invoices-endpoint
 */
class LexofficeInvoiceMapper {
    /**
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public function toPayload(Invoice $invoice, ?string $externalContactId, array $defaults = []): array {
        $invoice->loadMissing(['items', 'customer']);

        $currency = $invoice->currency->value;
        // Übertragen wird beim Ausstellen (MVP-1166): Belegdatum ist heute, Fälligkeit nach dem Zahlungsziel der Rechnung.
        $issuedOn = $invoice->issued_on ?? now();
        $dueOn = $invoice->due_on ?? $issuedOn->copy()->addDays($invoice->effectivePaymentTermsDays());
        $items = $invoice->items->sortBy('position')->values();

        return array_filter([
            'voucherDate' => $issuedOn->format('Y-m-d') . 'T00:00:00.000+01:00',
            'dueDate' => $dueOn->format('Y-m-d') . 'T00:00:00.000+01:00',
            'address' => $this->addressForCustomer($invoice->customer, $externalContactId),
            // Steuersatz und Rabatt je Zeile (Befund B9); Zeilen ohne eigenen Satz tragen den der Rechnung.
            'lineItems' => LexofficeLineItems::from($items, fallbackRate: $invoice->tax_rate),
            'totalPrice' => ['currency' => $currency] + $this->documentDiscount($invoice),
            'taxConditions' => [
                // Reverse Charge behält die Voreinstellung — die Steuerart dazu (§ 13b, innergemeinschaftlich) kennt die Rechnung nicht.
                'taxType' => $invoice->is_reverse_charge ? (string) ($defaults['default_tax_type'] ?? 'net') : LexofficeLineItems::taxType($items, $defaults, $invoice->tax_rate),
            ],
            'shippingConditions' => [
                'shippingDate' => $issuedOn->format('Y-m-d') . 'T00:00:00.000+01:00',
                'shippingType' => 'service',
            ],
            'paymentConditions' => $this->paymentConditionsForInvoice($invoice, $dueOn),
            'title' => $invoice->isCreditNote() ? __('Gutschrift') : __('Rechnung'),
            'remark' => $invoice->notes ?: ($invoice->customer->invoice_text ?: null),
        ], static fn($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * Gutschrift und Storno als Lexoffice-Rechnungskorrektur (MVP-1154): Lexoffice
     * erwartet positive Mengen und kennt keine Fälligkeit.
     *
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public function toCreditNotePayload(Invoice $invoice, ?string $externalContactId, array $defaults = []): array {
        $payload = $this->toPayload($invoice, $externalContactId, $defaults);
        unset($payload['dueDate'], $payload['paymentConditions']);
        $payload['title'] = $invoice->documentLabel();
        $payload['lineItems'] = array_map(
            static fn (array $line): array => isset($line['quantity']) ? ['quantity' => abs((float) $line['quantity'])] + $line : $line,
            LexofficeLineItems::from($invoice->items->sortBy('position')->values(), fallbackRate: $invoice->tax_rate, discountInPrice: true),
        );
        // Lexoffice kehrt das Vorzeichen selbst; der Betragsrabatt der Korrektur ist negiert gespeichert.
        $payload['totalPrice'] = ['currency' => $invoice->currency->value] + $this->documentDiscount($invoice);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function addressForCustomer(Customer $customer, ?string $externalContactId): array {
        if ($externalContactId !== null && $externalContactId !== '') {
            return ['contactId' => $externalContactId];
        }

        return array_filter([
            'name' => $customer->displayLabel(),
            'street' => $customer->address_street,
            'zip' => $customer->address_zip,
            'city' => $customer->address_city,
            'countryCode' => $customer->country ?: 'DE',
        ], static fn($v) => $v !== null && $v !== '');
    }

    /**
     * Belegrabatt ausdrücklich, sonst setzt Lexoffice den Rabatt des Kontakts
     * (Phase 145): Prozent als Prozent, ein Betrag netto und positiv.
     *
     * @return array<string, float>
     */
    private function documentDiscount(Invoice $invoice): array {
        $percent = $invoice->discount_percent;
        if ($percent !== null && $percent->isPositive()) {
            return ['totalDiscountPercentage' => (float) $percent->getNumericValue()];
        }
        $amount = $invoice->discount_amount;
        if ($amount !== null && ! $amount->isZero()) {
            return ['totalDiscountAbsolute' => abs($amount->toFloat())];
        }

        return ['totalDiscountPercentage' => 0.0];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function paymentConditionsForInvoice(Invoice $invoice, \Carbon\Carbon $dueOn): ?array {
        $issued = $invoice->issued_on ?? $invoice->created_at ?? now();
        $days = max(0, $dueOn->diffInDays($issued));
        if ($days === 0) {
            return null;
        }

        return [
            'paymentTermLabel' => __('Zahlbar innerhalb :n Tagen.', ['n' => $days]),
            'paymentTermDuration' => $days,
        ];
    }
}
