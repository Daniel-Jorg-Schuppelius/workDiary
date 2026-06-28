<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogOciImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Models\{Article, ArticleSupply, PurchaseOrder, PurchaseOrderLine, Supplier, User, Warehouse};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 050, MVP-096: OCI-/IDS-Warenkorb → Bestellentwurf. Zeilen werden über
 * die Lieferanten-Artikelnummer (Bezugsquelle) aufgelöst; unzuordenbare Zeilen
 * werden gemeldet, nicht still verworfen.
 */
final class CatalogOciImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Supplier $supplier;
    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function articleWithSupply(string $vendormat): Article {
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'purchasable' => true]);
        ArticleSupply::query()->create([
            'organization_id' => $this->organization->id, 'article_id' => $article->id,
            'supplier_id' => $this->supplier->id, 'supplier_sku' => $vendormat,
            'moq' => '1', 'pack_size' => '1', 'lead_time_days' => 0, 'currency' => 'EUR',
        ]);

        return $article;
    }

    public function test_oci_cart_creates_draft_with_matched_lines(): void {
        $this->articleWithSupply('V-1');
        $this->articleWithSupply('V-2');

        $this->actingAs($this->admin)->post(route('oci-carts.import'), [
            'supplier' => $this->supplier->sqid,
            'warehouse' => $this->warehouse->sqid,
            'NEW_ITEM-VENDORMAT' => [1 => 'V-1', 2 => 'V-2'],
            'NEW_ITEM-DESCRIPTION' => [1 => 'Schraube', 2 => 'Mutter'],
            'NEW_ITEM-QUANTITY' => [1 => '10', 2 => '5'],
            'NEW_ITEM-PRICE' => [1 => '1.50', 2 => '0.80'],
        ])->assertRedirect()->assertSessionHas('success');

        $order = PurchaseOrder::query()->where('supplier_id', $this->supplier->id)->latest('id')->firstOrFail();
        $this->assertSame(2, PurchaseOrderLine::query()->where('purchase_order_id', $order->id)->count());
        $this->assertSame('10.0000', $order->lines()->orderBy('id')->first()->ordered_qty);
    }

    public function test_oci_cart_reports_unmatched_lines(): void {
        $this->articleWithSupply('V-1');

        $this->actingAs($this->admin)->post(route('oci-carts.import'), [
            'supplier' => $this->supplier->sqid,
            'warehouse' => $this->warehouse->sqid,
            'NEW_ITEM-VENDORMAT' => [1 => 'V-1', 2 => 'V-UNKNOWN'],
            'NEW_ITEM-DESCRIPTION' => [1 => 'Schraube', 2 => 'Unbekannt'],
            'NEW_ITEM-QUANTITY' => [1 => '3', 2 => '7'],
        ])->assertRedirect();

        $order = PurchaseOrder::query()->where('supplier_id', $this->supplier->id)->latest('id')->firstOrFail();
        $this->assertSame(1, PurchaseOrderLine::query()->where('purchase_order_id', $order->id)->count());
    }

    public function test_missing_context_redirects_with_error(): void {
        $this->actingAs($this->admin)->post(route('oci-carts.import'), [
            'NEW_ITEM-VENDORMAT' => [1 => 'V-1'],
            'NEW_ITEM-DESCRIPTION' => [1 => 'Schraube'],
        ])->assertRedirect(route('purchase-orders.index'))->assertSessionHas('error');
    }

    public function test_import_requires_post_permission(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->post(route('oci-carts.import'), [
            'supplier' => $this->supplier->sqid, 'warehouse' => $this->warehouse->sqid,
        ])->assertForbidden();
    }
}
