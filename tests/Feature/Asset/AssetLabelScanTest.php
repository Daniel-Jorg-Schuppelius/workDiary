<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetLabelScanTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Asset;

use App\Enums\Inventory\BarcodeMatchType;
use App\Models\Asset\Asset;
use App\Models\Platform\User;
use App\Services\Inventory\BarcodeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-882: Objekt-Etikett mit QR auf die Objektseite, Scan erkennt Objekte. */
class AssetLabelScanTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Asset $asset;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_no' => 'OBJ-0042',
            'inventory_no' => 'INV-7',
            'name' => 'Kompressor Halle 2',
        ]);
    }

    public function test_asset_label_renders_pdf(): void {
        $this->actingAs($this->admin)
            ->get(route('assets.label', $this->asset))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->admin)
            ->get(route('assets.show', $this->asset))
            ->assertSee(route('assets.label', $this->asset), false);
    }

    public function test_resolver_finds_asset_by_number_and_label_url(): void {
        $this->actingAs($this->admin);
        $resolver = app(BarcodeResolver::class);

        foreach (['OBJ-0042', 'INV-7', route('assets.show', $this->asset)] as $code) {
            $match = $resolver->resolve($code);
            $this->assertSame(BarcodeMatchType::Asset, $match->type, $code);
            $this->assertSame($this->asset->id, $match->asset?->id);
        }
        $this->assertFalse($resolver->resolve('/assets/unbekannt')->found());
    }

    public function test_scan_page_offers_asset_link(): void {
        $this->actingAs($this->admin)
            ->get(route('inventory.scan', ['code' => 'OBJ-0042']))
            ->assertOk()
            ->assertSee('Kompressor Halle 2')
            ->assertSee(route('assets.show', $this->asset), false);
    }

    public function test_foreign_asset_label_is_not_reachable(): void {
        $foreign = Asset::factory()->create();

        $this->actingAs($this->admin)->get(route('assets.label', $foreign))->assertNotFound();
    }
}
