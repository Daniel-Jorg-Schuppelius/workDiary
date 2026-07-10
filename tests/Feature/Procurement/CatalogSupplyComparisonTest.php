<?php
/*
 * Created on   : Sat Jun 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogSupplyComparisonTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Models\{Article, ArticleSupply, Supplier, User};
use App\Services\Procurement\SupplySourceComparator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 050: Lieferantenvergleich und Bezugsquellenempfehlung (günstigste
 * Quelle mit Preis) sowie das Setzen der bevorzugten Bezugsquelle.
 */
final class CatalogSupplyComparisonTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Article $article;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id, 'purchasable' => true]);
    }

    private function supply(?string $price, int $leadTime = 5, bool $preferred = false): ArticleSupply {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);

        return ArticleSupply::query()->create([
            'organization_id' => $this->organization->id, 'article_id' => $this->article->id,
            'supplier_id' => $supplier->id, 'supplier_sku' => 'S-' . $supplier->id,
            'purchase_price' => $price, 'currency' => 'EUR',
            'moq' => '1', 'pack_size' => '1', 'lead_time_days' => $leadTime, 'is_preferred' => $preferred,
        ]);
    }

    public function test_recommends_cheapest_supply_with_price(): void {
        $this->supply('10.0000');
        $cheap = $this->supply('8.0000');
        $this->supply(null); // ohne Preis

        $this->assertSame($cheap->id, app(SupplySourceComparator::class)->recommend($this->article)?->id);
    }

    public function test_sorts_priced_before_unpriced_then_by_price(): void {
        $this->supply(null);
        $this->supply('10.0000');
        $cheap = $this->supply('8.0000');

        $sorted = app(SupplySourceComparator::class)->forArticle($this->article);
        $this->assertSame($cheap->id, $sorted->first()->id);
        $this->assertNull($sorted->last()->purchase_price);
    }

    public function test_set_preferred_route_marks_single_supply(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $a = $this->supply('10.0000', preferred: true);
        $b = $this->supply('8.0000');

        $this->actingAs($admin)
            ->post(route('articles.supplies.prefer', [$this->article, $b]))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertFalse($a->fresh()->is_preferred);
        $this->assertTrue($b->fresh()->is_preferred);
    }
}
