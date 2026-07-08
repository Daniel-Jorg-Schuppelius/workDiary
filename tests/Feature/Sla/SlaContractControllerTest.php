<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaContractControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Sla;

use App\Enums\User\Permission;
use App\Models\{Asset, MaintenancePlan, Organization, SlaContract, SlaContractQuota, User};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 010: read-only SLA-Vertrags-Detailseite. Trägerseite für die
 * Kontingent-Anzeige (Rang 44) und die vertragspflichtigen Wartungen (Rang 43).
 * Prüft Auflistung, Detail-Rendering, Mandantengrenze und das Recht
 * `slaContract.view`.
 */
class SlaContractControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $viewer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->viewer = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->viewer->givePermissionTo(Permission::SlaContractView->value);
    }

    private function contract(array $attributes = []): SlaContract {
        return SlaContract::factory()->create($attributes + [
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);
    }

    public function test_index_lists_organization_contracts(): void {
        $this->contract(['code' => 'SLA-GOLD', 'label' => 'Gold-SLA']);

        $this->actingAs($this->viewer)->get(route('sla-contracts.index'))
            ->assertOk()
            ->assertSee('SLA-GOLD')
            ->assertSee('Gold-SLA');
    }

    public function test_show_renders_quota_and_contractual_maintenance(): void {
        $contract = $this->contract(['code' => 'SLA-1', 'label' => 'Vertrag 1']);
        SlaContractQuota::query()->create([
            'organization_id' => $this->organization->id,
            'sla_contract_id' => $contract->id,
            'period_kind' => 'month',
            'included_minutes' => 600,
            'warn_threshold_pct' => 80,
        ]);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        MaintenancePlan::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $asset->id,
            'sla_contract_id' => $contract->id,
            'is_contractual' => true,
            'due_action' => 'ticket',
            'label' => 'Jährliche Prüfung',
            'next_due_on' => '2026-07-01',
        ]);

        $response = $this->actingAs($this->viewer)->get(route('sla-contracts.show', $contract));

        $response->assertOk();
        $this->assertCount(1, $response->viewData('quotaUsage'));
        $response->assertSee('Jährliche Prüfung');
        $response->assertSee(__('Vertragspflicht'));
        $response->assertSee(__('Inklusivzeit-Kontingente'));
    }

    public function test_foreign_contract_is_not_found(): void {
        $otherOrg = Organization::factory()->create();
        $foreign = SlaContract::factory()->create(['organization_id' => $otherOrg->id]);

        $this->actingAs($this->viewer)->get(route('sla-contracts.show', $foreign))->assertNotFound();
    }

    public function test_requires_view_permission(): void {
        $plain = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($plain)->get(route('sla-contracts.index'))->assertForbidden();
    }
}
