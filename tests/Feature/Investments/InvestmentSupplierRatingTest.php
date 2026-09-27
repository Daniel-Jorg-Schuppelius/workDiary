<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentSupplierRatingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Investments;

use App\Models\Investments\{InvestmentCase, InvestmentOption, InvestmentSupplierRating};
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-928: Lieferantenbewertung über Investitionen. */
final class InvestmentSupplierRatingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private InvestmentCase $case;

    private Supplier $alpha;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->alpha = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Alpha Maschinen']);
        $this->case = InvestmentCase::query()->create(['organization_id' => $this->organization->id, 'title' => 'Presse', 'category' => 'machine', 'status' => 'comparison', 'created_by' => $this->admin->id]);
        InvestmentOption::query()->create(['organization_id' => $this->organization->id, 'investment_case_id' => $this->case->id, 'title' => 'Presse A', 'supplier_id' => $this->alpha->id, 'one_time_cost' => '1000', 'recurring_cost_yearly' => '0']);
    }

    private function rate(Supplier $supplier): \Illuminate\Testing\TestResponse {
        return $this->actingAs($this->admin)->post(route('investments.supplier-ratings.store', $this->case), ['supplier_id' => $supplier->sqid, 'schedule_score' => 4, 'cost_score' => 2, 'quality_score' => 5, 'note' => 'pünktlich']);
    }

    public function test_suppliers_are_rated_after_implementation_and_averaged(): void {
        $this->rate($this->alpha)->assertSessionHas('error', __('investment.supplier_rating.error.not_rateable'));
        $this->case->update(['status' => 'completed']);

        $this->actingAs($this->admin)->get(route('investments.show', $this->case))->assertOk()->assertSee(__('investment.supplier_rating.title'));
        $this->rate($this->alpha)->assertSessionHas('success');
        $this->rate($this->alpha)->assertSessionHas('success');
        $this->assertSame(1, InvestmentSupplierRating::query()->count());

        $foreign = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Beta']);
        $this->rate($foreign)->assertSessionHas('error', __('investment.supplier_rating.error.not_supplier'));

        $this->actingAs($this->admin)->get(route('investments.supplier-ratings.index'))->assertOk()->assertSee('Alpha Maschinen')->assertSee('3,7');
    }
}
