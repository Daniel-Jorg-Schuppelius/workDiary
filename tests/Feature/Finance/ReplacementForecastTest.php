<?php
/*
 * Created on   : Sat Sep 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReplacementForecastTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Finance\DepreciationMethod;
use App\Models\AssetFinance\AssetFinanceContract;
use App\Models\Platform\{Organization, User};
use App\Services\Accounting\FixedAssetService;
use App\Settings\SettingScope;
use App\Support\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MVP-908: Restwert- und Ersatzprognose für Anlagen und Leasingverträge. */
final class ReplacementForecastTest extends TestCase {
    use RefreshDatabase;

    public function test_forecast_lists_expiring_assets_with_book_value_and_indexed_replacement(): void {
        CarbonImmutable::setTestNow('2026-06-30 09:00:00');
        $org = Organization::factory()->create();
        app()->instance('currentOrganization', $org);
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
        Setting::set('finance.fixed_assets.replacement_inflation_pct', 2, SettingScope::Organization, $org);
        app()->instance('currentOrganization', $org->fresh());

        $service = app(FixedAssetService::class);
        $base = ['residual_value' => '0.00', 'depreciation_method' => DepreciationMethod::Linear->value];
        $service->create($org, $admin, $base + ['name' => 'Laptop', 'acquired_on' => '2024-01-01', 'acquisition_cost' => '10000.00', 'useful_life_months' => 36]);
        $service->create($org, $admin, $base + ['name' => 'Stapler', 'acquired_on' => '2020-01-01', 'acquisition_cost' => '20000.00', 'useful_life_months' => 60]);
        $service->create($org, $admin, $base + ['name' => 'Halle', 'acquired_on' => '2024-01-01', 'acquisition_cost' => '500000.00', 'useful_life_months' => 396]);
        AssetFinanceContract::query()->create(['organization_id' => $org->id, 'number' => 'LEA-1', 'kind' => 'operating_lease', 'status' => 'active', 'partner_name' => 'Auto-Leasing', 'starts_on' => '2024-04-01', 'ends_on' => '2027-03-31', 'payment_rhythm' => 'monthly', 'currency' => 'EUR', 'residual_value' => '5000.00']);

        $response = $this->actingAs($admin)->get(route('reports.accounting.replacement-forecast', ['years' => 3]));
        $response->assertOk()->assertSee('Laptop')->assertSee('Stapler')->assertDontSee('Halle')->assertSee('LEA-1');

        $assets = collect($response->viewData('assets'))->keyBy(fn (array $row): string => $row['asset']->name);
        $this->assertSame(['3333.34', '10612.08', false], [$assets['Laptop']['book_value'], $assets['Laptop']['replacement'], $assets['Laptop']['overdue']]);
        $this->assertTrue($assets['Stapler']['overdue']);
        $this->assertSame('22081.62', $assets['Stapler']['replacement'], '20.000 × 1,02^5');
        $this->assertSame(['2026' => '32693.70'], array_map('strval', $response->viewData('years')));
        $this->actingAs($admin)->get(route('reports.accounting.index'))->assertSee(route('reports.accounting.replacement-forecast'), false);
    }
}
