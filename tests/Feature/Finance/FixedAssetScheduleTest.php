<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FixedAssetScheduleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Platform\{Organization, User};
use App\Services\Accounting\FixedAssetService;
use App\Services\Accounting\Reports\FixedAssetScheduleBuilder;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MVP-890: Anlagenspiegel aus dem AfA-Plan. */
class FixedAssetScheduleTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);

        $service = app(FixedAssetService::class);
        $service->create($this->org, $this->admin, ['name' => 'Transporter', 'acquired_on' => '2025-01-15', 'acquisition_cost' => '12000.00', 'residual_value' => '0.00', 'useful_life_months' => 60]);
        $press = $service->create($this->org, $this->admin, ['name' => 'Presse', 'acquired_on' => '2026-03-10', 'acquisition_cost' => '6000.00', 'residual_value' => '0.00', 'useful_life_months' => 60]);
        $service->dispose($press, CarbonImmutable::parse('2026-09-30'), $this->admin);
        $service->create($this->org, $this->admin, ['name' => 'Künftig', 'acquired_on' => '2027-02-01', 'acquisition_cost' => '100.00', 'residual_value' => '0.00', 'useful_life_months' => 12]);
    }

    public function test_schedule_develops_cost_and_depreciation_for_the_fiscal_year(): void {
        $data = app(FixedAssetScheduleBuilder::class)->build($this->org, 2026, 1, CurrencyCode::Euro);

        $this->assertSame(['Transporter', 'Presse'], array_map(static fn (array $row): string => $row['asset']->name, $data['rows']));
        $totals = array_map(static fn ($money): string => $money->getAmount(), $data['totals']);
        $this->assertSame([
            'cost_start' => '12000.00', 'additions' => '6000.00', 'disposals' => '6000.00', 'cost_end' => '12000.00',
            'dep_start' => '2400.00', 'dep_year' => '3100.00', 'dep_disposals' => '700.00', 'dep_end' => '4800.00',
            'book_start' => '9600.00', 'book_end' => '7200.00',
        ], $totals);
    }

    public function test_report_page_and_csv_export(): void {
        $this->actingAs($this->admin)
            ->get(route('reports.accounting.fixed-asset-schedule', ['year' => 2026]))
            ->assertOk()
            ->assertSee('Transporter')
            ->assertSee(__('accounting.reports.fixed_asset_schedule.dep_disposals'));

        $this->actingAs($this->admin)
            ->get(route('reports.accounting.fixed-asset-schedule', ['year' => 2026, 'export' => 'csv']))
            ->assertOk()
            ->assertSee('7200.00', false);
    }
}
