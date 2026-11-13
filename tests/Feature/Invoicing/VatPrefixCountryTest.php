<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VatPrefixCountryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceTaxCase, InvoiceStatus};
use App\Models\Customer\Customer;
use App\Models\Document\Document;
use App\Models\Invoicing\{IncomingEInvoice, Invoice};
use App\Services\Accounting\Posting\Adapters\SalesInvoiceAdapter;
use App\Services\Invoicing\EInvoice\IncomingInvoiceTaxCaseResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1686: Das Land einer USt-IdNr. löst php-common-toolkit 2.17 auf
 * (`VatNumberHelper::getCountryForVatPrefix()`), auch die Präfixe, die kein
 * ISO-Code sind — Griechenland führt `EL`, die Schweiz `CHE`.
 */
final class VatPrefixCountryTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_a_greek_supplier_known_only_by_its_vat_id_is_an_eu_reverse_charge(): void {
        $resolver = app(IncomingInvoiceTaxCaseResolver::class);

        $this->assertSame(IncomingInvoiceTaxCase::ReverseChargeEu, $resolver->resolve($this->incoming('EL123456783'))['case']);
        // Gegenprobe: Die Schweiz (CHE) liegt außerhalb der EU, die Steuerschuld des Empfängers gilt dann im Inland.
        $this->assertSame(IncomingInvoiceTaxCase::ReverseCharge, $resolver->resolve($this->incoming('CHE116281710MWST'))['case']);
    }

    public function test_the_vat_id_prefix_decides_whether_a_reverse_charge_sale_is_domestic(): void {
        $adapter = app(SalesInvoiceAdapter::class);

        $greek = $this->invoice(['vat_id' => 'EL123456783', 'country' => null]);
        $this->assertSame(['category' => 'AE', 'domestic' => false], $adapter->zeroRatedCategory($this->organization, $greek));

        // Die USt-IdNr. geht der Anschrift vor; ein Präfix, der keine USt-IdNr. kennzeichnet, fällt auf die Anschrift zurück.
        $german = $this->invoice(['vat_id' => 'DE136695976', 'country' => 'AT']);
        $this->assertSame(['category' => 'AE', 'domestic' => true], $adapter->zeroRatedCategory($this->organization, $german));
        $unknownPrefix = $this->invoice(['vat_id' => 'US123456789', 'country' => 'DE']);
        $this->assertSame(['category' => 'AE', 'domestic' => true], $adapter->zeroRatedCategory($this->organization, $unknownPrefix));
    }

    private function incoming(string $sellerVatId): IncomingEInvoice {
        return IncomingEInvoice::query()->create([
            'organization_id' => $this->organization->id,
            'document_id' => Document::factory()->create(['organization_id' => $this->organization->id])->id,
            'sha256' => hash('sha256', uniqid('vat-prefix', true)),
            'source' => 'upload',
            'received_at' => now(),
            'status' => IncomingEInvoiceStatus::Approved,
            'invoice_number' => 'ER-' . fake()->unique()->numberBetween(1000, 9999),
            'seller_name' => 'Beispiel Lieferant',
            'seller_vat_id' => $sellerVatId,
            'issue_date' => '2026-02-12',
            'currency' => 'EUR',
            'amount_net' => '1000.00',
            'amount_tax' => '0.00',
            'amount_gross' => '1000.00',
            'summary' => ['tax_breakdown' => [['category' => 'AE', 'percent' => 0.0, 'net' => '1000.00', 'tax' => '0.00']]],
        ]);
    }

    /** @param array<string, mixed> $customer */
    private function invoice(array $customer): Invoice {
        return Invoice::query()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => Customer::factory()->create(['organization_id' => $this->organization->id] + $customer)->id,
            'number' => 'RE-' . fake()->unique()->numberBetween(1000, 9999),
            'status' => InvoiceStatus::Issued,
            'type' => Invoice::TYPE_INVOICE,
            'category' => Invoice::CATEGORY_SERVICE,
            'issued_on' => '2026-02-20',
            'currency' => 'EUR',
            'subtotal' => '500.00',
            'tax_amount' => '0.00',
            'total' => '500.00',
            'is_reverse_charge' => true,
            'tax_breakdown' => [['rate' => '0.00', 'net' => '500.00', 'tax' => '0.00']],
            'tax_context' => ['category' => 'AE'],
        ])->refresh();
    }
}
