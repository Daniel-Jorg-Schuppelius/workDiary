<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Asset;

use App\Enums\Asset\{AssetClass, AssetOwnership, AssetStatus};
use App\Exceptions\AssetValidationException;
use App\Models\{Asset, AuditLog, Customer, Organization, User};
use App\Services\Asset\{AssetNumberGenerator, AssetService, AssetStatusMachine};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetServiceTest extends TestCase {
    use RefreshDatabase;

    private AssetService $service;

    private Organization $org;

    private User $actor;

    protected function setUp(): void {
        parent::setUp();

        $this->service = new AssetService(new AssetNumberGenerator, new AssetStatusMachine);
        $this->org = Organization::factory()->create();
        $this->actor = User::factory()->geschaeftsfuehrung()->create([
            'organization_id' => $this->org->id,
        ]);

        $this->actingAs($this->actor);
    }

    public function test_create_generates_asset_number_and_writes_audit(): void {
        $asset = $this->service->create($this->actor, [
            'asset_class' => AssetClass::Device->value,
            'name' => 'Core Router',
            'owned_by' => AssetOwnership::Organization->value,
            'status' => AssetStatus::Active->value,
        ]);

        $this->assertStringStartsWith('AS-' . now()->format('Y') . '-', $asset->asset_no);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'asset.created',
            'auditable_type' => Asset::class,
            'auditable_id' => $asset->id,
        ]);
    }

    public function test_create_requires_customer_for_customer_owned_asset(): void {
        $this->expectException(AssetValidationException::class);

        $this->service->create($this->actor, [
            'asset_class' => AssetClass::Device->value,
            'name' => 'Customer Firewall',
            'owned_by' => AssetOwnership::Customer->value,
            'status' => AssetStatus::Active->value,
        ]);
    }

    public function test_transfer_ownership_to_customer_sets_customer_id(): void {
        $asset = Asset::factory()->create([
            'organization_id' => $this->org->id,
            'owned_by' => AssetOwnership::Organization->value,
            'customer_id' => null,
        ]);
        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);

        $updated = $this->service->transferOwnership($asset, $this->actor, AssetOwnership::Customer, $customer->id);

        $this->assertSame(AssetOwnership::Customer, $updated->owned_by);
        $this->assertSame($customer->id, $updated->customer_id);

        $audit = AuditLog::query()->where('event', 'asset.ownershipTransferred')->latest('id')->first();
        $this->assertNotNull($audit);
    }

    public function test_decommission_requires_valid_transition_and_date(): void {
        $asset = Asset::factory()->create([
            'organization_id' => $this->org->id,
            'status' => AssetStatus::Active->value,
        ]);

        $result = $this->service->decommission($asset, $this->actor, now()->toDateString());

        $this->assertSame(AssetStatus::Decommissioned, $result->status);
        $this->assertNotNull($result->decommissioned_on);
    }
}
