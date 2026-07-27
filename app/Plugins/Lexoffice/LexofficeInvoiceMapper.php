<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeInvoiceMapper.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice;

use App\Models\{Customer, Invoice, InvoiceItem};

/**
 * Übersetzt eine lokale workDiary-Invoice in den JSON-Payload, den
 * Lexoffice unter POST /v1/invoices erwartet.
 *
 * Doku: https://developers.lexoffice.io/docs/#invoices-endpoint
 */
class LexofficeInvoiceMapper {
    /**
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public function toPayload(Invoice $invoice, ?string $externalContactId, array $defaults = []): array {
        $invoice->loadMissing(['items', 'customer']);

        $taxType = (string) ($defaults['default_tax_type'] ?? 'net');
        $currency = $invoice->currency->value;
        $issuedOn = $invoice->issued_on ?? $invoice->created_at ?? now();
        $dueOn = $invoice->due_on ?? $issuedOn->copy()->addDays(14);

        return array_filter([
            'voucherDate' => $issuedOn->format('Y-m-d') . 'T00:00:00.000+01:00',
            'dueDate' => $dueOn->format('Y-m-d') . 'T00:00:00.000+01:00',
            'address' => $this->addressForCustomer($invoice->customer, $externalContactId),
            'lineItems' => $invoice->items->map(fn(InvoiceItem $i) => $this->mapItem($i, $currency, $invoice->tax_rate !== null ? (float) $invoice->tax_rate->getNumericValue() : 0.0))->values()->all(),
            'totalPrice' => [
                'currency' => $currency,
            ],
            'taxConditions' => [
                'taxType' => $taxType,
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
     * @return array<string, mixed>
     */
    private function addressForCustomer(Customer $customer, ?string $externalContactId): array {
        if ($externalContactId !== null && $externalContactId !== '') {
            return ['contactId' => $externalContactId];
        }

        return array_filter([
            'name' => $customer->company ?: $customer->name,
            'street' => $customer->address_street,
            'zip' => $customer->address_zip,
            'city' => $customer->address_city,
            'countryCode' => $customer->country ?: 'DE',
        ], static fn($v) => $v !== null && $v !== '');
    }

    /**
     * @return array<string, mixed>
     */
    private function mapItem(InvoiceItem $item, string $currency, float $taxRate): array {
        $type = $item->expense_id !== null ? 'custom' : 'service';

        return array_filter([
            'type' => $type,
            'name' => $item->description ?: __('Position'),
            'quantity' => (float) $item->quantity,
            'unitName' => $item->unit ?: 'h',
            'unitPrice' => [
                'currency' => $currency,
                'netAmount' => $item->unit_price?->toFloat() ?? 0.0,
                'taxRatePercentage' => $taxRate,
            ],
        ], static fn($v) => $v !== '');
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
