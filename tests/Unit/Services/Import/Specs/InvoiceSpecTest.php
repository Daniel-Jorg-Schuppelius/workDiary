<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceSpecTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Services\Import\Specs;

use App\Enums\Finance\BillingMode;
use App\Enums\Import\ImportErrorCode;
use App\Models\{Customer, Invoice, Organization};
use App\Services\Import\ImportOutcome;
use App\Services\Import\Specs\InvoiceSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class InvoiceSpecTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'number' => 'K-100',
            'name' => 'Altkunde GmbH',
        ]);
    }

    /** @return array<string, string> */
    private function row(array $overrides = []): array {
        return array_merge([
            'external_number' => 'RE-2024-001',
            'customer_number' => 'K-100',
            'issued_on' => '15.01.2024',
            'due_on' => '29.01.2024',
            'net_amount' => '1.000,00',
            'tax_rate' => '19',
            'gross_amount' => '1.190,00',
            'currency' => 'eur',
            'paid_amount' => '',
            'legacy_source' => 'Lexware',
        ], $overrides);
    }

    public function test_normalize_handles_german_decimals_and_currency(): void {
        $row = (new InvoiceSpec())->normalize($this->row());

        $this->assertSame('1000.00', $row['net_amount']);
        $this->assertSame('1190.00', $row['gross_amount']);
        $this->assertSame('19', $row['tax_rate']);
        $this->assertSame('EUR', $row['currency']);
        $this->assertNull($row['paid_amount']);
    }

    public function test_validate_row_reports_required_customer_amount_and_overpayment(): void {
        $spec = new InvoiceSpec();

        $issues = $spec->validateRow($spec->normalize($this->row([
            'external_number' => '',
            'customer_number' => 'K-999',
            'net_amount' => '',
            'gross_amount' => '',
            'tax_rate' => '',
        ])), $this->organization);
        $codes = array_map(static fn($i) => $i->code, $issues);
        $this->assertContains(ImportErrorCode::Required, $codes);
        $this->assertContains(ImportErrorCode::FkMissing, $codes);

        $issues = $spec->validateRow($spec->normalize($this->row(['paid_amount' => '2000'])), $this->organization);
        $this->assertContains(ImportErrorCode::OutOfRange, array_map(static fn($i) => $i->code, $issues));
    }

    public function test_external_billing_sovereignty_blocks_row(): void {
        $this->customer->update(['billing_mode' => BillingMode::Lexoffice]);
        $spec = new InvoiceSpec();

        $issues = $spec->validateRow($spec->normalize($this->row()), $this->organization);

        $blocked = array_values(array_filter($issues, static fn($i) => $i->code === ImportErrorCode::Blocked));
        $this->assertCount(1, $blocked);
        $this->assertSame('customer_number', $blocked[0]->field);
        $this->assertStringContainsString('Lexoffice', $blocked[0]->message);
    }

    public function test_upsert_creates_frozen_opening_item_with_summary_position(): void {
        $spec = new InvoiceSpec();

        [$outcome, $issue] = $spec->upsert($spec->normalize($this->row()), $this->organization);

        $this->assertSame(ImportOutcome::Created, $outcome, $issue?->message ?? '');
        $this->assertNull($issue);

        $invoice = Invoice::query()->where('external_number', 'RE-2024-001')->firstOrFail();
        $this->assertSame('ALT-RE-2024-001', $invoice->number);
        $this->assertSame(InvoiceSpec::NUMBER_SOURCE, $invoice->number_source);
        $this->assertSame(Invoice::STATUS_ISSUED, $invoice->status);
        $this->assertSame('2024-01-15', $invoice->issued_on?->toDateString());
        $this->assertSame('2024-01-29', $invoice->due_on?->toDateString());
        $this->assertSame('1000.00', $invoice->subtotal?->getAmount());
        $this->assertSame('190.00', $invoice->tax_amount?->getAmount());
        $this->assertSame('1190.00', $invoice->total?->getAmount());
        $this->assertSame('Lexware', data_get($invoice->import_metadata, 'legacy_source'));
        $this->assertSame(1, $invoice->items()->count());
        $this->assertNotNull($invoice->party_snapshot, 'Sofort festgeschrieben (Partei-Snapshot)');

        // GoBD: fachliche Felder sind nach dem Import unveränderlich.
        $this->expectException(\RuntimeException::class);
        $invoice->update(['total' => '1.00']);
    }

    public function test_status_derives_from_paid_amount_and_gross_is_derived_from_net_and_rate(): void {
        $spec = new InvoiceSpec();

        [$o1, $i1] = $spec->upsert($spec->normalize($this->row(['external_number' => 'R-1', 'gross_amount' => '', 'paid_amount' => '500'])), $this->organization);
        [$o2, $i2] = $spec->upsert($spec->normalize($this->row(['external_number' => 'R-2', 'paid_amount' => '1190', 'paid_on' => '2024-02-01'])), $this->organization);
        $this->assertSame(ImportOutcome::Created, $o1, $i1?->message ?? '');
        $this->assertSame(ImportOutcome::Created, $o2, $i2?->message ?? '');

        $partial = Invoice::query()->where('external_number', 'R-1')->firstOrFail();
        $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $partial->status);
        $this->assertSame('1190.00', $partial->total?->getAmount(), 'Brutto aus Netto + Satz');
        $this->assertSame('500.00', data_get($partial->import_metadata, 'paid_amount'));
        $this->assertNull($partial->paid_on);

        $paid = Invoice::query()->where('external_number', 'R-2')->firstOrFail();
        $this->assertSame(Invoice::STATUS_PAID, $paid->status);
        $this->assertSame('2024-02-01', $paid->paid_on?->toDateString());
    }

    public function test_reimport_is_idempotent_and_skips_existing_external_number(): void {
        $spec = new InvoiceSpec();
        [$o1, $i1] = $spec->upsert($spec->normalize($this->row()), $this->organization);
        [$o2] = $spec->upsert($spec->normalize($this->row(['gross_amount' => '5.000,00'])), $this->organization);

        $this->assertSame(ImportOutcome::Created, $o1, $i1?->message ?? '');
        $this->assertSame(ImportOutcome::Skipped, $o2);
        $this->assertSame(1, Invoice::query()->where('organization_id', $this->organization->id)->count());
        $this->assertSame('1190.00', Invoice::query()->firstOrFail()->total?->getAmount(), 'Festgeschriebener Stand bleibt');
    }

    public function test_customer_lookup_is_tenant_scoped(): void {
        $other = Organization::factory()->create();
        Customer::factory()->create(['organization_id' => $other->id, 'number' => 'K-777', 'name' => 'Fremd']);
        $spec = new InvoiceSpec();

        [$outcome, $issue] = $spec->upsert($spec->normalize($this->row(['customer_number' => 'K-777'])), $this->organization);

        $this->assertSame(ImportOutcome::Failed, $outcome);
        $this->assertSame(ImportErrorCode::FkMissing, $issue?->code);
        $this->assertSame(0, Invoice::query()->count());
    }
}
