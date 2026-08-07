<?php
/*
 * Created on   : Fri Aug 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierAnalysisReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Http\Controllers\Reporting\SupplierAnalysisReportController;
use App\Models\{AuditLog, LexofficeVoucher, Supplier, User};
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

class SupplierAnalysisReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    private Supplier $supplier;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();

        $this->supplier = Supplier::create([
            'organization_id' => $this->organization->id,
            'name' => 'Musterlieferant GmbH',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function voucher(array $attributes): LexofficeVoucher {
        return LexofficeVoucher::create(array_merge([
            'organization_id' => $this->organization->id,
            'external_id' => 'ext-' . uniqid(),
            'supplier_id' => $this->supplier->id,
            'voucher_type' => 'purchaseinvoice',
            'voucher_status' => 'open',
            'voucher_date' => '2026-06-15',
            'currency' => CurrencyCode::Euro,
            'archived' => false,
        ], $attributes));
    }

    public function test_route_renders_for_admin(): void {
        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('reports.suppliers'));

        $response->assertOk();
        $response->assertSee('Lieferantenanalyse');
    }

    public function test_report_shows_supplier_spend_from_vouchers(): void {
        $this->voucher(['total_amount' => '1200.00', 'open_amount' => '1200.00']);

        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('reports.suppliers'));

        $response->assertOk();
        $response->assertSeeText('Musterlieferant GmbH');
        // Gesamtausgaben-KPI zeigt den Belegbetrag (deutsches Format).
        $response->assertSeeText('1.200,00 €');
    }

    public function test_credit_note_reduces_spend(): void {
        $this->voucher(['total_amount' => '1000.00', 'open_amount' => '0.00']);
        $this->voucher([
            'voucher_type' => 'purchasecreditnote',
            'voucher_status' => 'paid',
            'total_amount' => '250.00',
            'open_amount' => '0.00',
        ]);

        $result = app(\App\Services\Reporting\SupplierAnalysisReportBuilder::class)->build(
            \Carbon\CarbonImmutable::parse('2026-06-01')->startOfDay(),
            \Carbon\CarbonImmutable::parse('2026-06-30')->endOfDay(),
            false,
        );

        $this->assertSame(750.0, $result['concentration']['totalSpend']);
        $this->assertSame(750.0, $result['rows'][0]['spend']);
        $this->assertSame(2, $result['rows'][0]['voucherCount']);
    }

    public function test_draft_and_voided_vouchers_are_ignored(): void {
        $this->voucher(['voucher_status' => 'draft', 'total_amount' => '999.00', 'open_amount' => '999.00']);
        $this->voucher(['voucher_status' => 'voided', 'total_amount' => '888.00', 'open_amount' => '0.00']);

        $result = app(\App\Services\Reporting\SupplierAnalysisReportBuilder::class)->build(
            \Carbon\CarbonImmutable::parse('2026-06-01')->startOfDay(),
            \Carbon\CarbonImmutable::parse('2026-06-30')->endOfDay(),
            false,
        );

        $this->assertSame([], $result['rows']);
        $this->assertSame(0.0, $result['concentration']['totalSpend']);
    }

    public function test_csv_export_writes_audit_log_entry(): void {
        $this->voucher(['total_amount' => '500.00', 'open_amount' => '500.00']);

        $this->actingAs($this->admin)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('reports.suppliers', ['export' => 'csv']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $log = AuditLog::query()->where('event', 'report.exported')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(SupplierAnalysisReportController::class, $log->auditable_type);
        $this->assertSame('supplier-analysis', $log->changes['report_code'] ?? null);
        $this->assertSame('csv', $log->changes['format'] ?? null);
    }

    public function test_report_is_forbidden_without_report_permission(): void {
        $this->actingAs($this->orgUser())
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('reports.suppliers'))
            ->assertForbidden();
    }
}
