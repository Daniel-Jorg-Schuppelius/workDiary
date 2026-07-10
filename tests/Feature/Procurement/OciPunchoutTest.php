<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OciPunchoutTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Models\{Article, ArticleSupply, Organization, PurchaseOrder, Supplier, SupplierCatalogSource, User, Warehouse};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 050, MVP-096: Aktiver OCI-Punchout-Roundtrip — Absprung als
 * selbst absendende POST-Form mit signierter HOOK_URL, Rücksprung des Shops
 * sessionlos gegen die Signatur (Cross-Site-POST ohne Session-Cookie).
 */
final class OciPunchoutTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Supplier $supplier;
    private Warehouse $warehouse;
    private SupplierCatalogSource $source;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->source = SupplierCatalogSource::query()->create([
            'organization_id' => $this->organization->id, 'supplier_id' => $this->supplier->id,
            'name' => 'Shop', 'format' => 'csv', 'delimiter' => ';', 'decimal_separator' => ',',
            'encoding' => 'UTF-8', 'has_header' => true,
            'punchout_url' => 'https://shop.example.com/oci/login',
            'punchout_username' => 'einkauf', 'punchout_password' => 'geheim',
        ]);
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

    private function hookUrl(?User $user = null): string {
        return URL::temporarySignedRoute('oci-carts.return', now()->addHour(), [
            'source' => $this->source->id,
            'warehouse' => $this->warehouse->sqid,
            'user' => ($user ?? $this->admin)->sqid,
        ]);
    }

    public function test_punchout_renders_autosubmit_form_with_signed_hook_url(): void {
        $this->actingAs($this->admin)
            ->get(route('supplier-catalogs.punchout', $this->source) . '?warehouse=' . $this->warehouse->sqid)
            ->assertOk()
            ->assertSee('https://shop.example.com/oci/login', false)
            ->assertSee('HOOK_URL', false)
            ->assertSee('oci-carts/return', false)
            ->assertSee('signature=', false)
            ->assertSee('name="USERNAME" value="einkauf"', false);
    }

    public function test_punchout_requires_warehouse_and_configuration(): void {
        // Ohne Lagerort → zurück mit Fehler.
        $this->actingAs($this->admin)
            ->get(route('supplier-catalogs.punchout', $this->source))
            ->assertRedirect()->assertSessionHas('error');

        // Ohne konfigurierte Punchout-URL → zurück mit Fehler.
        $this->source->forceFill(['punchout_url' => null])->save();
        $this->actingAs($this->admin)
            ->get(route('supplier-catalogs.punchout', $this->source) . '?warehouse=' . $this->warehouse->sqid)
            ->assertRedirect()->assertSessionHas('error');
    }

    public function test_hook_return_creates_purchase_order_without_session(): void {
        $article = $this->articleWithSupply('X-100');

        // Kein actingAs: Der Shop POSTet cross-site ohne Session-Cookie.
        $this->post($this->hookUrl(), [
            'NEW_ITEM-VENDORMAT' => [1 => 'X-100'],
            'NEW_ITEM-DESCRIPTION' => [1 => 'Kabelkanal'],
            'NEW_ITEM-QUANTITY' => [1 => '4'],
            'NEW_ITEM-PRICE' => [1 => '2.50'],
        ])->assertRedirect();

        $order = PurchaseOrder::query()->where('supplier_id', $this->supplier->id)->latest('id')->firstOrFail();
        $this->assertSame($this->admin->id, (int) $order->created_by);
        $this->assertSame(1, $order->lines()->count());
        $this->assertSame($article->id, (int) $order->lines()->firstOrFail()->article_id);
    }

    public function test_hook_return_rejects_missing_or_invalid_signature(): void {
        $this->post(route('oci-carts.return', [
            'source' => $this->source->id,
            'warehouse' => $this->warehouse->sqid,
            'user' => $this->admin->sqid,
        ]), [
            'NEW_ITEM-VENDORMAT' => [1 => 'X-100'],
        ])->assertForbidden();
    }

    public function test_hook_return_rejects_expired_signature(): void {
        $url = URL::temporarySignedRoute('oci-carts.return', now()->subMinute(), [
            'source' => $this->source->id,
            'warehouse' => $this->warehouse->sqid,
            'user' => $this->admin->sqid,
        ]);

        $this->post($url, ['NEW_ITEM-VENDORMAT' => [1 => 'X-100']])->assertForbidden();
    }

    public function test_hook_return_rejects_cross_org_user(): void {
        $orgB = Organization::factory()->create();
        $stranger = User::factory()->create(['organization_id' => $orgB->id]);

        $this->post($this->hookUrl($stranger), [
            'NEW_ITEM-VENDORMAT' => [1 => 'X-100'],
        ])->assertNotFound();
    }

    public function test_source_form_rejects_internal_punchout_url(): void {
        $this->actingAs($this->admin)
            ->post(route('supplier-catalogs.store'), [
                'supplier' => $this->supplier->sqid, 'name' => 'Quelle', 'format' => 'csv',
                'delimiter' => ';', 'decimal_separator' => ',', 'encoding' => 'UTF-8',
                'punchout_url' => 'https://192.168.0.10/oci',
            ])->assertSessionHasErrors('punchout_url');
    }
}
