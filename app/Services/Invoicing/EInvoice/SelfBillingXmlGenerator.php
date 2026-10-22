<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SelfBillingXmlGenerator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\EInvoice;

use App\Models\Invoicing\SelfBillingCredit;
use CommonToolkit\ValueObjects\Money;
use ERechnungToolkit\Builders\ERechnungDocumentBuilder;
use ERechnungToolkit\Entities\InvoiceLine;
use ERechnungToolkit\Enums\{ERechnungProfile, InvoiceType, PaymentMeansCode, TaxCategory, UnitCode};
use Illuminate\Support\Str;

/**
 * Gutschrift im Gutschriftsverfahren als XRechnung (MVP-1280): Typcode 389
 * (Self-billed invoice). Verkäufer ist der Lieferant, der die Leistung
 * erbracht hat; Käuferin ist die ausstellende Organisation.
 */
class SelfBillingXmlGenerator {
    public function __construct(private readonly XRechnungGenerator $xrechnung) {}

    /**
     * Was vor dem Ausstellen fehlt — Pflichtangaben nach § 14 Abs. 4 UStG und
     * die Vereinbarung des Lieferanten.
     *
     * @return list<string>
     */
    public function preflight(SelfBillingCredit $credit): array {
        $credit->loadMissing(['supplier', 'lines']);
        $supplier = $credit->supplier;
        $errors = [];
        if ($supplier?->self_billing_agreed_on === null) {
            $errors[] = (string) __('invoicing.self_billing.errors.no_agreement');
        }
        if (trim((string) $supplier?->vat_id) === '' && trim((string) $supplier?->tax_number) === '') {
            $errors[] = (string) __('invoicing.self_billing.errors.no_tax_id');
        }
        if (trim((string) $supplier?->address_street) === '' || trim((string) $supplier?->address_zip) === '' || trim((string) $supplier?->address_city) === '') {
            $errors[] = (string) __('invoicing.self_billing.errors.no_address');
        }
        if ($credit->lines->isEmpty()) {
            $errors[] = (string) __('invoicing.self_billing.errors.no_lines');
        }
        if ($credit->service_from === null || $credit->service_until === null) {
            $errors[] = (string) __('invoicing.self_billing.errors.no_period');
        }

        return $errors;
    }

    public function generate(SelfBillingCredit $credit): string {
        $credit->loadMissing(['lines', 'organization']);
        $supplier = $credit->supplier()->firstOrFail();
        $buyer = $this->xrechnung->sellerDataFor($credit->organization);
        $currency = $credit->documentCurrency();
        $issuedOn = $credit->issued_on ?? now();
        $vatId = trim((string) $supplier->vat_id);
        $taxNumber = trim((string) $supplier->tax_number);

        $builder = ERechnungDocumentBuilder::create((string) $credit->number)
            ->withProfile(ERechnungProfile::XRECHNUNG)
            ->withInvoiceType(InvoiceType::SELF_BILLED_INVOICE)
            ->withCurrency($currency)
            ->withIssueDate(new \DateTimeImmutable($issuedOn->toDateString()))
            ->withDueDate(new \DateTimeImmutable($issuedOn->copy()->addDays(14)->toDateString()))
            ->withSeller((string) ($supplier->company ?: $supplier->name), $vatId, $taxNumber !== '' ? $taxNumber : null)
            ->withSellerAddress(trim((string) $supplier->address_street), trim((string) $supplier->address_zip), trim((string) $supplier->address_city), strtoupper(trim((string) $supplier->country) ?: 'DE'))
            ->withBuyer($buyer['name'], $buyer['vat_id'] !== '' ? $buyer['vat_id'] : null)
            ->withBuyerAddress($buyer['street'], $buyer['zip'], $buyer['city'], $buyer['country'])
            ->withBuyerReference((string) $credit->number)
            ->withPaymentMeans(PaymentMeansCode::SEPA_CREDIT_TRANSFER)
            ->addNote((string) __('invoicing.self_billing.einvoice_note', ['date' => $supplier->self_billing_agreed_on?->format('d.m.Y') ?? '—']));

        if ($credit->service_until !== null) {
            $builder->withDeliveryDate(new \DateTimeImmutable($credit->service_until->toDateString()));
        }
        if ($vatId === '' && $taxNumber !== '') {
            $builder->withSellerIdentifier($taxNumber);
        }
        if (trim((string) $supplier->email) !== '') {
            $builder->withSellerEndpoint(trim((string) $supplier->email), 'EM');
        }
        if ($buyer['contact_email'] !== '') {
            $builder->withBuyerEndpoint($buyer['contact_email'], 'EM');
        }
        if (trim((string) $supplier->bank_iban) !== '') {
            $builder->withSellerBankAccount(trim((string) $supplier->bank_iban), trim((string) $supplier->bank_bic) !== '' ? trim((string) $supplier->bank_bic) : null);
        }

        foreach ($credit->lines as $index => $line) {
            $rate = $line->taxRate();
            $percent = $rate !== null ? (float) $rate->getNumericValue() : 0.0;
            $description = trim($line->description);
            $builder->addInvoiceLine(new InvoiceLine(
                id: (string) ($index + 1),
                quantity: (float) $line->lineQuantity(),
                unitCode: UnitCode::fromText((string) $line->unit, UnitCode::UNIT_H87) ?? UnitCode::PIECE,
                netAmount: $line->netAmount(),
                itemName: Str::limit($description, 100, '…'),
                unitPrice: $line->unitPrice()?->withScale(2) ?? Money::zero($currency),
                taxCategory: $percent > 0 ? TaxCategory::STANDARD : TaxCategory::ZERO_RATED,
                taxPercent: $percent,
                itemDescription: mb_strlen($description) > 100 ? $description : null,
            ));
        }

        return $builder->build()->toUblXml();
    }
}
