<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceClassificationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\AssetFinance;

use App\Enums\AssetFinance\AssetFinanceKind;
use App\Models\Asset\Asset;
use App\Models\AssetFinance\AssetFinanceContract;
use App\Models\Platform\User;
use App\Services\AssetFinance\{AssetFinanceClassificationService, AssetFinanceService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-947: IFRS-16-/HGB-Einschätzung am Leasingvertrag. */
final class AssetFinanceClassificationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $overrides */
    private function contract(int $months, array $overrides = []): AssetFinanceContract {
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);

        return app(AssetFinanceService::class)->create($this->organization, $this->admin, array_merge([
            'kind' => AssetFinanceKind::OperatingLease->value,
            'partner_name' => 'Muster-Leasing GmbH',
            'starts_on' => '2026-01-01',
            'ends_on' => \Carbon\CarbonImmutable::parse('2026-01-01')->addMonths($months)->toDateString(),
            'payment_rhythm' => 'monthly',
            'rate_amount' => '400.00',
            'residual_value' => '5000.00',
        ], $overrides), [$asset->id]);
    }

    public function test_assessment_rules(): void {
        $service = app(AssetFinanceClassificationService::class);

        $short = $this->contract(12);
        $this->assertSame('short_term', $service->assess($short)['ifrs16']);

        $within = $this->contract(48);
        $within->useful_life_months = 72;
        $within->asset_value_amount = '80000.00';
        $result = $service->assess($within);
        $this->assertSame(['right_of_use', 'lessor', ['term_within']], [$result['ifrs16'], $result['hgb'], $result['reasons']]);

        $within->useful_life_months = 50;
        $this->assertSame(['term_above_90'], $service->assess($within)['reasons']);

        $bargain = $this->contract(48, ['purchase_option_amount' => '1000.00']);
        $bargain->useful_life_months = 72;
        $this->assertSame(['lessee', ['bargain_option']], array_values(array_intersect_key($service->assess($bargain), ['hgb' => 1, 'reasons' => 1])));

        $cheap = $this->contract(36);
        $cheap->asset_value_amount = '4500.00';
        $this->assertSame('low_value', $service->assess($cheap)['ifrs16']);
        $this->assertSame('unknown', $service->assess($cheap)['hgb']);
    }

    public function test_saving_stores_snapshot_and_requires_finance_permission(): void {
        $contract = $this->contract(48);

        $this->actingAs($this->admin)->put(route('asset-finance.classification.update', $contract), ['useful_life_months' => 72, 'asset_value_amount' => '80000', 'is_special_lease' => '1'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $fresh = $contract->fresh();
        $this->assertTrue($fresh->is_special_lease);
        $this->assertSame('lessee', $fresh->classification_snapshot['hgb']);
        $this->assertSame(['special_lease'], $fresh->classification_snapshot['reasons']);

        $member = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($member)->put(route('asset-finance.classification.update', $contract), ['useful_life_months' => 10])->assertForbidden();
        $this->actingAs($this->admin)->get(route('asset-finance.show', $contract))->assertOk()->assertSeeText(__('asset_finance.classification.title'));
    }
}
