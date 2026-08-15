<?php
/*
 * Created on   : Sat Aug 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogArticleAdoptionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Enums\Article\{ArticleStatus, ArticleType};
use App\Enums\Procurement\CatalogItemStatus;
use App\Models\{Article, ArticleSupply, ArticleVariant, Supplier, SupplierCatalogItem, SupplierCatalogSource, User};
use App\Services\Procurement\CatalogArticleAdopter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 050, MVP-541: Übernahme von Katalogartikeln als Dienstleistungs-
 * Artikel — Tarif-Gruppe → Artikel, Angebote mit Zusatzattributen → Varianten
 * mit SKU = Offer-Key, Bezugsquelle = günstigstes Angebot.
 */
final class CatalogArticleAdoptionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private CatalogArticleAdopter $adopter;
    private SupplierCatalogSource $source;
    private Supplier $supplier;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->adopter = app(CatalogArticleAdopter::class);

        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->source = SupplierCatalogSource::query()->create([
            'organization_id' => $this->organization->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Distributor', 'format' => 'xlsx', 'delimiter' => ';',
            'decimal_separator' => '.', 'encoding' => 'UTF-8', 'has_header' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function item(array $overrides = []): SupplierCatalogItem {
        return SupplierCatalogItem::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'supplier_catalog_source_id' => $this->source->id,
            'supplier_id' => $this->supplier->id,
            'status' => CatalogItemStatus::New,
            'currency' => 'EUR',
            'raw_hash' => sha1((string) json_encode($overrides)),
        ], $overrides));
    }

    /** Zwei Angebote desselben CSP-Tarifs + ein Domain-Einzeltarif. */
    private function seedItems(): void {
        $this->item([
            'external_no' => 'CFQ7-0001-P1M-1M', 'name' => 'Microsoft Entra ID Governance',
            'manufacturer_no' => 'CFQ7TTC0MFT1:0001', 'purchase_price' => '6.00', 'list_price' => '7.32',
            'extra_attributes' => ['vertragslaufzeit' => '1', 'zahlungsintervall' => 'monatlich'],
        ]);
        $this->item([
            'external_no' => 'CFQ7-0001-P1Y-1M', 'name' => 'Microsoft Entra ID Governance',
            'manufacturer_no' => 'CFQ7TTC0MFT1:0001', 'purchase_price' => '5.24', 'list_price' => '6.41',
            'extra_attributes' => ['vertragslaufzeit' => '12', 'zahlungsintervall' => 'monatlich'],
        ]);
        $this->item([
            'external_no' => '.app Domain', 'name' => '.app Domain',
            'purchase_price' => '1.20',
        ]);
    }

    public function test_adopt_creates_service_article_with_variants(): void {
        $this->seedItems();

        $summary = $this->adopter->adoptSource($this->source);

        $this->assertSame(2, $summary['articles']);
        $this->assertSame(2, $summary['variants']);
        $this->assertSame(3, $summary['linked']);
        $this->assertSame(0, $summary['skipped']);
        $this->assertSame([], $summary['errors']);

        $article = Article::query()->where('name', 'Microsoft Entra ID Governance')->firstOrFail();
        $this->assertSame(ArticleType::Service, $article->type);
        $this->assertSame(ArticleStatus::Active, $article->status);
        $this->assertTrue($article->sellable && $article->purchasable);
        $this->assertFalse($article->stockable);
        $this->assertNotSame('', (string) $article->number);

        // Optionen: Laufzeit + Zahlungsintervall mit den Werten der Gruppe.
        $this->assertSame(['vertragslaufzeit', 'zahlungsintervall'], $article->optionDefinitions()->orderBy('position')->pluck('code')->all());

        $variant = ArticleVariant::query()->where('sku', 'CFQ7-0001-P1Y-1M')->firstOrFail();
        $this->assertSame($article->id, $variant->article_id);
        $this->assertSame(ArticleStatus::Active, $variant->status);
        $this->assertSame('5.2400', $variant->purchase_price?->getAmount());
        $this->assertSame('6.4100', $variant->sale_price?->getAmount()); // UVP als VK-Vorschlag

        // Items sind verknüpft, inkl. Variante.
        $item = SupplierCatalogItem::query()->where('external_no', 'CFQ7-0001-P1Y-1M')->firstOrFail();
        $this->assertSame(CatalogItemStatus::Linked, $item->status);
        $this->assertSame($variant->id, $item->article_variant_id);

        // Einzeltarif ohne Attribute: Artikel ohne Varianten, Preise am Artikel.
        $domain = Article::query()->where('name', '.app Domain')->firstOrFail();
        $this->assertSame(0, $domain->variants()->count());
        $this->assertSame('1.2000', $domain->default_purchase_price?->getAmount());
    }

    public function test_adopt_is_idempotent(): void {
        $this->seedItems();
        $this->adopter->adoptSource($this->source);

        $summary = $this->adopter->adoptSource($this->source);

        $this->assertSame(['articles' => 0, 'variants' => 0, 'linked' => 0, 'skipped' => 0, 'errors' => []], $summary);
        $this->assertSame(1, Article::query()->where('name', 'Microsoft Entra ID Governance')->count());
    }

    public function test_adopt_adds_missing_variants_to_existing_article(): void {
        $this->seedItems();
        $this->adopter->adoptSource($this->source);

        // Nachzügler-Angebot nach Re-Import: gleiche Gruppe, neue Kombination.
        $this->item([
            'external_no' => 'CFQ7-0001-P1Y-12M', 'name' => 'Microsoft Entra ID Governance',
            'manufacturer_no' => 'CFQ7TTC0MFT1:0001', 'purchase_price' => '5.00', 'list_price' => '6.10',
            'extra_attributes' => ['vertragslaufzeit' => '12', 'zahlungsintervall' => 'jährlich'],
        ]);

        $summary = $this->adopter->adoptSource($this->source);

        $this->assertSame(0, $summary['articles']);
        $this->assertSame(1, $summary['variants']);

        $article = Article::query()->where('name', 'Microsoft Entra ID Governance')->firstOrFail();
        $this->assertSame(3, $article->variants()->count());
    }

    public function test_adopt_skips_on_foreign_sku_conflict(): void {
        $this->seedItems();

        // Der Offer-Key existiert bereits als Varianten-SKU eines anderen Artikels.
        $other = Article::query()->create([
            'organization_id' => $this->organization->id,
            'number' => 'X-1', 'name' => 'Fremdartikel', 'type' => ArticleType::Service->value,
        ]);
        $other->variants()->create([
            'organization_id' => $this->organization->id,
            'sku' => 'CFQ7-0001-P1M-1M', 'option_signature' => 'x=y', 'name' => 'Fremdvariante',
        ]);

        $summary = $this->adopter->adoptSource($this->source);

        $this->assertSame(1, $summary['skipped']);
        $this->assertSame(2, $summary['variants'] + 1); // zweites Angebot der Gruppe wird trotzdem übernommen
        $this->assertNotSame([], $summary['errors']);
        $this->assertNull(SupplierCatalogItem::query()->where('external_no', 'CFQ7-0001-P1M-1M')->firstOrFail()->article_id);
    }

    public function test_supply_holds_cheapest_offer_of_group(): void {
        $this->seedItems();
        $this->adopter->adoptSource($this->source);

        $article = Article::query()->where('name', 'Microsoft Entra ID Governance')->firstOrFail();
        $supply = ArticleSupply::query()
            ->where('article_id', $article->id)
            ->where('supplier_id', $this->supplier->id)
            ->firstOrFail();

        $this->assertSame('5.2400', $supply->purchase_price?->getAmount());
        $this->assertSame('CFQ7-0001-P1Y-1M', $supply->supplier_sku);
    }

    public function test_adopt_routes_require_permission_and_flash_summary(): void {
        $this->seedItems();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->post(route('supplier-catalogs.adopt', $this->source))->assertForbidden();

        $this->actingAs($admin)->get(route('supplier-catalogs.adopt-form', $this->source))->assertOk();
        $this->actingAs($admin)->post(route('supplier-catalogs.adopt', $this->source))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(2, Article::query()->count());
    }

    public function test_adopt_item_route_adopts_whole_group(): void {
        $this->seedItems();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $seed = SupplierCatalogItem::query()->where('external_no', 'CFQ7-0001-P1M-1M')->firstOrFail();

        $this->actingAs($admin)->post(route('supplier-catalogs.items.adopt', $seed))
            ->assertRedirect()
            ->assertSessionHas('success');

        // Die ganze Tarif-Gruppe (2 Angebote), aber nicht der Domain-Tarif.
        $article = Article::query()->where('name', 'Microsoft Entra ID Governance')->firstOrFail();
        $this->assertSame(2, $article->variants()->count());
        $this->assertNull(SupplierCatalogItem::query()->where('external_no', '.app Domain')->firstOrFail()->article_id);
    }
}
