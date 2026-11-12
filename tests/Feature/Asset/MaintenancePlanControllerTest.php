<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaintenancePlanControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Asset;

use App\Enums\Asset\{MaintenanceDueAction, MaintenanceIntervalKind};
use App\Enums\User\{Permission, UserRole};
use App\Models\Asset\{Asset, MaintenancePlan};
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\ServiceTicket\SlaContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MaintenancePlanControllerTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $actor;

    private Asset $asset;

    protected function setUp(): void {
        parent::setUp();

        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);

        $this->actor = User::factory()->create(['organization_id' => $this->org->id]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->org->id);
        $role = Role::query()
            ->where('name', UserRole::Teamleitung->value)
            ->where('team_id', $this->org->id)
            ->firstOrFail();
        $this->actor->syncRoles([$role]);
        $registrar->forgetCachedPermissions();
        $this->actor->unsetRelation('roles');
        $this->actor->unsetRelation('permissions');

        $this->asset = Asset::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($this->actor);
    }

    public function test_can_open_plan_create_dialog(): void {
        $this->get(route('assets.maintenance-plans.create', $this->asset))
            ->assertOk()
            ->assertSee('Wartungsplan anlegen')
            ->assertSee('name="label"', false)
            ->assertSee('name="interval_kind"', false);
    }

    public function test_store_creates_plan(): void {
        $response = $this->post(route('assets.maintenance-plans.store', $this->asset), [
            'label' => 'Quartalswartung',
            'interval_kind' => MaintenanceIntervalKind::Months->value,
            'interval_value' => 3,
            'tolerance_days' => 7,
        ]);

        $response->assertRedirect(route('assets.show', $this->asset));
        $this->assertDatabaseHas('maintenance_plans', [
            'asset_id' => $this->asset->id,
            'label' => 'Quartalswartung',
        ]);
    }

    /** Vollscan 2026-10-21: Pläne lassen sich bearbeiten (vorher nur erledigen, pausieren, löschen). */
    public function test_edit_dialog_prefills_and_update_saves(): void {
        $plan = MaintenancePlan::factory()->create([
            'organization_id' => $this->asset->organization_id,
            'asset_id' => $this->asset->id,
            'label' => 'Filterwechsel',
            'interval_value' => 6,
        ]);

        $this->get(route('assets.show', $this->asset))->assertOk()
            ->assertSee(route('assets.maintenance-plans.edit', [$this->asset, $plan]), false);
        $this->get(route('assets.maintenance-plans.edit', [$this->asset, $plan]) . '?dialog=1')->assertOk()
            ->assertSee(__('Wartungsplan bearbeiten'))
            ->assertSee('value="Filterwechsel"', false)
            ->assertSee(route('assets.maintenance-plans.update', [$this->asset, $plan]), false);

        $this->put(route('assets.maintenance-plans.update', [$this->asset, $plan]), [
            'label' => 'Filter und Dichtung',
            'interval_kind' => 'months',
            'interval_value' => 3,
            'tolerance_days' => 5,
        ])->assertRedirect(route('assets.show', $this->asset));
        $this->assertSame('Filter und Dichtung', $plan->fresh()?->label);
        $this->assertSame(3, (int) $plan->fresh()?->interval_value);
    }

    public function test_complete_reschedules(): void {
        $plan = MaintenancePlan::factory()->create([
            'organization_id' => $this->org->id,
            'asset_id' => $this->asset->id,
            'next_due_on' => '2026-06-01',
        ]);

        $this->post(route('assets.maintenance-plans.complete', [$this->asset, $plan]))
            ->assertRedirect();

        $plan->refresh();
        $this->assertNotNull($plan->last_run_at);
    }

    public function test_toggle_pauses_then_resumes(): void {
        $plan = MaintenancePlan::factory()->create([
            'organization_id' => $this->org->id,
            'asset_id' => $this->asset->id,
            'is_active' => true,
        ]);

        $this->post(route('assets.maintenance-plans.toggle', [$this->asset, $plan]))
            ->assertRedirect();
        $this->assertFalse($plan->fresh()?->is_active);

        $this->post(route('assets.maintenance-plans.toggle', [$this->asset, $plan]))
            ->assertRedirect();
        $this->assertTrue($plan->fresh()?->is_active);
    }

    public function test_destroy_removes_plan(): void {
        $plan = MaintenancePlan::factory()->create([
            'organization_id' => $this->org->id,
            'asset_id' => $this->asset->id,
        ]);

        $this->delete(route('assets.maintenance-plans.destroy', [$this->asset, $plan]))
            ->assertRedirect();

        $this->assertDatabaseMissing('maintenance_plans', ['id' => $plan->id]);
    }

    /** gl-68: Vertragsbindung, Vertragspflicht und Fälligkeitsaktion haben eine Schreibstelle. */
    public function test_dialog_offers_only_matching_contracts(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);
        $other = Customer::factory()->create(['organization_id' => $this->org->id]);
        $this->asset->update(['customer_id' => $customer->id]);
        $own = SlaContract::factory()->create(['organization_id' => $this->org->id, 'customer_id' => $customer->id, 'code' => 'SLA-OWN', 'is_default' => false]);
        SlaContract::factory()->create(['organization_id' => $this->org->id, 'customer_id' => $other->id, 'code' => 'SLA-OTHER', 'is_default' => false]);
        SlaContract::factory()->create(['organization_id' => $this->org->id, 'code' => 'SLA-OLA', 'is_ola' => true, 'is_default' => false]);

        $this->get(route('assets.maintenance-plans.create', $this->asset))->assertOk()
            ->assertSee('name="sla_contract_id"', false)
            ->assertSee('name="is_contractual"', false)
            ->assertSee('name="due_action"', false)
            ->assertSee('value="' . $own->sqid . '"', false)
            ->assertDontSee('SLA-OTHER')
            ->assertDontSee('SLA-OLA');
    }

    public function test_store_binds_contract_and_contract_page_shows_obligation(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);
        $this->asset->update(['customer_id' => $customer->id]);
        $contract = SlaContract::factory()->create(['organization_id' => $this->org->id, 'customer_id' => $customer->id, 'is_default' => false]);

        $this->post(route('assets.maintenance-plans.store', $this->asset), [
            'label' => 'Jahresinspektion',
            'interval_kind' => MaintenanceIntervalKind::Months->value,
            'interval_value' => 12,
            'sla_contract_id' => $contract->sqid,
            'is_contractual' => '1',
            'due_action' => MaintenanceDueAction::Ticket->value,
        ])->assertRedirect(route('assets.show', $this->asset))->assertSessionHasNoErrors();

        $plan = MaintenancePlan::query()->where('label', 'Jahresinspektion')->firstOrFail();
        $this->assertSame($contract->id, $plan->sla_contract_id);
        $this->assertTrue($plan->is_contractual);
        $this->assertSame(MaintenanceDueAction::Ticket, $plan->due_action);

        $this->get(route('sla-contracts.show', $contract))->assertOk()
            ->assertSee('Jahresinspektion')
            ->assertSee(__('Vertragspflicht'));
        $this->get(route('assets.show', $this->asset))->assertOk()->assertSee(__('Vertragspflicht'));

        $this->put(route('assets.maintenance-plans.update', [$this->asset, $plan]), [
            'label' => 'Jahresinspektion',
            'interval_kind' => MaintenanceIntervalKind::Months->value,
            'interval_value' => 12,
            'sla_contract_id' => '',
            'is_contractual' => '0',
            'due_action' => MaintenanceDueAction::None->value,
        ])->assertSessionHasNoErrors();
        $plan->refresh();
        $this->assertNull($plan->sla_contract_id);
        $this->assertFalse($plan->is_contractual);
        $this->assertSame(MaintenanceDueAction::None, $plan->due_action);
    }

    public function test_obligation_needs_a_contract_of_the_asset_customer(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);
        $other = Customer::factory()->create(['organization_id' => $this->org->id]);
        $this->asset->update(['customer_id' => $customer->id]);
        $foreign = SlaContract::factory()->create(['organization_id' => $this->org->id, 'customer_id' => $other->id, 'is_default' => false]);
        $payload = ['label' => 'Prüfung', 'interval_kind' => 'months', 'interval_value' => 6];

        $this->post(route('assets.maintenance-plans.store', $this->asset), $payload + ['is_contractual' => '1'])
            ->assertSessionHasErrors('sla_contract_id');
        $this->post(route('assets.maintenance-plans.store', $this->asset), $payload + ['sla_contract_id' => $foreign->sqid])
            ->assertSessionHasErrors('sla_contract_id');
        $this->assertDatabaseMissing('maintenance_plans', ['label' => 'Prüfung']);
    }

    public function test_without_sla_right_binding_stays_untouched(): void {
        $contract = SlaContract::factory()->create(['organization_id' => $this->org->id, 'is_default' => false]);
        $plan = MaintenancePlan::factory()->create([
            'organization_id' => $this->org->id,
            'asset_id' => $this->asset->id,
            'sla_contract_id' => $contract->id,
            'is_contractual' => true,
        ]);
        $role = Role::create(['name' => 'wartung-ohne-sla', 'guard_name' => 'web', 'team_id' => $this->org->id]);
        $role->givePermissionTo([Permission::AssetView->value, Permission::AssetUpdate->value]);
        $this->actor->syncRoles([$role]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actor->unsetRelation('roles')->unsetRelation('permissions');

        $this->get(route('assets.maintenance-plans.edit', [$this->asset, $plan]))->assertOk()
            ->assertDontSee('name="sla_contract_id"', false)
            ->assertDontSee('name="is_contractual"', false);

        $this->put(route('assets.maintenance-plans.update', [$this->asset, $plan]), [
            'label' => 'Umbenannt',
            'interval_kind' => 'months',
            'interval_value' => 6,
            'sla_contract_id' => '',
            'is_contractual' => '0',
        ])->assertSessionHasNoErrors();
        $plan->refresh();
        $this->assertSame('Umbenannt', $plan->label);
        $this->assertSame($contract->id, $plan->sla_contract_id);
        $this->assertTrue($plan->is_contractual);
    }

    public function test_cross_asset_access_is_blocked(): void {
        $otherAsset = Asset::factory()->create(['organization_id' => $this->org->id]);
        $plan = MaintenancePlan::factory()->create([
            'organization_id' => $this->org->id,
            'asset_id' => $this->asset->id,
        ]);

        $this->delete(route('assets.maintenance-plans.destroy', [$otherAsset, $plan]))
            ->assertNotFound();
    }
}
