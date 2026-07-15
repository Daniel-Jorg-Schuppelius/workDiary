<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProductModelTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Product;

use App\Enums\User\Permission;
use App\Models\{Asset, Organization, Product, User};
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Produktmodell P1 (MVP-369, produktmodell-konzept.md): Datenmodell,
 * Org-Scope, Unique-Regel und Asset-Backfill.
 */
class ProductModelTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_manufacturer_model_is_unique_per_organization(): void {
        Product::factory()->create([
            'organization_id' => $this->organization->id,
            'manufacturer' => 'Kärcher',
            'model' => 'KAE-200',
        ]);

        // Andere Organisation darf dasselbe Paar führen.
        $otherOrg = Organization::factory()->create();
        Product::factory()->create([
            'organization_id' => $otherOrg->id,
            'manufacturer' => 'Kärcher',
            'model' => 'KAE-200',
        ]);
        $this->assertSame(2, Product::query()->withoutGlobalScopes()->count());

        $this->expectException(QueryException::class);
        Product::factory()->create([
            'organization_id' => $this->organization->id,
            'manufacturer' => 'Kärcher',
            'model' => 'KAE-200',
        ]);
    }

    public function test_products_are_organization_scoped(): void {
        $mine = Product::factory()->create(['organization_id' => $this->organization->id]);
        $otherOrg = Organization::factory()->create();
        Product::factory()->create(['organization_id' => $otherOrg->id]);

        app()->instance('currentOrganization', $this->organization);

        $visible = Product::query()->pluck('id')->all();
        $this->assertSame([$mine->id], $visible);
    }

    public function test_name_defaults_to_manufacturer_and_model_and_fields_are_trimmed(): void {
        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'manufacturer' => '  Kärcher ',
            'model' => ' KAE-200  ',
            'name' => '',
        ]);

        $this->assertSame('Kärcher', $product->manufacturer);
        $this->assertSame('KAE-200', $product->model);
        $this->assertSame('Kärcher KAE-200', $product->name);
    }

    public function test_backfill_creates_products_from_assets_and_types_them(): void {
        $otherOrg = Organization::factory()->create();

        // Gleiche Paare in unterschiedlicher Schreibweise → EIN Produkt je Org
        // (erste Schreibweise gewinnt); leere Hersteller/Modelle bleiben außen vor.
        $a1 = Asset::factory()->create(['organization_id' => $this->organization->id, 'manufacturer' => 'Kärcher', 'model' => 'KAE-200']);
        $a2 = Asset::factory()->create(['organization_id' => $this->organization->id, 'manufacturer' => ' kärcher ', 'model' => 'kae-200 ']);
        $a3 = Asset::factory()->create(['organization_id' => $this->organization->id, 'manufacturer' => 'Bosch', 'model' => 'GSB 18V']);
        $untyped = Asset::factory()->create(['organization_id' => $this->organization->id, 'manufacturer' => '', 'model' => 'X']);
        $foreign = Asset::factory()->create(['organization_id' => $otherOrg->id, 'manufacturer' => 'Kärcher', 'model' => 'KAE-200']);

        $created = Product::backfillFromAssets();

        $this->assertSame(3, $created); // Kärcher+Bosch (eigene Org) + Kärcher (Fremd-Org)

        $karcher = Product::query()->withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->where('manufacturer', 'Kärcher')->firstOrFail();
        $this->assertSame('KAE-200', $karcher->model); // erste Schreibweise gewinnt
        $this->assertSame($karcher->id, $a1->fresh()->product_id);
        $this->assertSame($karcher->id, $a2->fresh()->product_id);
        $this->assertNotNull($a3->fresh()->product_id);
        $this->assertNull($untyped->fresh()->product_id);
        $this->assertNotSame($karcher->id, $foreign->fresh()->product_id);

        // Idempotent: zweiter Lauf legt nichts Neues an.
        $this->assertSame(0, Product::backfillFromAssets());
    }

    public function test_policy_requires_product_permissions(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $manager = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $manager->givePermissionTo([Permission::ProductViewAny->value, Permission::ProductManage->value]);

        $this->assertFalse(Gate::forUser($user)->allows('viewAny', Product::class));
        $this->assertFalse(Gate::forUser($user)->allows('create', Product::class));
        $this->assertTrue(Gate::forUser($manager->fresh())->allows('viewAny', Product::class));
        $this->assertTrue(Gate::forUser($manager->fresh())->allows('create', Product::class));
    }
}
