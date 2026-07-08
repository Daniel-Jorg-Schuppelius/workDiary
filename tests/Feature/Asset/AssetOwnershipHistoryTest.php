<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetOwnershipHistoryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Asset;

use App\Enums\Asset\AssetOwnership;
use App\Enums\User\Permission;
use App\Models\{Asset, AssetOwnershipChange, Customer, User};
use App\Services\Asset\AssetLifecycleService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 027 → Rang 49: Eigentümerwechsel-Historie. Prüft, dass Wechsel
 * ausschließlich über den {@see AssetLifecycleService} als unveränderliche,
 * append-only Zeilen entstehen, das Asset atomar mitgeführt wird und die
 * Objektakte die Historie zeigt.
 */
class AssetOwnershipHistoryTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $actor;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->actor = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    private function service(): AssetLifecycleService {
        return app(AssetLifecycleService::class);
    }

    public function test_change_records_history_and_updates_asset(): void {
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'owned_by' => 'org', 'customer_id' => null]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $change = $this->service()->changeOwnership($asset, $this->actor, AssetOwnership::Customer, $customer->id, 'Verkauft');

        $this->assertNotNull($change);
        $this->assertSame(AssetOwnership::Organization, $change->from_ownership);
        $this->assertSame(AssetOwnership::Customer, $change->to_ownership);
        $this->assertSame($customer->id, $change->to_customer_id);
        $this->assertSame($this->actor->id, $change->changed_by_user_id);

        $asset->refresh();
        $this->assertSame(AssetOwnership::Customer, $asset->owned_by);
        $this->assertSame($customer->id, $asset->customer_id);

        $this->assertDatabaseHas('audit_logs', ['event' => 'asset.ownership_changed', 'auditable_id' => $asset->id]);
    }

    public function test_no_change_writes_no_history(): void {
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'owned_by' => 'org', 'customer_id' => null]);

        $this->assertNull($this->service()->changeOwnership($asset, $this->actor, AssetOwnership::Organization, null));
        $this->assertSame(0, AssetOwnershipChange::query()->count());
    }

    public function test_switching_away_from_customer_clears_customer(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'owned_by' => 'customer', 'customer_id' => $customer->id]);

        // Ziel-Kunde wird bei Nicht-Kunden-Eigentümerschaft ignoriert.
        $change = $this->service()->changeOwnership($asset, $this->actor, AssetOwnership::Organization, $customer->id);

        $this->assertNotNull($change);
        $this->assertSame($customer->id, $change->from_customer_id);
        $this->assertNull($change->to_customer_id);

        $asset->refresh();
        $this->assertSame(AssetOwnership::Organization, $asset->owned_by);
        $this->assertNull($asset->customer_id);
    }

    public function test_history_is_append_only_and_ordered(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'owned_by' => 'org', 'customer_id' => null]);

        Carbon::setTestNow('2026-06-01 10:00:00');
        $this->service()->changeOwnership($asset, $this->actor, AssetOwnership::Customer, $customer->id);
        Carbon::setTestNow('2026-06-02 10:00:00');
        $this->service()->changeOwnership($asset->refresh(), $this->actor, AssetOwnership::Organization, null);
        Carbon::setTestNow();

        $this->assertSame(2, $asset->ownershipChanges()->count());
        // Erste Zeile bleibt unverändert (append-only), jüngste zuerst.
        $latest = $asset->ownershipChanges()->first();
        $this->assertSame(AssetOwnership::Organization, $latest->to_ownership);
    }

    public function test_dossier_shows_ownership_history(): void {
        $viewer = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $viewer->givePermissionTo(Permission::AssetView->value);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'owned_by' => 'org', 'customer_id' => null]);

        $this->service()->changeOwnership($asset, $viewer, AssetOwnership::Customer, $customer->id, 'Übergabe an Kunde');

        $this->actingAs($viewer)->get(route('assets.dossier', $asset))
            ->assertOk()
            ->assertSee(__('Eigentümerwechsel-Historie'))
            ->assertSee('Übergabe an Kunde');
    }
}
