<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdsPunchoutTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Enums\Organization\TenantStatus;
use App\Models\Article\{Article, ArticleSupply};
use App\Models\Inventory\Warehouse;
use App\Models\Platform\User;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Supplier\{Supplier, SupplierCatalogItem, SupplierCatalogSource};
use App\Services\Procurement\PunchoutHandoff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1071: IDS-Connect-Absprung, Warenkorb-Rücksprung über Einmal-Token und Artikel-Deeplink. */
final class IdsPunchoutTest extends TestCase {
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
            'name' => 'Großhandel', 'format' => 'csv', 'delimiter' => ';', 'decimal_separator' => ',',
            'encoding' => 'UTF-8', 'has_header' => true,
            'punchout_url' => 'https://shop.example.com/ids',
            'punchout_protocol' => 'ids', 'punchout_customer_number' => 'K-4711',
            'punchout_username' => 'einkauf', 'punchout_password' => 'geheim',
        ]);
    }

    private function articleWithSupply(string $sku): Article {
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'purchasable' => true]);
        ArticleSupply::query()->create([
            'organization_id' => $this->organization->id, 'article_id' => $article->id,
            'supplier_id' => $this->supplier->id, 'supplier_sku' => $sku,
            'moq' => '1', 'pack_size' => '1', 'lead_time_days' => 0, 'currency' => 'EUR',
        ]);

        return $article;
    }

    private function cart(string $returnFlag = 'Warenkorbrückgabe'): string {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Warenkorb>
  <WarenkorbInfo><Date>2026-10-02</Date><Time>14:30:05</Time><RueckgabeKZ>{$returnFlag}</RueckgabeKZ><Version>2.5</Version></WarenkorbInfo>
  <Order>
    <OrderInfo><Cur>EUR</Cur></OrderInfo>
    <OrderItem><ItemChara>normal</ItemChara><ArtNo>WT-200</ArtNo><Qty>2</Qty><QU>PCE</QU><Kurztext>Waschtisch</Kurztext><NetPrice>375.50</NetPrice><Hinweis>Lieferzeit 5 Tage</Hinweis></OrderItem>
    <OrderItem><ItemChara>normal</ItemChara><ArtNo>UNBEKANNT</ArtNo><Qty>1</Qty><QU>PCE</QU><Kurztext>Fremdartikel</Kurztext></OrderItem>
    <OrderItem><ItemChara>alternate</ItemChara><ArtNo>WT-300</ArtNo><Qty>2</Qty><QU>PCE</QU></OrderItem>
    <OrderItem><ArtNo>FEHLER-1</ArtNo><Qty>1</Qty><QU>PCE</QU><Fehlercode>17</Fehlercode><Fehlertext>Artikel gesperrt</Fehlertext></OrderItem>
  </Order>
</Warenkorb>
XML;
    }

    private function token(?User $user = null): string {
        return app(PunchoutHandoff::class)->issueHook($this->source, $this->warehouse, $user ?? $this->admin);
    }

    public function test_punchout_posts_ids_fields_with_short_single_use_hook(): void {
        $response = $this->actingAs($this->admin)
            ->get(route('supplier-catalogs.punchout', $this->source) . '?warehouse=' . $this->warehouse->sqid)
            ->assertOk()
            ->assertSee('https://shop.example.com/ids', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="kndnr" value="K-4711"', false)
            ->assertSee('name="action" value="WKE"', false)
            ->assertDontSee('HOOK_URL', false);

        preg_match('/name="hookurl" value="([^"]+)"/', (string) $response->getContent(), $match);
        $this->assertNotEmpty($match);
        $hook = html_entity_decode($match[1]);
        $this->assertLessThanOrEqual(256, strlen($hook));
        $this->assertStringNotContainsString('signature=', $hook);
    }

    public function test_cart_return_creates_draft_and_reports_shop_notes(): void {
        $article = $this->articleWithSupply('WT-200');
        $this->articleWithSupply('WT-300');
        $token = $this->token();

        // Kein actingAs: Der Shop POSTet cross-site ohne Sitzung; die Antwort setzt kein Cookie.
        $response = $this->post(route('oci-carts.ids-return', $token), ['warenkorb' => $this->cart()]);
        $response->assertRedirect();
        $this->assertSame([], $response->headers->getCookies());

        $order = PurchaseOrder::query()->where('organization_id', $this->organization->id)->latest('id')->firstOrFail();
        $lines = $order->lines()->get();
        $this->assertCount(1, $lines, 'nur die normale, zugeordnete Position — Alternative und Fehlerposition nicht');
        $this->assertSame($article->id, (int) $lines->first()->article_id);
        $this->assertSame('187.75', $lines->first()->unit_price->withScale(2)->getAmount());
        $this->assertSame((int) $this->admin->id, (int) $order->created_by);

        // Die Meldungen holt die angemeldete Sitzung ab — nur der Nutzer des Absprungs, nur einmal.
        $resultUrl = (string) $response->headers->get('Location');
        $other = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($other)->get($resultUrl)->assertRedirect(route('purchase-orders.index'));

        $result = $this->post(route('oci-carts.ids-return', $this->token()), ['warenkorb' => $this->cart()]);
        $this->actingAs($this->admin)->get((string) $result->headers->get('Location'))
            ->assertRedirect(route('purchase-orders.show', PurchaseOrder::query()->latest('id')->firstOrFail()))
            ->assertSessionHas('success')
            ->assertSessionHas('warning', fn (string $text): bool => str_contains($text, 'Lieferzeit 5 Tage') && str_contains($text, 'Artikel gesperrt'));

        // Einmal-Token: zweiter Rücksprung wird abgewiesen.
        $this->post(route('oci-carts.ids-return', $token), ['warenkorb' => $this->cart()])->assertNotFound();
        $this->assertSame(2, PurchaseOrder::query()->where('organization_id', $this->organization->id)->count());
    }

    public function test_ordered_cart_is_flagged_and_unreadable_cart_rejected(): void {
        $this->articleWithSupply('WT-200');

        $ordered = $this->post(route('oci-carts.ids-return', $this->token()), ['warenkorb' => $this->cart('Warenkorbrückgabe mit Bestellung')]);
        $this->actingAs($this->admin)->get((string) $ordered->headers->get('Location'))
            ->assertSessionHas('warning', fn (string $text): bool => str_contains($text, __('procurement.ids.flash.ordered_in_shop')));

        $unreadable = $this->post(route('oci-carts.ids-return', $this->token()), ['warenkorb' => '<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><Warenkorb>&e;</Warenkorb>']);
        $this->actingAs($this->admin)->get((string) $unreadable->headers->get('Location'))
            ->assertRedirect(route('supplier-catalogs.show', $this->source))
            ->assertSessionHas('error', __('procurement.ids.flash.unreadable'));
    }

    public function test_unknown_token_and_foreign_user_are_rejected(): void {
        $this->post(route('oci-carts.ids-return', str_repeat('a', 40)), ['warenkorb' => $this->cart()])->assertNotFound();

        $foreign = User::factory()->create();
        $this->post(route('oci-carts.ids-return', $this->token($foreign)), ['warenkorb' => $this->cart()])
            ->assertNotFound();
        $this->assertSame(0, PurchaseOrder::query()->count());
    }

    public function test_article_deeplink_posts_adl_without_hook(): void {
        $item = SupplierCatalogItem::query()->create([
            'organization_id' => $this->organization->id, 'supplier_catalog_source_id' => $this->source->id, 'supplier_id' => $this->supplier->id,
            'external_no' => 'WT-200', 'name' => 'Waschtisch', 'raw_hash' => hash('sha256', 'WT-200'),
        ]);

        $this->actingAs($this->admin)
            ->get(route('supplier-catalogs.items.shop', $item))
            ->assertOk()
            ->assertSee('name="action" value="ADL"', false)
            ->assertSee('name="ghnummer" value="WT-200"', false)
            ->assertDontSee('name="hookurl"', false);
    }

    public function test_ids_source_requires_customer_number(): void {
        $this->actingAs($this->admin)
            ->post(route('supplier-catalogs.store'), [
                'supplier' => $this->supplier->sqid, 'name' => 'IDS', 'format' => 'csv',
                'delimiter' => ';', 'decimal_separator' => ',', 'encoding' => 'UTF-8',
                'punchout_url' => 'https://shop.example.com/ids', 'punchout_protocol' => 'ids',
            ])
            ->assertSessionHasErrors('punchout_customer_number');
    }

    /** Sicherheitsaudit 2026-10-04, pub-3: der Link endet mit der Mandantensperre. */
    public function test_cart_return_is_locked_for_a_suspended_tenant(): void {
        $this->articleWithSupply('WT-200');
        $token = $this->token();
        $this->organization->forceFill(['tenant_status' => TenantStatus::Suspended])->save();

        $this->post(route('oci-carts.ids-return', $token), ['warenkorb' => $this->cart()])->assertStatus(423);
    }
}
