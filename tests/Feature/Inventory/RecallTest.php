<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecallTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\{RecallItemStatus, RecallStatus, SerialStatus};
use App\Mail\RecallNoticeMail;
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Claims\ClaimCase;
use App\Models\Customer\Customer;
use App\Models\Document\DocumentDispatch;
use App\Models\Inventory\{Recall, RecallItem, StockDelivery, StockSerial, Warehouse};
use App\Models\Manufacturing\ManufacturingOrder;
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/** MVP-921/922: Rückrufaktion — Eingrenzung, Betroffene, Sperre, Stand je Kunde, Anschreiben, Rücklauf, Portal. */
final class RecallTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private User $admin;

    private ArticleVariant $variant;

    private Warehouse $warehouse;

    private ManufacturingOrder $orderA;

    private Customer $alpha;

    private Customer $beta;

    private StockSerial $shipped;

    private StockSerial $inStock;

    private StockSerial $otherBatch;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Heizlüfter']);
        $this->variant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $article->id, 'sku' => 'HL-1']);
        $this->orderA = ManufacturingOrder::factory()->create(['organization_id' => $this->organization->id, 'number' => 'FA-100']);
        $orderB = ManufacturingOrder::factory()->create(['organization_id' => $this->organization->id, 'number' => 'FA-200']);
        $this->alpha = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Alpha GmbH', 'email' => 'einkauf@alpha.test']);
        $this->beta = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Beta KG', 'email' => null]);

        $d1 = $this->delivery($this->orderA, $this->alpha, '2026-09-01 10:00:00');
        $this->delivery($orderB, $this->beta, '2026-09-10 10:00:00');
        $this->shipped = $this->serial('SN-1', $this->orderA, SerialStatus::Shipped, $d1);
        $this->inStock = $this->serial('SN-2', $this->orderA, SerialStatus::InStock);
        $this->otherBatch = $this->serial('SN-3', $orderB, SerialStatus::InStock);
    }

    private function delivery(ManufacturingOrder $order, Customer $customer, string $at): StockDelivery {
        return StockDelivery::query()->create([
            'organization_id' => $this->organization->id, 'manufacturing_order_id' => $order->id, 'article_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id, 'customer_id' => $customer->id, 'quantity' => '1', 'unit' => 'Stk', 'name_snapshot' => 'Heizlüfter',
            'stock_status' => 'delivered', 'facturation_status' => 'pending', 'delivered_at' => Carbon::parse($at),
        ]);
    }

    private function serial(string $no, ManufacturingOrder $order, SerialStatus $status, ?StockDelivery $delivery = null): StockSerial {
        return StockSerial::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $this->variant->article_id, 'article_variant_id' => $this->variant->id,
            'serial_no' => $no, 'status' => $status->value, 'manufacturing_order_id' => $order->id,
            'stock_delivery_id' => $delivery?->id, 'customer_id' => $delivery?->customer_id, 'warehouse_id' => $delivery === null ? $this->warehouse->id : null,
        ]);
    }

    /** @param array<string, mixed> $extra */
    private function create(array $extra = []): Recall {
        $this->actingAs($this->admin)->post(route('recalls.store'), $extra + [
            'article_variant_id' => $this->variant->sqid, 'kind' => 'safety', 'title' => 'Überhitzung', 'reason' => 'Thermosicherung fehlerhaft.',
            'manufacturing_orders' => 'FA-100', 'is_blocking_stock' => '1',
        ])->assertRedirect();

        return Recall::query()->latest('id')->firstOrFail();
    }

    public function test_draft_previews_the_scope_and_activation_fixes_items_and_blocks_stock(): void {
        $recall = $this->create();
        $this->assertMatchesRegularExpression('/^RUF-\d{4}-\d{4}$/', (string) $recall->number);
        $this->assertSame([$this->orderA->id], $recall->manufacturing_order_ids);
        $this->actingAs($this->admin)->get(route('recalls.show', $recall))->assertOk()->assertSee('Alpha GmbH')->assertDontSee('Beta KG');

        $this->actingAs($this->admin)->post(route('recalls.transition', $recall), ['status' => 'active'])->assertSessionHas('success');

        $item = RecallItem::query()->sole();
        $this->assertSame($this->alpha->id, $item->customer_id);
        $this->assertSame($this->shipped->id, $item->stock_serial_id);
        $this->assertSame(SerialStatus::Blocked, $this->inStock->fresh()->status);
        $this->assertSame($recall->number, $this->inStock->fresh()->blocked_reason);
        $this->assertSame(SerialStatus::InStock, $this->otherBatch->fresh()->status);
        $this->assertSame(SerialStatus::Shipped, $this->shipped->fresh()->status);

        $status = route('recalls.items.status', [$recall, $item]);
        $this->actingAs($this->admin)->post($status, ['status' => 'notified'])->assertSessionHas('success');
        $this->actingAs($this->admin)->post($status, ['status' => 'returned', 'note' => 'per Paket'])->assertSessionHas('success');
        $this->actingAs($this->admin)->post($status, ['status' => 'notified'])->assertSessionHas('error');
        $item->refresh();
        $this->assertSame(RecallItemStatus::Returned, $item->status);
        $this->assertNotNull($item->notified_at);
        $this->assertSame('per Paket', $item->note);

        $this->actingAs($this->admin)->post(route('recalls.transition', $recall), ['status' => 'cancelled'])->assertSessionHas('success');
        $this->assertSame(RecallStatus::Cancelled, $recall->fresh()->status);
        $this->assertSame(SerialStatus::InStock, $this->inStock->fresh()->status);
    }

    public function test_delivery_period_narrows_the_scope_in_local_days(): void {
        $recall = $this->create(['manufacturing_orders' => '', 'delivered_from' => '2026-09-05', 'delivered_until' => '2026-09-30', 'is_blocking_stock' => '0']);
        $this->actingAs($this->admin)->post(route('recalls.transition', $recall), ['status' => 'active'])->assertSessionHas('success');

        $this->assertSame([$this->beta->id], RecallItem::query()->pluck('customer_id')->all());
        $this->assertSame(SerialStatus::InStock, $this->inStock->fresh()->status);
    }

    public function test_recalls_need_their_permissions(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($user)->get(route('recalls.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('recalls.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('recalls.create'))->assertOk()->assertSee('HL-1');
    }

    public function test_customers_are_notified_with_delivery_record_and_see_the_recall_in_the_portal(): void {
        Mail::fake();
        $recall = $this->create(['manufacturing_orders' => '', 'customer_message' => 'Bitte Gerät nicht mehr verwenden.']);
        $this->actingAs($this->admin)->post(route('recalls.transition', $recall), ['status' => 'active']);

        $this->actingAs($this->admin)->post(route('recalls.notify', $recall))->assertSessionHas('success')->assertSessionHas('warning', fn (string $w): bool => str_contains($w, 'Beta KG'));

        Mail::assertQueued(RecallNoticeMail::class, fn (RecallNoticeMail $m): bool => $m->hasTo('einkauf@alpha.test') && $m->customerId === $this->alpha->id);
        $dispatch = DocumentDispatch::query()->sole();
        $this->assertSame(Recall::DOCUMENT_KIND, $dispatch->document_kind);
        $this->assertSame($recall->id, $dispatch->document_id);
        $this->assertSame(RecallItemStatus::Notified, RecallItem::query()->where('customer_id', $this->alpha->id)->firstOrFail()->status);
        $this->assertSame(RecallItemStatus::Open, RecallItem::query()->where('customer_id', $this->beta->id)->firstOrFail()->status);
        $this->actingAs($this->admin)->get(route('recalls.show', $recall))->assertOk()->assertSee('einkauf@alpha.test');

        $this->allowPortal($this->alpha);
        $portalUser = User::factory()->kunde((int) $this->alpha->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);
        $this->actingAs($portalUser, 'customer')->get(route('customer.dashboard'))->assertOk()->assertSee('Überhitzung')->assertSee('Bitte Gerät nicht mehr verwenden.');

        $mail = new RecallNoticeMail($recall->id, $this->alpha->id, $dispatch->id);
        $mail->assertSeeInText('SN-1');
    }

    public function test_return_opens_a_claim_for_the_item(): void {
        $recall = $this->create();
        $this->actingAs($this->admin)->post(route('recalls.transition', $recall), ['status' => 'active']);
        $item = RecallItem::query()->sole();

        $response = $this->actingAs($this->admin)->post(route('recalls.items.claim', [$recall, $item]));

        $claim = ClaimCase::query()->sole();
        $response->assertRedirect(route('claims.show', $claim));
        $this->assertSame($this->alpha->id, $claim->customer_id);
        $this->assertSame($this->shipped->id, $claim->stock_serial_id);
        $this->assertSame($claim->id, $item->fresh()->claim_case_id);
        $this->actingAs($this->admin)->post(route('recalls.items.claim', [$recall, $item]))->assertRedirect(route('claims.show', $claim));
        $this->assertSame(1, ClaimCase::query()->count());
    }
}
