<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseOrderReconcileTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Models\{Article, ArticleVariant, PurchaseOrder, Supplier, User, Warehouse};
use App\Services\Procurement\{PurchaseOrderService, UglInvoiceReconciler};
use CommonToolkit\Enums\CurrencyCode;
use Database\Seeders\PermissionsSeeder;
use DateTimeImmutable;
use ERechnungToolkit\Entities\{OrderLine, UglInvoice};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 050: Abgleich einer eingehenden UGL-Rechnung gegen die Bestellung.
 */
final class PurchaseOrderReconcileTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Supplier $supplier;
    private Article $article;
    private Warehouse $warehouse;
    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Pumpe', 'number' => 'ART-1']);
        ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $this->article->id,
            'is_default' => true, 'option_signature' => 'default',
        ]);
    }

    private function makeOrder(): PurchaseOrder {
        $orders = app(PurchaseOrderService::class);
        $po = $orders->createDraft($this->organization, $this->supplier, $this->warehouse);
        $orders->addLine($po, $this->article, '10', ['unit_price' => '120', 'supplier_sku' => 'SUP-1']);
        $orders->submit($po);

        return $po->fresh(['lines.article', 'lines.variant']);
    }

    private function invoice(float $qty = 10, float $net = 1200.0, string $sku = 'SUP-1'): UglInvoice {
        return new UglInvoice(
            number: 'RE-1', documentType: UglInvoice::TYPE_INVOICE,
            date: new DateTimeImmutable('2026-06-28'), currency: CurrencyCode::Euro,
            grossTotal: $net * 1.19, vatAmount: $net * 0.19, netTotal: $net,
            lines: [new OrderLine(id: '1', quantity: $qty, unitCode: \ERechnungToolkit\Enums\UnitCode::PIECE,
                netAmount: $net, itemName: 'Pumpe', unitPrice: $qty > 0 ? $net / $qty : 0.0, sellersItemId: $sku)]
        );
    }

    public function test_reconcile_matches_order(): void {
        $result = app(UglInvoiceReconciler::class)->reconcile($this->makeOrder(), $this->invoice());

        $this->assertTrue($result['ok']);
        $this->assertSame('match', $result['lines'][0]['status']);
        $this->assertSame([], $result['missing']);
        $this->assertTrue($result['totals']['matches']);
    }

    public function test_reconcile_detects_quantity_mismatch(): void {
        $result = app(UglInvoiceReconciler::class)->reconcile($this->makeOrder(), $this->invoice(qty: 8, net: 960.0));

        $this->assertFalse($result['ok']);
        $this->assertSame('mismatch', $result['lines'][0]['status']);
        $this->assertFalse($result['totals']['matches']);
    }

    public function test_reconcile_flags_invoice_only_and_missing(): void {
        $result = app(UglInvoiceReconciler::class)->reconcile($this->makeOrder(), $this->invoice(sku: 'OTHER-9'));

        $this->assertSame('invoice_only', $result['lines'][0]['status']);
        $this->assertCount(1, $result['missing']);
        $this->assertSame('SUP-1', $result['missing'][0]['sku']);
        $this->assertFalse($result['ok']);
    }

    public function test_upload_renders_reconciliation_view(): void {
        $po = $this->makeOrder();
        $file = UploadedFile::fake()->createWithContent('invoice.ugl', $this->uglFile());

        $response = $this->actingAs($this->admin)
            ->post(route('purchase-orders.reconcile-invoice', $po), ['invoice_ugl' => $file]);

        $response->assertOk();
        $response->assertViewIs('purchase-orders.invoice-reconciliation');
        $response->assertSee('RE-2026-9');
    }

    public function test_upload_rejects_non_ugl_file(): void {
        $po = $this->makeOrder();
        $file = UploadedFile::fake()->createWithContent('garbage.ugl', 'kein ugl inhalt');

        $this->actingAs($this->admin)
            ->post(route('purchase-orders.reconcile-invoice', $po), ['invoice_ugl' => $file])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    /** Builds a minimal RGD + POA + END UGL invoice matching the order. */
    private function uglFile(): string {
        $alpha = fn (string $rec, int $from, int $to, string $v): string => substr_replace($rec, str_pad(substr($v, 0, $to - $from + 1), $to - $from + 1), $from - 1, $to - $from + 1);
        $num = fn (string $rec, int $from, int $to, float $v, int $d): string => substr_replace($rec, str_pad((string) (int) round($v * (10 ** $d)), $to - $from + 1, '0', STR_PAD_LEFT), $from - 1, $to - $from + 1);
        $blank = str_repeat(' ', 350);

        $rgd = $alpha($blank, 1, 3, 'RGD');
        $rgd = $alpha($rgd, 4, 13, 'RE-2026-9');
        $rgd = $alpha($rgd, 14, 15, 'RG');
        $rgd = $alpha($rgd, 16, 23, '20260628');
        $rgd = $alpha($rgd, 24, 26, 'EUR');
        $rgd = $num($rgd, 27, 37, 1428.00, 2);
        $rgd = $num($rgd, 38, 48, 228.00, 2);
        $rgd = $num($rgd, 54, 64, 1200.00, 2);

        $poa = $alpha($blank, 1, 3, 'POA');
        $poa = $num($poa, 4, 13, 1, 0);
        $poa = $alpha($poa, 24, 38, 'SUP-1');
        $poa = $num($poa, 39, 49, 10, 3);
        $poa = $alpha($poa, 50, 89, 'Pumpe');
        $poa = $num($poa, 130, 140, 120.00, 2);
        $poa = $num($poa, 142, 152, 1200.00, 2);
        $poa = $alpha($poa, 184, 186, 'ST');

        $end = $alpha($blank, 1, 3, 'END');

        return implode("\r\n", [$rgd, $poa, $end]) . "\r\n";
    }
}
