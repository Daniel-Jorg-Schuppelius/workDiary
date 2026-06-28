<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogPriceAlertTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Enums\Procurement\CatalogItemStatus;
use App\Models\{Article, PricingChangeAlert, PricingMarginRule, Supplier, SupplierCatalogItem, SupplierCatalogSource, User};
use App\Services\Procurement\CatalogCsvImportService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 050, MVP-094: Kalkulationswarnung, wenn ein EK-Anstieg den
 * hinterlegten Verkaufspreis eines verknüpften Artikels unter die Mindestmarge
 * drückt.
 */
final class CatalogPriceAlertTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Supplier $supplier;
    private SupplierCatalogSource $source;
    private Article $article;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->source = SupplierCatalogSource::query()->create([
            'organization_id' => $this->organization->id, 'supplier_id' => $this->supplier->id,
            'name' => 'K', 'format' => 'csv', 'delimiter' => ';', 'decimal_separator' => ',',
            'encoding' => 'UTF-8', 'has_header' => true,
        ]);
        $this->article = Article::factory()->create([
            'organization_id' => $this->organization->id, 'purchasable' => true, 'default_sale_price' => '100.0000',
        ]);
        PricingMarginRule::query()->create([
            'organization_id' => $this->organization->id, 'name' => 'R', 'min_margin' => '30',
            'rounding' => 'none', 'priority' => 0, 'active' => true,
        ]);
    }

    private function linkedItem(string $price): SupplierCatalogItem {
        return SupplierCatalogItem::query()->create([
            'organization_id' => $this->organization->id,
            'supplier_catalog_source_id' => $this->source->id,
            'supplier_id' => $this->supplier->id,
            'external_no' => 'A-1', 'name' => 'Schraube',
            'purchase_price' => $price, 'currency' => 'EUR', 'pack_size' => '1',
            'article_id' => $this->article->id, 'status' => CatalogItemStatus::Linked->value, 'raw_hash' => 'seed',
        ]);
    }

    private function importPrice(string $ek): void {
        $csv = "ArtNr;Bezeichnung;EK\nA-1;Schraube;{$ek}";
        app(CatalogCsvImportService::class)->import($this->source, $csv, [
            'external_no' => 'ArtNr', 'name' => 'Bezeichnung', 'purchase_price' => 'EK',
        ]);
    }

    public function test_price_increase_below_min_margin_creates_alert(): void {
        $this->linkedItem('50.0000');      // Marge 50 %
        $this->importPrice('90,00');       // → Marge 10 % < 30 %

        $this->assertDatabaseHas('pricing_change_alerts', [
            'organization_id' => $this->organization->id,
            'article_id' => $this->article->id,
            'new_purchase_price' => '90.0000',
            'status' => PricingChangeAlert::STATUS_OPEN,
        ]);
    }

    public function test_price_change_within_margin_creates_no_alert(): void {
        $this->linkedItem('50.0000');
        $this->importPrice('60,00');       // → Marge 40 % ≥ 30 %

        $this->assertSame(0, PricingChangeAlert::query()->count());
    }

    public function test_unlinked_item_creates_no_alert(): void {
        SupplierCatalogItem::query()->create([
            'organization_id' => $this->organization->id,
            'supplier_catalog_source_id' => $this->source->id,
            'supplier_id' => $this->supplier->id,
            'external_no' => 'A-1', 'name' => 'Schraube', 'purchase_price' => '50.0000',
            'currency' => 'EUR', 'pack_size' => '1',
            'status' => CatalogItemStatus::New->value, 'raw_hash' => 'seed',
        ]);
        $this->importPrice('90,00');

        $this->assertSame(0, PricingChangeAlert::query()->count());
    }

    public function test_acknowledge_route_marks_done(): void {
        $alert = PricingChangeAlert::query()->create([
            'organization_id' => $this->organization->id,
            'supplier_catalog_item_id' => $this->linkedItem('90.0000')->id,
            'article_id' => $this->article->id, 'supplier_id' => $this->supplier->id,
            'new_purchase_price' => '90.0000', 'sale_price' => '100.0000', 'new_margin' => '10',
            'min_margin' => '30', 'status' => PricingChangeAlert::STATUS_OPEN,
        ]);

        $this->actingAs($this->admin)
            ->post(route('supplier-catalogs.alerts.acknowledge', $alert))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(PricingChangeAlert::STATUS_ACKNOWLEDGED, $alert->fresh()->status);
    }
}
