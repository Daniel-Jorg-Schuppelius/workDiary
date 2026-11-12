<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillbeeInventoryModeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\Billbee;

use App\Enums\Integration\DataDomain;
use App\Enums\Inventory\InventoryMode;
use App\Models\Platform\{PluginSetting, User};
use App\Plugins\Billbee\BillbeePlugin;
use App\Plugins\Billbee\Services\BillbeeInventoryProvider;
use App\Plugins\JtlWawi\JtlWawiPlugin;
use App\Services\Integration\DataOwnershipResolver;
use App\Services\Inventory\{InventoryProviderResolver, LocalInventoryProvider};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1626: Die Bestandsführung „Billbee“ (Rückkanal der Bestände) ist auf der
 * Billbee-Seite wählbar — mit Recht, Zugangsdaten und ohne fremde Führung zu übergehen.
 */
final class BillbeeInventoryModeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => BillbeePlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'bb-key', 'username' => 'shopper', 'api_password' => 'bb-pass'],
        ]);
    }

    public function test_admin_switches_billbee_on_and_back_to_local(): void {
        $this->actingAs($this->admin)->get(route('admin.billbee.index'))->assertOk()
            ->assertSee(route('admin.billbee.mode.update'), false)
            ->assertSee(__('billbee::billbee.inventory.mode_external'));

        $this->actingAs($this->admin)->post(route('admin.billbee.mode.update'), ['inventory_mode' => 'read_only'])->assertSessionHasErrors('inventory_mode');
        $this->actingAs($this->admin)->post(route('admin.billbee.mode.update'), ['inventory_mode' => 'external'])
            ->assertSessionHas('success', __('billbee::billbee.inventory.changed'));

        $organization = $this->organization->refresh();
        $this->assertSame(InventoryMode::External, app(InventoryProviderResolver::class)->modeFor($organization));
        $this->assertInstanceOf(BillbeeInventoryProvider::class, app(InventoryProviderResolver::class)->providerFor($organization));
        $this->assertSame(BillbeePlugin::ID, app(DataOwnershipResolver::class)->ownerFor($organization, DataDomain::Inventory));
        $this->assertDatabaseHas('audit_logs', ['event' => 'inventory.mode_changed', 'auditable_id' => $organization->id]);

        $this->actingAs($this->admin)->post(route('admin.billbee.mode.update'), ['inventory_mode' => 'local'])->assertSessionHas('success');
        $this->assertInstanceOf(LocalInventoryProvider::class, app(InventoryProviderResolver::class)->providerFor($this->organization->refresh()));
        $this->assertNull(data_get($this->organization->settings, 'inventory_plugin_id'));
    }

    public function test_switch_needs_permission_credentials_and_no_foreign_lead(): void {
        $plain = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($plain)->post(route('admin.billbee.mode.update'), ['inventory_mode' => 'external'])->assertForbidden();

        PluginSetting::query()->firstOrFail()->update(['settings' => []]);
        $this->actingAs($this->admin)->post(route('admin.billbee.mode.update'), ['inventory_mode' => 'external'])
            ->assertSessionHas('error', __('billbee::billbee.inventory.needs_connection'));
        $this->assertSame(InventoryMode::Local, app(InventoryProviderResolver::class)->modeFor($this->organization->refresh()));

        // JTL führt: Billbee übergeht das nicht, und JTL lässt sich nicht von der Billbee-Seite abschalten.
        $this->organization->forceFill(['settings' => ['inventory_mode' => 'external', 'inventory_plugin_id' => JtlWawiPlugin::ID]])->save();
        $this->actingAs($this->admin->fresh())->post(route('admin.billbee.mode.update'), ['inventory_mode' => 'local'])->assertSessionHas('error');
        $this->assertSame(JtlWawiPlugin::ID, data_get($this->organization->refresh()->settings, 'inventory_plugin_id'));

        // Umgekehrt stellt die JTL-Seite eine Billbee-Führung nicht um (sonst liefe die JTL-Übernahme-Inventur).
        $this->organization->forceFill(['settings' => ['inventory_mode' => 'external', 'inventory_plugin_id' => BillbeePlugin::ID]])->save();
        $this->actingAs($this->admin->fresh())->post(route('admin.jtl.mode.update'), ['inventory_mode' => 'local'])
            ->assertSessionHas('error', __('jtl_wawi::jtl_wawi.flash.mode_other_plugin', ['plugin' => 'Billbee']));
        $this->assertSame(BillbeePlugin::ID, data_get($this->organization->refresh()->settings, 'inventory_plugin_id'));
    }
}
