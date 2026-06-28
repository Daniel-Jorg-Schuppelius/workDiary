<?php
/*
 * Created on   : Sat Jun 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogConflictTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Enums\Procurement\CatalogItemStatus;
use App\Models\{Article, Supplier, SupplierCatalogItem, SupplierCatalogSource};
use App\Services\Procurement\CatalogCsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 050, MVP-094: keine stille Mutation verknüpfter Katalogartikel —
 * Identitätsänderung (GTIN) oder Verschwinden erzeugt einen Konflikt.
 */
final class CatalogConflictTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private SupplierCatalogSource $source;
    private Article $article;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->source = SupplierCatalogSource::query()->create([
            'organization_id' => $this->organization->id, 'supplier_id' => $supplier->id,
            'name' => 'K', 'format' => 'csv', 'delimiter' => ';', 'decimal_separator' => ',',
            'encoding' => 'UTF-8', 'has_header' => true,
        ]);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id, 'purchasable' => true]);
    }

    private function item(array $attrs): SupplierCatalogItem {
        return SupplierCatalogItem::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'supplier_catalog_source_id' => $this->source->id,
            'supplier_id' => $this->source->supplier_id,
            'external_no' => 'A-1', 'name' => 'Schraube', 'currency' => 'EUR', 'pack_size' => '1',
            'status' => CatalogItemStatus::New->value, 'raw_hash' => 'seed',
        ], $attrs));
    }

    private function import(string $csv, array $mapping): void {
        app(CatalogCsvImportService::class)->import($this->source, $csv, $mapping);
    }

    public function test_gtin_change_on_linked_item_creates_conflict(): void {
        $item = $this->item(['gtin' => '4011111111111', 'article_id' => $this->article->id, 'status' => CatalogItemStatus::Linked->value]);

        $this->import("ArtNr;Bez;EAN\nA-1;Schraube;4019999999999", ['external_no' => 'ArtNr', 'name' => 'Bez', 'gtin' => 'EAN']);

        $this->assertSame(CatalogItemStatus::Conflict, $item->fresh()->status);
        $this->assertSame($this->article->id, $item->fresh()->article_id); // Verknüpfung bleibt
    }

    public function test_linked_item_disappearing_becomes_conflict(): void {
        $item = $this->item(['article_id' => $this->article->id, 'status' => CatalogItemStatus::Linked->value]);

        $this->import("ArtNr;Bez\nB-2;Mutter", ['external_no' => 'ArtNr', 'name' => 'Bez']); // A-1 fehlt

        $this->assertSame(CatalogItemStatus::Conflict, $item->fresh()->status);
    }

    public function test_unlinked_item_disappearing_becomes_discontinued(): void {
        $item = $this->item(['external_no' => 'B-1', 'status' => CatalogItemStatus::New->value]);

        $this->import("ArtNr;Bez\nC-9;Anderes", ['external_no' => 'ArtNr', 'name' => 'Bez']); // B-1 fehlt

        $this->assertSame(CatalogItemStatus::Discontinued, $item->fresh()->status);
    }

    public function test_gtin_enrichment_on_linked_item_is_not_a_conflict(): void {
        // Vorher kein GTIN → Hinzufügen ist Anreicherung, kein Identitätswechsel.
        $item = $this->item(['gtin' => null, 'article_id' => $this->article->id, 'status' => CatalogItemStatus::Linked->value]);

        $this->import("ArtNr;Bez;EAN\nA-1;Schraube;4011111111111", ['external_no' => 'ArtNr', 'name' => 'Bez', 'gtin' => 'EAN']);

        $this->assertSame(CatalogItemStatus::Linked, $item->fresh()->status);
        $this->assertSame('4011111111111', $item->fresh()->gtin);
    }
}
