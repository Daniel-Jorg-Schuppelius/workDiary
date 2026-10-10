<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierReportExclusionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Claims\{ClaimSource, ClaimStatus};
use App\Models\Claims\ClaimCase;
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Plugins\Lexoffice\Models\{LexofficePostingCategory, LexofficeVoucher, LexofficeVoucherCategory};
use App\Services\Procurement\SupplierScorecardService;
use App\Services\Reporting\SupplierAnalysisReportBuilder;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * MVP-1121: Lieferanten mit `exclude_from_reports` (Finanzamt, Krankenkasse …)
 * fehlen in Lieferantenwert, Lieferantenanalyse und Scorecard-Ranking —
 * Tabellen, Summen, Trend und Buchungskategorien; `include_excluded` nimmt
 * sie wieder auf, die Lieferantenakte zeigt sie unverändert.
 */
class SupplierReportExclusionTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    private Supplier $vendor;

    private Supplier $taxOffice;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();

        $this->vendor = Supplier::create(['organization_id' => $this->organization->id, 'name' => 'Alpha Bauteile GmbH']);
        $this->taxOffice = Supplier::create([
            'organization_id' => $this->organization->id,
            'name' => 'Finanzamt Musterstadt',
            'exclude_from_reports' => true,
        ]);

        $this->voucher($this->vendor, '1000.00', 'Wareneingang');
        $this->voucher($this->taxOffice, '5000.00', 'Umsatzsteuervorauszahlung');
    }

    private function voucher(Supplier $supplier, string $amount, string $category): void {
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id,
            'external_id' => 'ext-' . $supplier->id,
            'supplier_id' => $supplier->id,
            'voucher_type' => 'purchaseinvoice',
            'voucher_status' => 'paid',
            'voucher_date' => '2026-06-15',
            'total_amount' => $amount,
            'currency' => CurrencyCode::Euro,
            'archived' => false,
        ]);
        LexofficePostingCategory::create([
            'organization_id' => $this->organization->id,
            'external_id' => 'cat-' . $supplier->id,
            'name' => $category,
            'kind' => 'outgo',
        ]);
        LexofficeVoucherCategory::create([
            'organization_id' => $this->organization->id,
            'currency' => 'EUR',
            'voucher_id' => $voucher->id,
            'position' => 1,
            'category_external_id' => 'cat-' . $supplier->id,
            'net_amount' => $amount,
        ]);
    }

    /**
     * @param  array<string, int>  $query
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function report(string $route, array $query = []): TestResponse {
        return $this->actingAs($this->admin)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route($route, $query));
    }

    public function test_supplier_value_leaves_out_excluded_supplier(): void {
        $response = $this->report('reports.supplier-value');

        $response->assertOk();
        $response->assertSeeText('Alpha Bauteile GmbH');
        $response->assertDontSeeText('Finanzamt Musterstadt');
        $this->assertSame(1000.0, $response->viewData('concentration')['totalSpend']);
        $this->assertSame([100.0], array_column($response->viewData('rows')->all(), 'spendShare'));
        $response->assertSeeText('Ausgeblendete Lieferanten einbeziehen');
    }

    public function test_supplier_value_includes_excluded_supplier_on_request(): void {
        $response = $this->report('reports.supplier-value', ['include_excluded' => 1]);

        $response->assertOk();
        $response->assertSeeText('Finanzamt Musterstadt');
        $this->assertSame(6000.0, $response->viewData('concentration')['totalSpend']);
    }

    public function test_toggle_is_hidden_without_excluded_suppliers(): void {
        $this->taxOffice->update(['exclude_from_reports' => false]);

        $this->report('reports.supplier-value')
            ->assertOk()
            ->assertDontSeeText('Ausgeblendete Lieferanten einbeziehen');
    }

    public function test_supplier_analysis_leaves_out_excluded_supplier_in_rows_trend_and_categories(): void {
        $response = $this->report('reports.suppliers');

        $response->assertOk();
        $this->assertSame(['Alpha Bauteile GmbH'], array_column($response->viewData('rows')->all(), 'supplierName'));
        $this->assertSame(1000.0, array_sum(array_column($response->viewData('monthlySpendSeries'), 'y')));
        $this->assertSame(['Wareneingang'], array_column($response->viewData('categorySpend')['bands'], 'label'));

        $all = $this->report('reports.suppliers', ['include_excluded' => 1]);
        $this->assertSame(6000.0, array_sum(array_column($all->viewData('monthlySpendSeries'), 'y')));
        $this->assertContains('Umsatzsteuervorauszahlung', array_column($all->viewData('categorySpend')['bands'], 'label'));
    }

    public function test_supplier_page_series_keep_excluded_supplier(): void {
        $series = app(SupplierAnalysisReportBuilder::class)->supplierMonthlySpendSeries(
            $this->taxOffice->id,
            CarbonImmutable::parse('2026-06-01')->startOfDay(),
            CarbonImmutable::parse('2026-06-30')->endOfDay(),
            'month',
        );

        $this->assertSame(5000.0, array_sum(array_column($series, 'y')));
    }

    public function test_scorecard_ranking_leaves_out_excluded_supplier(): void {
        foreach ([$this->vendor, $this->taxOffice] as $index => $supplier) {
            ClaimCase::create([
                'organization_id' => $this->organization->id,
                'number' => 'RK-000' . $index,
                'status' => ClaimStatus::Received->value,
                'source' => ClaimSource::Internal->value,
                'priority' => 'normal', 'severity' => 'minor', 'title' => 'Transportschaden',
                'supplier_id' => $supplier->id,
                'reported_at' => CarbonImmutable::parse('2026-06-12'),
            ]);
        }
        $service = app(SupplierScorecardService::class);
        $from = CarbonImmutable::parse('2026-06-01')->startOfDay();
        $to = CarbonImmutable::parse('2026-06-30')->endOfDay();

        $this->assertSame(['Alpha Bauteile GmbH'], array_column($service->ranking($from, $to, Supplier::excludedFromReportsIds()), 'supplier_name'));
        $this->assertCount(2, $service->ranking($from, $to));
    }

    public function test_supplier_form_saves_and_clears_flag(): void {
        $payload = ['name' => 'Alpha Bauteile GmbH', 'currency' => 'EUR', 'exclude_from_reports' => '1'];

        $this->actingAs($this->admin)->put(route('suppliers.update', $this->vendor), $payload)->assertRedirect();
        $this->assertTrue($this->vendor->refresh()->exclude_from_reports);

        $this->actingAs($this->admin)->put(route('suppliers.update', $this->vendor), ['exclude_from_reports' => '0'] + $payload)->assertRedirect();
        $this->assertFalse($this->vendor->refresh()->exclude_from_reports);
    }
}
