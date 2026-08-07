<?php
/*
 * Created on   : Fri Aug 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierValueReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Http\Controllers\Reporting\SupplierValueReportController;
use App\Models\{AuditLog, LexofficeVoucher, Supplier, User};
use App\Services\Reporting\SupplierValueReportBuilder;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

class SupplierValueReportTest extends TestCase {
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
            'name' => 'Alpha Bauteile GmbH',
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
        $this->voucher(['total_amount' => '2000.00']);

        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('reports.supplier-value'));

        $response->assertOk();
        $response->assertSee('Lieferantenwert');
        $response->assertSeeText('Alpha Bauteile GmbH');
    }

    public function test_builder_segments_new_supplier_and_computes_share(): void {
        $this->voucher(['total_amount' => '3000.00']);

        $result = app(SupplierValueReportBuilder::class)->build(
            CarbonImmutable::parse('2026-06-01')->startOfDay(),
            CarbonImmutable::parse('2026-06-30')->endOfDay(),
        );

        $this->assertSame(3000.0, $result['concentration']['totalSpend']);
        $this->assertSame(1, $result['segments']['new']);
        $row = $result['rows'][0];
        $this->assertSame('new', $row['segment']);
        $this->assertSame(3000.0, $row['spend']);
        $this->assertSame(100.0, $row['spendShare']);
    }

    public function test_risk_rows_flag_high_dependency_suppliers(): void {
        $beta = Supplier::create(['organization_id' => $this->organization->id, 'name' => 'Beta Klein']);
        // Alpha: 9000 (90 %), Beta: 1000 (10 %).
        $this->voucher(['total_amount' => '9000.00']);
        LexofficeVoucher::create([
            'organization_id' => $this->organization->id,
            'external_id' => 'ext-beta',
            'supplier_id' => $beta->id,
            'voucher_type' => 'purchaseinvoice',
            'voucher_status' => 'open',
            'voucher_date' => '2026-06-10',
            'total_amount' => '1000.00',
            'currency' => CurrencyCode::Euro,
            'archived' => false,
        ]);

        $builder = app(SupplierValueReportBuilder::class);
        $result = $builder->build(
            CarbonImmutable::parse('2026-06-01')->startOfDay(),
            CarbonImmutable::parse('2026-06-30')->endOfDay(),
        );
        $risk = $builder->riskRows($result['rows'], 15.0);

        $this->assertCount(1, $risk);
        $this->assertSame('Alpha Bauteile GmbH', $risk[0]['supplierName']);
    }

    public function test_csv_export_writes_audit_log_entry(): void {
        $this->voucher(['total_amount' => '500.00']);

        $this->actingAs($this->admin)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('reports.supplier-value', ['export' => 'csv']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $log = AuditLog::query()->where('event', 'report.exported')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(SupplierValueReportController::class, $log->auditable_type);
        $this->assertSame('supplier-value', $log->changes['report_code'] ?? null);
    }

    public function test_report_is_forbidden_without_report_permission(): void {
        $this->actingAs($this->orgUser())
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('reports.supplier-value'))
            ->assertForbidden();
    }
}
