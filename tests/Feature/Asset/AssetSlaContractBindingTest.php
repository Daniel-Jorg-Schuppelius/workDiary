<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetSlaContractBindingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Asset;

use App\Enums\Asset\{AssetClass, AssetOwnership, AssetStatus};
use App\Enums\User\Permission;
use App\Models\Asset\{Asset, MaintenancePlan};
use App\Models\Audit\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\ServiceTicket\SlaContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1628 (Paket 21g): `assets.sla_contract_id` hat eine Schreibstelle im Asset-Dialog –
 * Vertrag des Asset-Kunden oder ohne Kunden, kein OLA, kein projektgebundener Vertrag,
 * nur mit `slaContract.view`; ein Kundenwechsel löst Bindungen an Verträge des bisherigen Kunden.
 */
class AssetSlaContractBindingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    private Customer $otherCustomer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_edit_dialog_offers_only_bindable_contracts(): void {
        $asset = $this->asset();
        $own = $this->contract(['customer_id' => $this->customer->id, 'code' => 'SLA-OWN']);
        $free = $this->contract(['code' => 'SLA-FREE']);
        $this->contract(['customer_id' => $this->otherCustomer->id, 'code' => 'SLA-OTHER']);
        $this->contract(['code' => 'SLA-OLA', 'is_ola' => true]);
        $this->contract(['code' => 'SLA-INACTIVE', 'is_active' => false]);
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $this->contract(['customer_id' => $this->customer->id, 'project_id' => $project->id, 'code' => 'SLA-PROJECT']);

