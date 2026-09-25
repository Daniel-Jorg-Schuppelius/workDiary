<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LowValueAssetTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Finance\{DepreciationMethod, FixedAssetDisposalKind};
use App\Models\Platform\{Organization, User};
use App\Services\Accounting\{DepreciationCalculator, FixedAssetService};
use App\Services\Accounting\Posting\Adapters\AssetDisposalAdapter;
use App\Services\Accounting\Reports\FixedAssetScheduleBuilder;
use App\Settings\SettingScope;
use App\Support\Setting;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** MVP-892: GWG-Sofortabschreibung und Sammelposten. */
class LowValueAssetTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function asset(array $attributes): \App\Models\Accounting\FixedAsset {
        return app(FixedAssetService::class)->create($this->org, $this->admin, $attributes + ['acquired_on' => '2026-08-15', 'residual_value' => '0.00', 'useful_life_months' => 36]);
    }

    /** @return list<array{int, string}> */
    private function plan(\App\Models\Accounting\FixedAsset $asset): array {
        return array_map(static fn ($row): array => [$row->fiscalYear, $row->amount->getAmount()], app(DepreciationCalculator::class)->scheduleFor($asset));
    }

    public function test_low_value_asset_is_written_off_in_the_year_of_acquisition(): void {
        $drill = $this->asset(['name' => 'Akkuschrauber', 'acquisition_cost' => '420.00', 'depreciation_method' => DepreciationMethod::Immediate->value]);

        $this->assertSame([[2026, '420.00']], $this->plan($drill));

        $this->expectException(ValidationException::class);
        $this->asset(['name' => 'Laptop', 'acquisition_cost' => '1200.00', 'depreciation_method' => DepreciationMethod::Immediate->value]);
    }

    public function test_pool_uses_equal_yearly_rates_and_survives_a_disposal(): void {
        $monitor = $this->asset(['name' => 'Monitor', 'acquisition_cost' => '600.00', 'depreciation_method' => DepreciationMethod::Pool->value]);
        $this->assertSame(60, $monitor->useful_life_months);
        $this->assertSame([[2026, '120.00'], [2027, '120.00'], [2028, '120.00'], [2029, '120.00'], [2030, '120.00']], $this->plan($monitor));

        $monitor = app(FixedAssetService::class)->dispose($monitor, CarbonImmutable::parse('2027-03-01'), $this->admin, null, FixedAssetDisposalKind::Scrap);
        $this->assertCount(5, $this->plan($monitor), 'Abgang beendet den Sammelposten nicht');
        $this->assertTrue(app(AssetDisposalAdapter::class)->candidates($this->org, CarbonImmutable::parse('2027-01-01'), CarbonImmutable::parse('2027-12-31'))->isEmpty());

        $builder = app(FixedAssetScheduleBuilder::class);
        $this->assertSame('600.00', $builder->build($this->org, 2027, 1, CurrencyCode::Euro)['totals']['cost_end']->getAmount());
        $final = $builder->build($this->org, 2030, 1, CurrencyCode::Euro)['totals'];
        $this->assertSame(['600.00', '0.00', '0.00'], [$final['disposals']->getAmount(), $final['cost_end']->getAmount(), $final['dep_end']->getAmount()]);
        $this->assertSame([], $builder->build($this->org, 2031, 1, CurrencyCode::Euro)['rows']);
    }

    public function test_pool_limits_and_term_come_from_organisation_settings(): void {
        Setting::set('finance.fixed_assets.pool_upper', 2000, SettingScope::Organization, $this->org);
        Setting::set('finance.fixed_assets.pool_years', 4, SettingScope::Organization, $this->org);
        app()->instance('currentOrganization', $this->org->fresh());

        $printer = $this->asset(['name' => 'Drucker', 'acquisition_cost' => '1500.00', 'depreciation_method' => DepreciationMethod::Pool->value]);
        $this->assertSame(48, $printer->useful_life_months);

        $this->expectException(ValidationException::class);
        $this->asset(['name' => 'Maus', 'acquisition_cost' => '30.00', 'depreciation_method' => DepreciationMethod::Pool->value]);
    }
}
