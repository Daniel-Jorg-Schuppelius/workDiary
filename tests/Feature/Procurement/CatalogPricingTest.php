<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogPricingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Enums\Procurement\CatalogItemStatus;
use App\Models\{Article, PricingMarginRule, Supplier, SupplierCatalogItem, SupplierCatalogSource, User};
use App\Services\Procurement\{CatalogLinkService, PriceSuggestionService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 050, MVP-095: Verkaufspreisvorschläge aus Margenregeln, Rundung/
 * Mindestmarge, Regelauflösung und Freigabe in den Artikelstamm.
 */
final class CatalogPricingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private PriceSuggestionService $pricing;
    private User $admin;
    private Supplier $supplier;
    private SupplierCatalogSource $source;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->pricing = app(PriceSuggestionService::class);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->source = SupplierCatalogSource::query()->create([
            'organization_id' => $this->organization->id, 'supplier_id' => $this->supplier->id,
            'name' => 'K', 'format' => 'csv', 'delimiter' => ';', 'decimal_separator' => ',',
            'encoding' => 'UTF-8', 'has_header' => true,
        ]);
    }

    /** @param array<string, mixed> $attrs */
    private function rule(array $attrs): PricingMarginRule {
        return PricingMarginRule::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'name' => 'R',
            'rounding' => 'none', 'priority' => 0, 'active' => true,
        ], $attrs));
    }

    private function item(string $price = '112.0000', ?string $category = null): SupplierCatalogItem {
        return SupplierCatalogItem::query()->create([
            'organization_id' => $this->organization->id,
            'supplier_catalog_source_id' => $this->source->id,
            'supplier_id' => $this->supplier->id,
            'external_no' => 'A-1', 'name' => 'Artikel', 'category' => $category,
            'purchase_price' => $price, 'currency' => 'EUR', 'pack_size' => '1',
            'status' => CatalogItemStatus::New->value, 'raw_hash' => 'h',
        ]);
    }

    public function test_target_margin_suggestion(): void {
        $rule = $this->rule(['target_margin' => '30']);
        $s = $this->pricing->suggest($rule, '112.00');

        $this->assertSame('160.00', $s['price']); // 112 / (1 - 0,30)
        $this->assertSame(30.0, $s['margin']);
        $this->assertFalse($s['below_min']);
    }

    public function test_markup_suggestion(): void {
        $rule = $this->rule(['markup_percent' => '50']);
        $this->assertSame('150.00', $this->pricing->suggest($rule, '100.00')['price']);
    }

    public function test_rounding_up_to_99(): void {
        $rule = $this->rule(['target_margin' => '30', 'rounding' => 'up_0_99']);
        $this->assertSame('160.99', $this->pricing->suggest($rule, '112.00')['price']);
    }

    public function test_min_sale_price_floor(): void {
        $rule = $this->rule(['markup_percent' => '10', 'min_sale_price' => '50']);
        $this->assertSame('50.00', $this->pricing->suggest($rule, '30.00')['price']); // 33 < 50 → 50
    }

    public function test_below_min_margin_flag(): void {
        $rule = $this->rule(['markup_percent' => '5', 'min_margin' => '20']);
        $this->assertTrue($this->pricing->suggest($rule, '100.00')['below_min']);
    }

    public function test_resolve_prefers_supplier_specific_rule(): void {
        $this->rule(['name' => 'global', 'markup_percent' => '10']);
        $specific = $this->rule(['name' => 'sup', 'supplier_id' => $this->supplier->id, 'markup_percent' => '40']);

        $resolved = $this->pricing->resolveRule($this->organization->id, $this->supplier->id, null);
        $this->assertSame($specific->id, $resolved?->id);
    }

    public function test_rule_matches_linked_article_category(): void {
        // MVP-604: Regel-Warengruppe matcht auch die Kategorie des
        // verknüpften Artikels, nicht nur die Katalog-Kategorie.
        $this->rule(['name' => 'global', 'markup_percent' => '10']);
        $specific = $this->rule(['name' => 'kabel', 'category' => 'Kabel', 'markup_percent' => '40']);

        $resolved = $this->pricing->resolveRule($this->organization->id, $this->supplier->id, null, 'Kabel');
        $this->assertSame($specific->id, $resolved?->id);

        // Über den Katalogartikel: Kategorie kommt vom verknüpften Artikel.
        $article = Article::factory()->create([
            'organization_id' => $this->organization->id, 'category' => 'Kabel', 'purchasable' => true,
        ]);
        $item = $this->item();
        app(CatalogLinkService::class)->link($item, $article);

        $suggestion = $this->pricing->suggestForItem($item->fresh());
        $this->assertSame($specific->id, $suggestion['rule']->id ?? null);
    }

    public function test_assembly_minutes_add_labour_to_suggestion(): void {
        // MVP-602: Montagezeit × Kalkulationsstundensatz auf den Materialpreis.
        $this->organization->forceFill([
            'settings' => array_merge((array) $this->organization->settings, [
                'invoicing' => ['assembly_hourly_rate' => '60'],
            ]),
        ])->save();
        $this->rule(['markup_percent' => '50']);

        $article = Article::factory()->create([
            'organization_id' => $this->organization->id, 'purchasable' => true,
            'assembly_minutes' => '30.00',
        ]);
        $item = $this->item('100.0000');
        app(CatalogLinkService::class)->link($item, $article);

        $suggestion = $this->pricing->suggestForItem($item->fresh());
        // 100 × 1,5 = 150 Material + 0,5 h × 60 € = 30 € Lohn.
        $this->assertSame('180.00', $suggestion['price']);
        $this->assertSame('30.00', $suggestion['labour'] ?? null);
        // Marge bleibt materialbezogen.
        $this->assertSame(33.3, $suggestion['margin']);
    }

    public function test_apply_to_article_sets_sale_price(): void {
        $rule = $this->rule(['target_margin' => '30']);
        $item = $this->item('112.0000');
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'purchasable' => true]);
        app(CatalogLinkService::class)->link($item, $article);

        $this->pricing->applyToArticle($item->fresh());

        $this->assertSame('160.0000', $article->fresh()->default_sale_price?->getAmount());
    }

    public function test_apply_throws_when_not_linked(): void {
        $this->rule(['target_margin' => '30']);
        $item = $this->item('112.0000');

        $this->expectException(RuntimeException::class);
        $this->pricing->applyToArticle($item);
    }

    public function test_store_rule_route(): void {
        $this->actingAs($this->admin)->post(route('pricing-margin-rules.store'), [
            'name' => 'Standard 30', 'target_margin' => '30', 'rounding' => 'up_0_99', 'priority' => '5',
        ])->assertRedirect();

        $this->assertDatabaseHas('pricing_margin_rules', [
            'organization_id' => $this->organization->id, 'name' => 'Standard 30', 'rounding' => 'up_0_99',
        ]);
    }

    public function test_apply_price_route_updates_article(): void {
        $this->rule(['target_margin' => '30']);
        $item = $this->item('112.0000');
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'purchasable' => true]);
        app(CatalogLinkService::class)->link($item, $article);

        $this->actingAs($this->admin)
            ->post(route('supplier-catalogs.items.apply-price', $item))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('160.0000', $article->fresh()->default_sale_price?->getAmount());
    }
}
