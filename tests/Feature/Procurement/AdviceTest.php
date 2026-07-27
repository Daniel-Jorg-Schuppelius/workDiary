<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AdviceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Enums\Procurement\{AdviceStatus, PurchaseOrderStatus};
use App\Models\{Article, ArticleVariant, Supplier, Warehouse};
use App\Services\Procurement\{AdviceService, PurchaseOrderService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lieferavis/ASN (Feature 048, E4): Avis erfassen und daraus den Wareneingang
 * gegen die Bestellzeilen buchen.
 */
final class AdviceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_announce_and_receive_books_goods_receipt(): void {
        $this->setUpOrganization();
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk']);
        ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $article->id, 'is_default' => true, 'option_signature' => 'default']);

        $orders = app(PurchaseOrderService::class);
        $po = $orders->createDraft($this->organization, $supplier, $warehouse);
        $line = $orders->addLine($po, $article, '10', ['unit_price' => '2']);
        $orders->submit($po);

        $advice = app(AdviceService::class)->announce($po, [['line' => $line, 'qty' => '4']], ['reference' => 'ASN-1']);
        $this->assertSame(AdviceStatus::Announced, $advice->status);
        $this->assertSame(1, $advice->lines()->count());

        app(AdviceService::class)->receive($advice);

        $this->assertSame(AdviceStatus::Received, $advice->fresh()->status);
        $this->assertSame('4.0000', $line->fresh()->received_qty?->getNumericValue());
        $this->assertSame(PurchaseOrderStatus::PartiallyReceived, $po->fresh()->status);
    }

    public function test_empty_advice_throws(): void {
        $this->setUpOrganization();
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $po = app(PurchaseOrderService::class)->createDraft($this->organization, $supplier, $warehouse);

        $this->expectException(RuntimeException::class);
        app(AdviceService::class)->announce($po, []);
    }
}
