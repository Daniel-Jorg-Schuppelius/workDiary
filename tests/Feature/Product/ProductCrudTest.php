<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProductCrudTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Product;

use App\Models\{Article, Asset, Product, User};
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Produktmodell P2 (MVP-370): CRUD-UI mit Modal-Pflege, Suche und
 * Typ-Picker in Asset-/Artikel-Dialog (produktmodell-konzept.md).
 */
class ProductCrudTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_index_requires_permission_and_lists_products(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        Product::factory()->create([
            'organization_id' => $this->organization->id,
            'manufacturer' => 'Kärcher',
            'model' => 'KAE-200',
        ]);

        $this->actingAs($stranger)->get(route('products.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('products.index'))
            ->assertOk()
            ->assertSee('Kärcher')
            ->assertSee('KAE-200');
    }

    public function test_search_filters_by_manufacturer_and_model(): void {
        Product::factory()->create(['organization_id' => $this->organization->id, 'manufacturer' => 'Kärcher', 'model' => 'KAE-200']);
        Product::factory()->create(['organization_id' => $this->organization->id, 'manufacturer' => 'Bosch', 'model' => 'GSB 18V']);

        $this->actingAs($this->admin)->get(route('products.index', ['q' => 'kärch']))
            ->assertOk()
            ->assertSee('KAE-200')
            ->assertDontSee('GSB 18V');
    }

    public function test_store_creates_product_and_duplicate_is_rejected(): void {
        $this->actingAs($this->admin)->post(route('products.store'), [
            'manufacturer' => 'Kärcher',
            'model' => 'KAE-200',
            'name' => '',
            'status' => 'active',
        ])->assertRedirect(route('products.index'));

        $product = Product::query()->where('model', 'KAE-200')->firstOrFail();
        $this->assertSame('Kärcher KAE-200', $product->name);

        // Duplikat (gleiche Org, gleicher Hersteller+Modell) → Validierungsfehler.
        $this->actingAs($this->admin)->from(route('products.index'))->post(route('products.store'), [
            'manufacturer' => 'Kärcher',
            'model' => 'KAE-200',
            'status' => 'active',
        ])->assertSessionHasErrors('model');
    }

    public function test_update_and_destroy_work_and_keep_linked_records(): void {
        $product = Product::factory()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'product_id' => $product->id]);

        $this->actingAs($this->admin)->put(route('products.update', $product), [
            'manufacturer' => $product->manufacturer,
            'model' => $product->model,
            'name' => 'Neuer Anzeigename',
            'status' => 'phasing_out',
        ])->assertRedirect(route('products.index'));

        $this->assertSame('Neuer Anzeigename', $product->fresh()->name);
        $this->assertSame('phasing_out', $product->fresh()->status->value);

        $this->actingAs($this->admin)->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'));

        // nullOnDelete: Asset bleibt, verliert nur die Typ-Zuordnung.
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertNull($asset->fresh()->product_id);
    }

    public function test_asset_store_prefills_manufacturer_and_model_from_product(): void {
        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'manufacturer' => 'Kärcher',
            'model' => 'KAE-200',
        ]);

        $this->actingAs($this->admin)->post(route('assets.store'), [
            'asset_class' => 'machine',
            'name' => 'Kehrmaschine Halle 1',
            'status' => 'active',
            'product_id' => $product->id,
        ])->assertRedirect(route('assets.index'));

        $this->assertDatabaseHas('assets', [
            'name' => 'Kehrmaschine Halle 1',
            'product_id' => $product->id,
            'manufacturer' => 'Kärcher',
            'model' => 'KAE-200',
        ]);
    }

    public function test_article_store_accepts_product_sqid(): void {
        $product = Product::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)->post(route('articles.store'), [
            'name' => 'Ersatzbürste',
            'type' => 'raw',
            'base_unit' => 'kg',
            'status' => 'active',
            'currency' => 'EUR',
            'product_id' => Sqid::encode(Product::class, $product->id),
        ]);

        $article = Article::query()->where('name', 'Ersatzbürste')->firstOrFail();
        $this->assertSame($product->id, $article->product_id);
    }
}