        $this->actingAs($this->admin)->get(route('assets.edit', $asset))->assertOk()
            ->assertSee('name="sla_contract_id"', false)
            ->assertSee('value="' . $own->sqid . '"', false)
            ->assertSee('value="' . $free->sqid . '"', false)
            ->assertDontSee('SLA-OTHER')
            ->assertDontSee('SLA-OLA')
            ->assertDontSee('SLA-INACTIVE')
            ->assertDontSee('SLA-PROJECT');
    }

    public function test_update_binds_and_clears_contract(): void {
        $asset = $this->asset();
        $contract = $this->contract(['customer_id' => $this->customer->id, 'code' => 'SLA-BOUND', 'label' => 'Premium-Vertrag']);

        $this->actingAs($this->admin)->put(route('assets.update', $asset), $this->payload(['sla_contract_id' => $contract->sqid]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('assets.show', $asset));
        $this->assertSame($contract->id, $asset->refresh()->sla_contract_id);

        $this->actingAs($this->admin)->get(route('assets.show', $asset))->assertOk()->assertSee('SLA-BOUND — Premium-Vertrag');
        $this->actingAs($this->admin)->get(route('assets.edit', $asset))->assertOk()
            ->assertSee('value="' . $contract->sqid . '" selected', false);

        $this->actingAs($this->admin)->put(route('assets.update', $asset), $this->payload(['sla_contract_id' => '']))
            ->assertSessionHasNoErrors();
        $this->assertNull($asset->refresh()->sla_contract_id);
    }

    public function test_create_binds_contract(): void {
        $contract = $this->contract(['code' => 'SLA-NEW']);

        $this->actingAs($this->admin)->post(route('assets.store'), $this->payload([
            'name' => 'Neue Anlage',
            'sla_contract_id' => $contract->sqid,
        ]))->assertSessionHasNoErrors();

        $this->assertSame($contract->id, Asset::query()->where('name', 'Neue Anlage')->firstOrFail()->sla_contract_id);
    }

    public function test_rejects_contracts_of_other_customers_ola_and_project_bound(): void {
        $asset = $this->asset();
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = $this->contract(['customer_id' => $this->otherCustomer->id]);
        $ola = $this->contract(['is_ola' => true]);
        $projectBound = $this->contract(['project_id' => $project->id]);

        foreach ([$foreign, $ola, $projectBound] as $contract) {
            $this->actingAs($this->admin)->put(route('assets.update', $asset), $this->payload(['sla_contract_id' => $contract->sqid]))
                ->assertSessionHasErrors('sla_contract_id');
        }
        $this->assertNull($asset->refresh()->sla_contract_id);
    }

    public function test_without_sla_right_field_is_hidden_and_binding_stays(): void {
        $contract = $this->contract(['customer_id' => $this->customer->id]);
        $asset = $this->asset(['sla_contract_id' => $contract->id]);
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        // Kunden-Factories verstellen das Spatie-Team; vor der Rechtevergabe zurücksetzen.
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->organization->id);
        $role = Role::create(['name' => 'asset-ohne-sla', 'guard_name' => 'web', 'team_id' => $this->organization->id]);
        $role->givePermissionTo([Permission::AssetView->value, Permission::AssetUpdate->value]);
        $user->syncRoles([$role]);
        $registrar->forgetCachedPermissions();
        $user->unsetRelation('roles')->unsetRelation('permissions');

        $this->actingAs($user)->get(route('assets.edit', $asset))->assertOk()
            ->assertDontSee('name="sla_contract_id"', false);

        $this->actingAs($user)->put(route('assets.update', $asset), $this->payload(['name' => 'Umbenannt', 'sla_contract_id' => '']))
            ->assertSessionHasNoErrors();
        $asset->refresh();
        $this->assertSame('Umbenannt', $asset->name);
        $this->assertSame($contract->id, $asset->sla_contract_id);
    }

    public function test_customer_change_releases_contracts_of_the_previous_customer(): void {
        $previous = $this->contract(['customer_id' => $this->customer->id]);
        $free = $this->contract();
        $asset = $this->asset(['sla_contract_id' => $previous->id]);
        $boundPlan = MaintenancePlan::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $asset->id,
            'sla_contract_id' => $previous->id,
            'is_contractual' => true,
        ]);
        $freePlan = MaintenancePlan::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $asset->id,
            'sla_contract_id' => $free->id,
            'is_contractual' => true,
        ]);

        // Ohne Vertragsfeld im Formular (etwa ohne Recht auf SLA-Verträge) muss der Wechsel trotzdem lösen.
        $this->actingAs($this->admin)->put(route('assets.update', $asset), [
            'asset_class' => AssetClass::Device->value,
            'status' => AssetStatus::Active->value,
            'name' => $asset->name,
            'customer_id' => $this->otherCustomer->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->otherCustomer->id, $asset->refresh()->customer_id);
        $this->assertNull($asset->sla_contract_id);
        $boundPlan->refresh();
        $this->assertNull($boundPlan->sla_contract_id);
        $this->assertFalse($boundPlan->is_contractual);
        $freePlan->refresh();
        $this->assertSame($free->id, $freePlan->sla_contract_id);
        $this->assertTrue($freePlan->is_contractual);

        $audit = AuditLog::query()->where('event', 'asset.ownership_changed')->latest('id')->firstOrFail();
        $this->assertSame([$previous->id], $audit->changes['released_sla_contract_ids'] ?? null);
    }

    public function test_dossier_marks_direct_binding_only_when_it_applies(): void {
        $customerContract = $this->contract(['customer_id' => $this->customer->id, 'code' => 'SLA-CUST']);
        $inactive = $this->contract(['customer_id' => $this->customer->id, 'is_active' => false]);
        $asset = $this->asset(['sla_contract_id' => $inactive->id]);

        $this->actingAs($this->admin)->get(route('assets.dossier', $asset))->assertOk()
            ->assertViewHas('slaContract', fn ($c): bool => $c !== null && $c->id === $customerContract->id)
            ->assertDontSee(__('Direktzuordnung'));

        $inactive->update(['is_active' => true]);
        $this->actingAs($this->admin)->get(route('assets.dossier', $asset))->assertOk()
            ->assertViewHas('slaContract', fn ($c): bool => $c !== null && $c->id === $inactive->id)
            ->assertSee(__('Direktzuordnung'));
    }

    /** @param array<string, mixed> $attributes */
    private function asset(array $attributes = []): Asset {
        return Asset::factory()->create($attributes + [
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'owned_by' => AssetOwnership::Customer->value,
            'asset_class' => AssetClass::Device->value,
            'status' => AssetStatus::Active->value,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function contract(array $attributes = []): SlaContract {
        return SlaContract::factory()->create($attributes + [
            'organization_id' => $this->organization->id,
            'is_default' => false,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array {
        return $overrides + [
            'asset_class' => AssetClass::Device->value,
            'status' => AssetStatus::Active->value,
            'name' => 'Kältemaschine',
            'customer_id' => $this->customer->id,
        ];
    }
}
