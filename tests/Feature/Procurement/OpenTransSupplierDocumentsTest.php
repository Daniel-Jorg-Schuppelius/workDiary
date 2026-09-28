<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenTransSupplierDocumentsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Procurement;

use App\Models\Article\{Article, ArticleVariant};
use App\Models\Inventory\Warehouse;
use App\Models\Platform\User;
use App\Models\Procurement\{PurchaseOrder, PurchaseOrderAdvice};
use App\Models\Supplier\Supplier;
use App\Services\Procurement\PurchaseOrderService;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use DateTimeImmutable;
use ERechnungToolkit\Entities\{DespatchAdvice, DespatchLine, OrderResponse, OrderResponseLine, Party};
use ERechnungToolkit\Enums\{DespatchAdviceProfile, UnitCode};
use ERechnungToolkit\Generators\{OpenTransDispatchNotificationGenerator, OpenTransOrderResponseGenerator};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-964: openTRANS-Auftragsbestätigung und -Lieferschein an der Bestellung. */
final class OpenTransSupplierDocumentsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private PurchaseOrder $order;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'purchasable' => true, 'base_unit' => 'Stk']);
        ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $article->id, 'is_default' => true, 'option_signature' => 'default']);
        $this->order = app(PurchaseOrderService::class)->createDraft($this->organization, $supplier, $warehouse);
        $this->actingAs($this->admin)->post(route('purchase-orders.lines.add', $this->order), ['article' => $article->sqid, 'qty' => '10', 'unit_price' => '2'])->assertRedirect();
        $this->actingAs($this->admin)->post(route('purchase-orders.submit', $this->order))->assertRedirect();
        $this->order->refresh();
    }

    private function upload(string $name, string $xml): UploadedFile {
        return UploadedFile::fake()->createWithContent($name, $xml)->mimeType('application/xml');
    }

    public function test_order_confirmation_stores_confirmed_values_and_reports_deviations(): void {
        $response = new OrderResponse((string) $this->order->number, new DateTimeImmutable('2026-10-01'), new Party('Wir'), new Party('Lieferant'), CurrencyCode::Euro, supplierOrderId: 'AB-100');
        $response->addLine(new OrderResponseLine('1', 8, UnitCode::PIECE, 'Artikel', Money::of('2.10', CurrencyCode::Euro), deliveryDate: new DateTimeImmutable('2026-10-08')));
        $xml = (new OpenTransOrderResponseGenerator)->generate($response);

        $this->actingAs($this->admin)->post(route('purchase-orders.confirmation.import', $this->order), ['confirmation_xml' => $this->upload('ab.xml', $xml)])
            ->assertSessionHas('success')->assertSessionHas('warning');

        $this->order->refresh();
        $this->assertSame('AB-100', $this->order->supplier_order_ref);
        $this->assertNotNull($this->order->supplier_confirmed_at);
        $line = $this->order->lines()->sole();
        $this->assertSame('8.0000', (string) $line->confirmed_qty);
        $this->assertSame('2.1000', (string) $line->confirmed_unit_price);
        $this->assertSame('2026-10-08', $line->confirmed_delivery_on?->toDateString());
        $this->actingAs($this->admin)->get(route('purchase-orders.show', $this->order))->assertOk()->assertSeeText(__('procurement.confirmation.column'));

        $foreign = new OrderResponse('BE-FREMD', new DateTimeImmutable('2026-10-01'), new Party('Wir'), new Party('Lieferant'));
        $foreign->addLine(new OrderResponseLine('1', 1, UnitCode::PIECE));
        $this->actingAs($this->admin)->post(route('purchase-orders.confirmation.import', $this->order), ['confirmation_xml' => $this->upload('x.xml', (new OpenTransOrderResponseGenerator)->generate($foreign))])
            ->assertSessionHas('error');
    }

    public function test_dispatch_notification_becomes_an_advice(): void {
        $advice = new DespatchAdvice('LS-1', new DateTimeImmutable('2026-10-05'), new Party('Lieferant'), new Party('Wir'), DespatchAdviceProfile::OPENTRANS_DISPATCHNOTIFICATION, (string) $this->order->number, actualDeliveryDate: new DateTimeImmutable('2026-10-06'));
        $advice->addLine(new DespatchLine('1', 6, UnitCode::PIECE, 'Artikel', orderLineId: '1'));
        $xml = (new OpenTransDispatchNotificationGenerator)->generate($advice);

        $this->actingAs($this->admin)->post(route('purchase-orders.advices.import', $this->order), ['advice_xml' => $this->upload('ls.xml', $xml)])->assertSessionHas('success');

        $imported = PurchaseOrderAdvice::query()->sole();
        $this->assertSame('LS-1', $imported->reference);
        $this->assertSame('2026-10-06', $imported->expected_at?->toDateString());
    }
}
