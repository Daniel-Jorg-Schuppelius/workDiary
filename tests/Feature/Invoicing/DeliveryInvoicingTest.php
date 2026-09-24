<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliveryInvoicingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Article\ArticleType;
use App\Enums\Manufacturing\DeliveryFacturationStatus;
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Customer\Customer;
use App\Models\Inventory\{StockDelivery, Warehouse};
use App\Models\Invoicing\Invoice;
use App\Models\Manufacturing\ManufacturingOrder;
use App\Models\Platform\{Organization, User};
use App\Services\Invoicing\{DeliveryInvoicingService, InvoiceGenerator, InvoiceIssueService};
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Fertigungsauslieferungen lokal abrechnen (Feature 160, MVP-858):
 * Teilauslieferungen gemeinsam, genau eine aktive Zuordnung je Auslieferung
 * (Dienst und Datenbank), Freigabe bei Entfernen/Verwerfen/Vollstorno mit
 * erhaltener Historie, keine Freigabe durch Gutschrift, fremde/externe
 * Quellen abgewiesen, Lagerbestand unberührt.
 */
final class DeliveryInvoicingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    private ArticleVariant $variant;

    private ManufacturingOrder $order;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'currency' => 'EUR']);
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Regal Eiche', 'type' => ArticleType::Finished->value, 'base_unit' => 'Stk', 'default_sale_price' => '250.0000', 'currency' => 'EUR', 'sellable' => true]);
        $this->variant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $article->id, 'sku' => 'REGAL-180', 'name' => '180 cm', 'sale_price' => '250.0000', 'currency' => 'EUR']);
        $this->order = ManufacturingOrder::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'article_variant_id' => $this->variant->id, 'number' => 'FA-2026-0007']);
    }

    /** @param  array<string, mixed>  $overrides */
    private function delivery(string $qty, array $overrides = []): StockDelivery {
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);

        return StockDelivery::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'manufacturing_order_id' => $this->order->id,
            'article_variant_id' => $this->variant->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $this->customer->id,
            'quantity' => $qty,
            'unit' => 'Stk',
            'sku_snapshot' => 'REGAL-180',
            'name_snapshot' => 'Regal Eiche 180 cm',
            'unit_price_snapshot' => '250.0000',
            'currency' => 'EUR',
            'stock_status' => 'delivered',
            'facturation_status' => DeliveryFacturationStatus::Pending->value,
            'facturation_target' => 'workdiary',
            'delivered_at' => now(),
        ], $overrides));
    }

    private function draft(?Customer $customer = null): Invoice {
        return app(InvoiceGenerator::class)->emptyDraft($customer ?? $this->customer);
    }

    private function service(): DeliveryInvoicingService {
        return app(DeliveryInvoicingService::class);
    }

    public function test_two_partial_deliveries_are_billed_together_with_source_bound_quantity_and_delivery_price(): void {
        $first = $this->delivery('1');
        $second = $this->delivery('1', ['unit_price_snapshot' => '240.0000']);
        $draft = $this->draft();

        $this->actingAs($this->admin)->get(route('invoices.deliveries.form', $draft))->assertOk()->assertSee('Regal Eiche 180 cm');
        $this->actingAs($this->admin)->post(route('invoices.deliveries.attach', $draft), ['delivery_ids' => [$first->sqid, $second->sqid]])
            ->assertRedirect(route('invoices.show', $draft))->assertSessionHasNoErrors();

        $draft->refresh();
        $this->assertSame(2, $draft->items()->count());
        $items = $draft->items()->orderBy('position')->get()->all();
        $this->assertCount(2, $items);
        $this->assertSame('1.000', (string) $items[0]->quantity);
        $this->assertSame('250.0000', $items[0]->unit_price?->getAmount());
        $this->assertSame('240.0000', $items[1]->unit_price?->getAmount());
        $this->assertSame($first->id, $items[0]->stock_delivery_id);
        $this->assertSame('REGAL-180', $items[0]->article_number_snapshot);
        $this->assertSame($this->variant->id, $items[0]->article_variant_id);
        $this->assertStringContainsString('FA-2026-0007', (string) $items[0]->description);
        $this->assertSame('490.00', $draft->subtotal?->getAmount());
        $this->assertSame($items[0]->id, $first->refresh()->invoice_item_id);
        $this->assertSame(DeliveryFacturationStatus::Pending, $first->facturation_status);

        $this->actingAs($this->admin)->get(route('invoices.show', $draft))->assertOk()->assertSee('FA-2026-0007');
        $this->actingAs($this->admin)->get(route('manufacturing-orders.show', $this->order))->assertOk()->assertSee($draft->number);
    }

    public function test_a_delivery_can_only_be_reserved_once_and_the_existing_draft_is_named(): void {
        $delivery = $this->delivery('2');
        $first = $this->draft();
        $this->service()->attach($first, [$delivery->id]);

        $second = $this->draft();
        try {
            $this->service()->attach($second, [$delivery->id]);
            $this->fail('Eine reservierte Auslieferung darf nicht zweimal übernommen werden.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString((string) $first->number, (string) $e->errors()['delivery_ids'][0]);
        }
        $this->assertSame(0, $second->items()->count(), 'Scheitern rollt die Position zurück.');
        $this->assertSame(1, $first->items()->count());

        // Datenbankebene: die zweite aktive Zuordnung scheitert am Unique, unabhängig vom Dienst.
        $item = $second->items()->create(['organization_id' => $this->organization->id, 'description' => 'x', 'quantity' => '1', 'unit' => 'Stk', 'unit_price' => '1.00', 'position' => 1]);
        $this->expectException(QueryException::class);
        DB::table('stock_deliveries')->where('id', $this->delivery('1')->id)->update(['invoice_item_id' => $first->items()->firstOrFail()->id]);
        unset($item);
    }

    public function test_removing_the_item_or_deleting_the_draft_releases_the_delivery(): void {
        $delivery = $this->delivery('1');
        $draft = $this->draft();
        $item = $this->service()->attach($draft, [$delivery->id])->firstOrFail();
        $this->assertNotNull($delivery->refresh()->invoice_item_id);

        $this->actingAs($this->admin)->delete(route('invoices.items.destroy', [$draft, $item]))->assertRedirect();
        $this->assertNull($delivery->refresh()->invoice_item_id);
        $this->assertSame(DeliveryFacturationStatus::Pending, $delivery->facturation_status);

        $again = $this->draft();
        $this->service()->attach($again, [$delivery->id]);
        $this->assertNotNull($delivery->refresh()->invoice_item_id);
        $this->actingAs($this->admin)->delete(route('invoices.destroy', $again))->assertRedirect();
        $this->assertNull($delivery->refresh()->invoice_item_id);
        $this->assertSame(0, Invoice::query()->whereKey($again->id)->count());
    }

    public function test_issue_marks_the_delivery_invoiced_and_only_a_full_cancellation_releases_it_with_history(): void {
        $delivery = $this->delivery('1');
        $draft = $this->draft();
        $item = $this->service()->attach($draft, [$delivery->id])->firstOrFail();

        app(InvoiceIssueService::class)->issue($draft);
        $this->assertSame(DeliveryFacturationStatus::Invoiced, $delivery->refresh()->facturation_status);
        $this->assertSame($item->id, $delivery->invoice_item_id);

        // Gutschrift (nur zu bezahlten Rechnungen): keine Freigabe.
        $draft->refresh()->forceFill(['status' => Invoice::STATUS_PAID])->saveQuietly();
        $credit = app(InvoiceGenerator::class)->creditNoteFor($draft->refresh(), $this->admin->id);
        $this->assertSame(DeliveryFacturationStatus::Invoiced, $delivery->refresh()->facturation_status);
        $this->assertNull($credit->items()->firstOrFail()->stock_delivery_id);

        // Vollstorno: frei zur erneuten Abrechnung, Herkunft am alten und am Stornoposten sichtbar.
        $draft->refresh()->forceFill(['status' => Invoice::STATUS_ISSUED])->saveQuietly();
        $cancellation = app(InvoiceGenerator::class)->cancellationFor($draft->refresh(), 'Test', $this->admin->id);
        $this->assertNull($delivery->refresh()->invoice_item_id);
        $this->assertSame(DeliveryFacturationStatus::Pending, $delivery->facturation_status);
        $this->assertSame($delivery->id, $item->refresh()->stock_delivery_id);
        $this->assertSame($delivery->id, $cancellation->items()->firstOrFail()->stock_delivery_id);
        $this->assertSame(2, $delivery->invoiceItems()->count());

        $rebill = $this->draft();
        $this->service()->attach($rebill, [$delivery->id]);
        $this->assertSame(1, $rebill->items()->count());
        $this->actingAs($this->admin)->get(route('manufacturing-orders.show', $this->order))->assertOk()->assertSee($rebill->number);
    }

    public function test_foreign_external_currency_and_customer_mismatches_are_rejected_and_stock_is_untouched(): void {
        $draft = $this->draft();
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $wrongCustomer = $this->delivery('1', ['customer_id' => $otherCustomer->id]);
        $external = $this->delivery('1', ['facturation_target' => 'lexoffice']);
        $foreignCurrency = $this->delivery('1', ['currency' => 'CHF']);
        $notDelivered = $this->delivery('1', ['stock_status' => 'reserved']);
        $other = Organization::factory()->create();
        $foreign = StockDelivery::query()->create(['organization_id' => $other->id, 'article_variant_id' => $this->variant->id, 'warehouse_id' => Warehouse::factory()->create(['organization_id' => $other->id])->id, 'customer_id' => Customer::factory()->create(['organization_id' => $other->id])->id, 'quantity' => '1', 'unit' => 'Stk', 'name_snapshot' => 'Fremd', 'unit_price_snapshot' => '1.0000', 'currency' => 'EUR', 'stock_status' => 'delivered', 'facturation_status' => 'pending', 'facturation_target' => 'workdiary', 'delivered_at' => now()]);

        foreach ([$wrongCustomer, $external, $foreignCurrency, $notDelivered, $foreign] as $delivery) {
            try {
                $this->service()->attach($draft, [$delivery->id]);
                $this->fail('Auslieferung ' . $delivery->id . ' hätte abgewiesen werden müssen.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('delivery_ids', $e->errors());
            }
        }
        $this->assertSame(0, $draft->items()->count());
        $this->assertSame(0, DB::table('stock_movements')->count(), 'Fakturierung bucht keinen Lagerbestand.');

        // Fremde Auslieferung über die Oberfläche: Sqid einer anderen Organisation wird still ignoriert → Feldfehler.
        $this->actingAs($this->admin)->post(route('invoices.deliveries.attach', $draft), ['delivery_ids' => [$foreign->sqid]])->assertSessionHasErrors('delivery_ids');
    }

    public function test_deliveries_require_the_inventory_module_and_manufacturing_read_rights(): void {
        $draft = $this->draft();
        $viewer = $this->userWithRole('buchhaltung');
        $this->actingAs($viewer)->get(route('invoices.deliveries.form', $draft))->assertForbidden();
        $this->actingAs($viewer)->post(route('invoices.deliveries.attach', $draft), ['delivery_ids' => ['x']])->assertForbidden();
    }
}
