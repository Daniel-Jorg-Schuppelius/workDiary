<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetSpecTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Services\Import\Specs;

use App\Enums\Asset\{AssetClass, AssetOwnership, AssetStatus};
use App\Enums\Import\ImportErrorCode;
use App\Models\{Asset, Customer, Organization};
use App\Services\Import\ImportOutcome;
use App\Services\Import\Specs\AssetSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class AssetSpecTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Customer::factory()->create(['organization_id' => $this->organization->id, 'number' => 'K-1', 'name' => 'Kunde']);
    }

    public function test_normalize_maps_enum_values_case_insensitively(): void {
        $row = (new AssetSpec())->normalize([
            'asset_no' => ' AS-1 ',
            'name' => 'Server',
            'asset_class' => 'Machine',
            'status' => 'inmaintenance',
            'owned_by' => 'CUSTOMER',
            'acquisition_cost' => '1.234,50',
        ]);

        $this->assertSame('AS-1', $row['asset_no']);
        $this->assertSame(AssetClass::Machine->value, $row['asset_class']);
        $this->assertSame(AssetStatus::InMaintenance->value, $row['status']);
        $this->assertSame(AssetOwnership::Customer->value, $row['owned_by']);
        $this->assertSame('1234.50', $row['acquisition_cost']);
    }

    public function test_validate_row_checks_enums_dates_and_customer_ownership(): void {
        $spec = new AssetSpec();

        $issues = $spec->validateRow($spec->normalize([
            'asset_no' => 'AS-1',
            'name' => 'Server',
            'asset_class' => 'spaceship',
            'owned_by' => 'customer',
            'commissioned_on' => 'kein datum',
            'customer_number' => '',
        ]), $this->organization);

        $fields = array_map(static fn($i) => $i->field, $issues);
        $this->assertContains('asset_class', $fields);
        $this->assertContains('commissioned_on', $fields);
        $this->assertContains('customer_number', $fields, 'Kundeneigentum braucht einen Kunden');
    }

    public function test_upsert_creates_then_updates_by_asset_no(): void {
        $spec = new AssetSpec();

        [$o1] = $spec->upsert($spec->normalize([
            'asset_no' => 'AS-1',
            'name' => 'Server',
            'asset_class' => 'device',
            'owned_by' => 'customer',
            'customer_number' => 'K-1',
            'commissioned_on' => '01.03.2023',
            'warranty_until' => '2026-03-01',
            'acquisition_cost' => '999,99',
        ]), $this->organization);
        $this->assertSame(ImportOutcome::Created, $o1);

        $asset = Asset::query()->where('asset_no', 'AS-1')->firstOrFail();
        $this->assertSame('2023-03-01', $asset->commissioned_on?->toDateString());
        $this->assertSame(AssetOwnership::Customer, $asset->owned_by);
        $this->assertSame((int) Customer::query()->where('number', 'K-1')->value('id'), (int) $asset->customer_id);
        $this->assertSame(AssetStatus::Active, $asset->status);

        [$o2] = $spec->upsert($spec->normalize(['asset_no' => 'AS-1', 'name' => 'Server neu', 'status' => 'decommissioned']), $this->organization);
        $this->assertSame(ImportOutcome::Updated, $o2);
        $asset->refresh();
        $this->assertSame('Server neu', $asset->name);
        $this->assertSame(AssetStatus::Decommissioned, $asset->status);
        $this->assertSame('2023-03-01', $asset->commissioned_on?->toDateString(), 'Leere Felder überschreiben nichts');
        $this->assertSame(1, Asset::query()->where('organization_id', $this->organization->id)->count());
    }

    public function test_customer_lookup_is_tenant_scoped(): void {
        $other = Organization::factory()->create();
        Customer::factory()->create(['organization_id' => $other->id, 'number' => 'K-F', 'name' => 'Fremd']);
        $spec = new AssetSpec();

        [$outcome, $issue] = $spec->upsert($spec->normalize(['asset_no' => 'AS-2', 'name' => 'X', 'customer_number' => 'K-F']), $this->organization);

        $this->assertSame(ImportOutcome::Failed, $outcome);
        $this->assertSame(ImportErrorCode::FkMissing, $issue?->code);
        $this->assertSame(0, Asset::query()->count());
    }
}
