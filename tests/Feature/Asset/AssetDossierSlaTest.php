<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetDossierSlaTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Asset;

use App\Enums\User\Permission;
use App\Models\{Asset, Customer, SlaContract, User};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 027 → Rang 48: SLA-/Vertrags-Sektion im Asset-Dossier. Prüft die
 * Auflösungspräzedenz (Direktzuordnung > kundengebundener Vertrag >
 * Default-Vertrag) und dass die Sektion nur mit Recht `slaContract.view`
 * erscheint.
 */
class AssetDossierSlaTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $viewer;
    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->viewer = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->viewer->givePermissionTo([Permission::AssetView->value, Permission::SlaContractView->value]);

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function asset(array $attributes = []): Asset {
        return Asset::factory()->create($attributes + ['organization_id' => $this->organization->id]);
    }

    public function test_direct_assignment_overrides_customer_and_default(): void {
        SlaContract::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => null, 'is_default' => true, 'is_active' => true]);
        SlaContract::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'is_default' => false, 'is_active' => true]);
        $override = SlaContract::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => null, 'is_default' => false, 'is_active' => true]);

        $asset = $this->asset(['customer_id' => $this->customer->id, 'sla_contract_id' => $override->id]);

        $this->actingAs($this->viewer)->get(route('assets.dossier', $asset))
            ->assertOk()
            ->assertViewHas('slaContract', fn ($c): bool => $c !== null && $c->id === $override->id);
    }

    public function test_customer_contract_wins_over_default_without_override(): void {
        SlaContract::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => null, 'is_default' => true, 'is_active' => true]);
        $customerContract = SlaContract::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'is_default' => false, 'is_active' => true]);

        $asset = $this->asset(['customer_id' => $this->customer->id, 'sla_contract_id' => null]);

        $this->actingAs($this->viewer)->get(route('assets.dossier', $asset))
            ->assertOk()
            ->assertViewHas('slaContract', fn ($c): bool => $c !== null && $c->id === $customerContract->id);
    }

    public function test_falls_back_to_default_contract(): void {
        $default = SlaContract::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => null, 'is_default' => true, 'is_active' => true]);

        $asset = $this->asset(['customer_id' => null, 'sla_contract_id' => null]);

        $this->actingAs($this->viewer)->get(route('assets.dossier', $asset))
            ->assertOk()
            ->assertViewHas('slaContract', fn ($c): bool => $c !== null && $c->id === $default->id)
            ->assertSee(__('Verträge & SLA'));
    }

    public function test_section_hidden_without_sla_permission(): void {
        SlaContract::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => null, 'is_default' => true, 'is_active' => true]);
        $limited = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $limited->givePermissionTo(Permission::AssetView->value); // aber NICHT slaContract.view

        $asset = $this->asset(['customer_id' => null]);

        $this->actingAs($limited)->get(route('assets.dossier', $asset))
            ->assertOk()
            ->assertViewHas('canViewSla', false)
            ->assertDontSee(__('Verträge & SLA'));
    }
}
