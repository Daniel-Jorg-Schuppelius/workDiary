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
use App\Models\Asset\{Asset, MaintenancePlan};
use App\Models\Platform\{Organization, User};
use App\Models\ServiceTicket\{SlaContract, SlaContractQuota};
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

    /** Feature 010 (MVP-1620): Kontingente mit Pauschale und Mehrstundensatz sind erfassbar und sichtbar. */
    public function test_manager_maintains_quotas_with_flat_fee_and_overage_rate(): void {
        $manager = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $manager->givePermissionTo([Permission::SlaContractView->value, Permission::SlaContractManage->value]);
        $contract = $this->contract(['code' => 'SLA-2', 'label' => 'Vertrag 2']);

        $this->actingAs($manager)->get(route('sla-contracts.show', $contract))->assertOk()
            ->assertSee(route('sla-contracts.quotas.create', $contract), false);
        $this->actingAs($manager)->get(route('sla-contracts.quotas.create', $contract))->assertOk()
            ->assertSee('name="flat_fee"', false)
            ->assertSee('name="overage_rate"', false);

        $this->actingAs($manager)->post(route('sla-contracts.quotas.store', $contract), [
            'period_kind' => 'month', 'included_hours' => '10', 'warn_threshold_pct' => '75', 'flat_fee' => '150', 'overage_rate' => '80',
        ])->assertSessionHasNoErrors()->assertRedirect(route('sla-contracts.show', $contract));

        $quota = SlaContractQuota::query()->where('sla_contract_id', $contract->id)->sole();
        $this->assertSame(600, $quota->included_minutes);
        $this->assertSame(75, $quota->warn_threshold_pct);
        $this->assertSame('150.00', (string) $quota->flat_fee);
        $this->assertSame('80.00', (string) $quota->overage_rate);

        $this->actingAs($manager)->post(route('sla-contracts.quotas.store', $contract), [
            'period_kind' => 'month', 'included_hours' => '5', 'warn_threshold_pct' => '80',
        ])->assertSessionHasErrors('period_kind');

        $this->actingAs($manager)->patch(route('sla-contracts.quotas.update', [$contract, $quota]), [
            'period_kind' => 'quarter', 'included_hours' => '30', 'warn_threshold_pct' => '90', 'flat_fee' => '', 'overage_rate' => '95.50',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $quota->refresh();
        $this->assertSame('quarter', $quota->period_kind->value);
        $this->assertNull($quota->flat_fee);

        $this->actingAs($this->viewer)->get(route('sla-contracts.show', $contract))->assertOk()
            ->assertSee(__('sla.quota.overage_line', ['rate' => '95,50 €', 'amount' => '0,00 €']))
            ->assertDontSee(route('sla-contracts.quotas.edit', [$contract, $quota]), false);

        $this->actingAs($manager)->delete(route('sla-contracts.quotas.destroy', [$contract, $quota]))->assertRedirect();
        $this->assertSame(0, SlaContractQuota::query()->count());
    }

    public function test_quota_maintenance_needs_the_manage_permission(): void {
        $contract = $this->contract();

        $this->actingAs($this->viewer)->get(route('sla-contracts.quotas.create', $contract))->assertForbidden();
        $this->actingAs($this->viewer)->post(route('sla-contracts.quotas.store', $contract), [
            'period_kind' => 'month', 'included_hours' => '1', 'warn_threshold_pct' => '80',
        ])->assertForbidden();
        $this->assertSame(0, SlaContractQuota::query()->count());
    }
}
