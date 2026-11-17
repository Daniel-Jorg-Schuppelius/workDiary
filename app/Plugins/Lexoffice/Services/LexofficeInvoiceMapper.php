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

use App\Exceptions\UnmappableTaxCaseException;
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Services\Invoicing\OutgoingTaxCaseResolver;

/**
 * Übersetzt eine lokale workDiary-Invoice in den JSON-Payload, den
 * Lexoffice unter POST /v1/invoices erwartet.
 *
 * Doku: https://developers.lexware.io/docs/#invoices-endpoint
 *
 * Die Steuerart folgt dem Steuerfall der Rechnung (MVP-1712,
 * {@see LexofficeTaxConditions}): ausgestellt der eingefrorene, sonst der des
 * Ausstellens. Vorher gingen Reverse Charge mit der Voreinstellung und jede
 * Rechnung ohne Steuer als „steuerfrei“ hinüber.
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
        $taxConditions = $this->taxConditions($invoice, $items, $defaults, $externalContactId);
        $remark = $invoice->notes ?: ($invoice->customer->invoice_text ?: null);
        // Der Hinweis steht als taxTypeNote auf dem Beleg; in der Bemerkung stünde er doppelt.
        if ($remark !== null && isset($taxConditions['taxTypeNote']) && str_contains($remark, $taxConditions['taxTypeNote'])) {
            $remark = trim((string) preg_replace("/\n{3,}/", "\n\n", str_replace($taxConditions['taxTypeNote'], '', $remark))) ?: null;
        }

        return array_filter([
            'voucherDate' => $issuedOn->format('Y-m-d') . 'T00:00:00.000+01:00',
            'dueDate' => $dueOn->format('Y-m-d') . 'T00:00:00.000+01:00',
            'address' => $this->addressForCustomer($invoice->customer, $externalContactId),
            // Steuersatz und Rabatt je Zeile (Befund B9); Zeilen ohne eigenen Satz tragen den der Rechnung.
            'lineItems' => LexofficeLineItems::from($items, fallbackRate: $invoice->tax_rate),
            'totalPrice' => ['currency' => $currency] + $this->documentDiscount($invoice),
            'taxConditions' => $taxConditions,
            'shippingConditions' => [
                'shippingDate' => $issuedOn->format('Y-m-d') . 'T00:00:00.000+01:00',
                'shippingType' => 'service',
            ],
            'paymentConditions' => $this->paymentConditionsForInvoice($invoice, $dueOn),
            'title' => $invoice->isCreditNote() ? __('Gutschrift') : __('Rechnung'),
            'remark' => $remark,
        ], static fn($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * @param  iterable<\App\Models\Invoicing\InvoiceItem>  $items
     * @param  array<string, mixed>  $defaults
     * @return array{taxType: string, taxTypeNote?: string}
     *
     * @throws UnmappableTaxCaseException
     */
    private function taxConditions(Invoice $invoice, iterable $items, array $defaults, ?string $externalContactId): array {
        $cases = app(OutgoingTaxCaseResolver::class)->forInvoice($invoice);
        if (count($cases) !== 1) {
            throw new UnmappableTaxCaseException((string) __('invoicing.tax_case.place_of_supply_mixed'));
        }
        $conditions = LexofficeTaxConditions::for($cases[0], LexofficeLineItems::taxType($items, $defaults, $invoice->tax_rate));
        if (LexofficeTaxConditions::requiresContact($conditions['taxType']) && blank($externalContactId)) {
            throw new UnmappableTaxCaseException((string) __('lexoffice::finance.tax_case.contact_required', ['case' => $cases[0]->case->label()]));
        }

        return $conditions;
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
        // Gutschriften haben laut Lexware-Referenz weder Fälligkeit noch Zahlungs- oder Versandbedingungen.
        unset($payload['dueDate'], $payload['paymentConditions'], $payload['shippingConditions']);
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
