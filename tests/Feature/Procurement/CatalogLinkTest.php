<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogLinkTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Enums\Procurement\CatalogItemStatus;
use App\Models\{Article, Supplier, SupplierCatalogItem, SupplierCatalogSource, User};
use App\Services\Procurement\CatalogLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 050, MVP-093: Verknüpfung externer Katalogartikel mit dem internen
 * Artikelstamm und automatische Pflege der Bezugsquelle.
 */
final class CatalogLinkTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private CatalogLinkService $links;
    private User $admin;
    private Supplier $supplier;
    private SupplierCatalogSource $source;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->links = app(CatalogLinkService::class);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->source = SupplierCatalogSource::query()->create([
            'organization_id' => $this->organization->id, 'supplier_id' => $this->supplier->id,
            'name' => 'Katalog', 'format' => 'csv', 'delimiter' => ';',
            'decimal_separator' => ',', 'encoding' => 'UTF-8', 'has_header' => true,
        ]);
    }

    private function item(?string $gtin = '4001234567890'): SupplierCatalogItem {
        return SupplierCatalogItem::query()->create([
            'organization_id' => $this->organization->id,
            'supplier_catalog_source_id' => $this->source->id,
            'supplier_id' => $this->supplier->id,
            'external_no' => 'A-1', 'name' => 'Schraube M4', 'gtin' => $gtin,
            'purchase_price' => '1.5000', 'currency' => 'EUR', 'pack_size' => '1',
            'status' => CatalogItemStatus::New->value, 'raw_hash' => 'h1',
        ]);
    }

    private function article(?string $gtin = null): Article {
        return Article::factory()->create([
            'organization_id' => $this->organization->id, 'purchasable' => true, 'gtin' => $gtin,
        ]);
    }

    public function test_link_sets_status_and_creates_supply(): void {
        $item = $this->item();
        $article = $this->article();

        $this->links->link($item, $article);

        $this->assertSame(CatalogItemStatus::Linked, $item->fresh()->status);
        $this->assertSame($article->id, $item->fresh()->article_id);
        $this->assertDatabaseHas('article_supplies', [
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'supplier_id' => $this->supplier->id,
            'supplier_sku' => 'A-1',
            'purchase_price' => '1.5000',
        ]);
    }

    public function test_propose_matches_by_gtin(): void {
        $item = $this->item('4001234567890');
        $article = $this->article('4001234567890');

        $matched = $this->links->propose($item);

        $this->assertNotNull($matched);
        $this->assertSame($article->id, $matched->id);
        $this->assertSame(CatalogItemStatus::Proposed, $item->fresh()->status);
    }

    public function test_propose_returns_null_without_match(): void {
        $item = $this->item('9999999999999');
        $this->article('4001234567890');

        $this->assertNull($this->links->propose($item));
        $this->assertSame(CatalogItemStatus::New, $item->fresh()->status);
    }

    public function test_propose_matches_variant_by_sku(): void {
        $item = $this->item(null); // keine GTIN — Dienstleistungsfall
        $article = $this->article();
        $variant = $article->variants()->create([
            'organization_id' => $this->organization->id,
            'sku' => 'A-1', 'option_signature' => 'laufzeit=12', 'name' => 'Variante 12 Mon.',
        ]);

        $matched = $this->links->propose($item);

        $this->assertSame($article->id, $matched?->id);
        $this->assertSame($variant->id, $item->fresh()->article_variant_id);
        $this->assertSame(CatalogItemStatus::Proposed, $item->fresh()->status);
    }

    public function test_propose_matches_by_supply_sku(): void {
        $item = $this->item(null);
        $article = $this->article();
        \App\Models\ArticleSupply::query()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id, 'supplier_id' => $this->supplier->id,
            'supplier_sku' => 'A-1', 'purchase_price' => '1.4000', 'currency' => 'EUR',
        ]);

        $matched = $this->links->propose($item);

        $this->assertSame($article->id, $matched?->id);
        $this->assertNull($item->fresh()->article_variant_id);
    }

    public function test_propose_matches_unique_fuzzy_name(): void {
        $item = $this->item(null);
        $article = Article::factory()->create([
            'organization_id' => $this->organization->id, 'purchasable' => true, 'name' => 'Schraube M4',
        ]);

        $matched = $this->links->propose($item);

        $this->assertSame($article->id, $matched?->id);
        $this->assertSame(CatalogItemStatus::Proposed, $item->fresh()->status);
    }

    public function test_propose_skips_ambiguous_fuzzy_matches(): void {
        $item = $this->item(null);
        Article::factory()->create(['organization_id' => $this->organization->id, 'purchasable' => true, 'name' => 'Schraube M4']);
        Article::factory()->create(['organization_id' => $this->organization->id, 'purchasable' => true, 'name' => 'Schraube M4A']);

        $this->assertNull($this->links->propose($item));
        $this->assertSame(CatalogItemStatus::New, $item->fresh()->status);
    }

    public function test_unlink_clears_link_but_keeps_supply(): void {
        $item = $this->item();
        $article = $this->article();
        $this->links->link($item, $article);

        $this->links->unlink($item->fresh());

        $this->assertNull($item->fresh()->article_id);
        $this->assertSame(CatalogItemStatus::New, $item->fresh()->status);
        $this->assertDatabaseHas('article_supplies', ['article_id' => $article->id, 'supplier_id' => $this->supplier->id]);
    }

    public function test_link_route_links_item(): void {
        $item = $this->item();
        $article = $this->article();

        $this->actingAs($this->admin)
            ->post(route('supplier-catalogs.items.link', $item), ['article' => $article->sqid])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(CatalogItemStatus::Linked, $item->fresh()->status);
    }

    public function test_propose_route_flashes_no_match(): void {
        $item = $this->item('9999999999999');

        $this->actingAs($this->admin)
            ->post(route('supplier-catalogs.items.propose', $item))
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
