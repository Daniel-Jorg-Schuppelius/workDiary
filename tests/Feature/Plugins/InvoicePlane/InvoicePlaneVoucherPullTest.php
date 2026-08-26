<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicePlaneVoucherPullTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\InvoicePlane;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Models\Finance\AccountingVoucher;
use App\Plugins\InvoicePlane\Schema\{NullVoucherReaderFactory, VoucherReader, VoucherReaderFactory};
use App\Plugins\InvoicePlane\Services\InvoicePlaneVoucherPullService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-731 (Vollscan G18): Beleg-Rückabruf aus InvoicePlane.
 *
 * InvoicePlane 1.x hat keine REST-API (Feature 086: v2 blockiert) — die
 * Anbindung ist eine schreibgeschützte Sicht auf das `ip_*`-Schema. Der Test
 * spielt aufgezeichnete Zeilen über einen Array-Reader ein; ohne freigegebene
 * Verbindung gibt es gar keinen Reader und damit keinen erfundenen Beleg.
 */
final class InvoicePlaneVoucherPullTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    /** @return list<array<string, mixed>> */
    private function rows(): array {
        /** @var array{rows: list<array<string, mixed>>} $data */
        $data = json_decode((string) file_get_contents(base_path('tests/Fixtures/Plugins/InvoicePlane/invoices.json')), true);

        return $data['rows'];
    }

    /** @param list<array<string, mixed>> $rows */
    private function bindReader(array $rows): void {
        $reader = new class($rows) implements VoucherReader {
            /** @param list<array<string, mixed>> $rows */
            public function __construct(private readonly array $rows) {}

            /** @return list<array<string, mixed>> */
            public function invoicesSince(?string $sinceDate, int $offset, int $limit): array {
                $rows = $sinceDate === null
                    ? $this->rows
                    : array_values(array_filter(
                        $this->rows,
                        static fn (array $row): bool => substr((string) $row['invoice_date_modified'], 0, 10) >= $sinceDate,
                    ));

                return array_slice($rows, $offset, $limit);
            }
        };

        $factory = new class($reader) implements VoucherReaderFactory {
            public function __construct(private readonly VoucherReader $reader) {}

            public function for(int $organizationId): ?VoucherReader {
                return $this->reader;
            }
        };

        $this->app->instance(VoucherReaderFactory::class, $factory);
    }

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_maps_invoices_and_credit_notes_from_the_ip_schema(): void {
        $this->bindReader($this->rows());

        $result = app(InvoicePlaneVoucherPullService::class)->pull((int) $this->organization->id);

        $this->assertSame(['read' => 2, 'created' => 2, 'updated' => 0, 'skipped' => 0], $result);

        $invoice = AccountingVoucher::query()->where('external_id', '12')->firstOrFail();
        $this->assertSame('invoiceplane', $invoice->plugin_id);
        $this->assertSame(DocumentDirection::Outgoing->value, $invoice->direction);
        $this->assertSame(DocumentKind::Invoice->value, $invoice->document_kind);
        // Status 2 (versendet) ist offen — „überfällig" wäre es auch.
        $this->assertSame('open', $invoice->voucher_state);
        $this->assertSame('119.00', (string) $invoice->total_amount);
        $this->assertSame('119.00', (string) $invoice->open_amount);

        // Aufgehoben wird in InvoicePlane über eine Gutschrift mit Elternbezug.
        $credit = AccountingVoucher::query()->where('external_id', '13')->firstOrFail();
        $this->assertSame(DocumentKind::CreditNote->value, $credit->document_kind);
        $this->assertSame('12', $credit->cancels_external_id);
        $this->assertFalse($credit->is_cancellation);
        $this->assertSame('paid', $credit->voucher_state);
    }

    public function test_second_run_reads_incrementally_and_updates_instead_of_duplicating(): void {
        $this->bindReader($this->rows());
        app(InvoicePlaneVoucherPullService::class)->pull((int) $this->organization->id);

        $result = app(InvoicePlaneVoucherPullService::class)->pull((int) $this->organization->id);

        // Ab dem jüngsten bekannten Änderungsstand — die ältere Zeile fällt raus.
        $this->assertSame(1, $result['read']);
        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(2, AccountingVoucher::query()->count());
    }

    public function test_without_a_reader_nothing_is_mirrored(): void {
        $this->app->instance(VoucherReaderFactory::class, new NullVoucherReaderFactory());

        $service = app(InvoicePlaneVoucherPullService::class);

        $this->assertFalse($service->isConfigured((int) $this->organization->id));
        $this->assertSame(0, $service->pull((int) $this->organization->id)['read']);
        $this->assertSame(0, AccountingVoucher::query()->count());
    }
}
