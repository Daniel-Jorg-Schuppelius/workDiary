<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliveryNoteTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Billing;

use App\Enums\Billing\{DeliveryNoteStatus, DocumentRelationKind};
use App\Enums\Manufacturing\{DeliveryFacturationStatus, DeliveryStockStatus};
use App\Enums\Numbering\NumberScope;
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Billing\DeliveryNote;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Inventory\{StockDelivery, Warehouse};
use App\Models\Manufacturing\ManufacturingOrder;
use App\Models\Platform\{Organization, PluginSetting, User};
use App\Models\Sales\OrderConfirmation;
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\Easybill\Services\EasybillDocumentIssuer;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Billing\{DeliveryNoteService, DocumentRelations};
use App\Services\Inventory\InventoryLedger;
use App\Services\Invoicing\QuoteService;
use App\Services\Manufacturing\{DeliveryService, ManufacturingOrderService};
use App\Services\Numbering\NumberSequenceService;
use App\Services\Sales\OrderConfirmationService;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Psr\Http\Message\RequestInterface;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** Phase 145, P3 (MVP-1163/1164): Lieferschein als eigener Beleg. */
final class DeliveryNoteTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private Customer $customer;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-10-13 10:00:00');
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
        $this->user = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->org->id, 'name' => 'Werft Nord GmbH']);
    }

    private function confirmedOrder(): OrderConfirmation {
        $service = app(QuoteService::class);
        $quote = $service->create(['customer_id' => $this->customer->id, 'valid_until' => now()->addWeeks(2)->toDateString()], [
            ['description' => 'Pumpe P-200', 'quantity' => 3, 'unit' => 'Stk', 'unit_price' => 500, 'tax_rate' => '19.00'],
            ['description' => 'Montage', 'quantity' => 1, 'unit' => 'Psch', 'unit_price' => 300, 'tax_rate' => '19.00'],
        ], $this->user);
        $quote = $service->approve($quote, $this->user);
        ['quote' => $quote, 'acceptance_token' => $token] = $service->send($quote, $this->user);
        $quote = $service->accept($quote, null, $token);
        $confirmations = app(OrderConfirmationService::class);

        return $confirmations->confirm($confirmations->fromQuote($quote, $this->user), $this->user);
    }

    private function noteFor(OrderConfirmation $confirmation): DeliveryNote {
        $this->actingAs($this->user)->post(route('order-confirmations.delivery-note.store', $confirmation))->assertRedirect();

        return DeliveryNote::query()->where('order_confirmation_id', $confirmation->id)->latest('id')->firstOrFail();
    }

    /** @param array<string, mixed> $body */
    private static function reply(array $body, int $status = 200): \Closure {
        return static fn (): Psr7Response => FakePluginHttp::response($body, $status);
    }

    public function test_order_confirmation_is_delivered_in_parts(): void {
        $confirmation = $this->confirmedOrder();

        $first = $this->noteFor($confirmation);
        $this->assertSame(DeliveryNoteStatus::Draft, $first->status);
        $this->assertSame(['Pumpe P-200', 'Montage'], $first->items->pluck('description')->all());
        [$pump, $assembly] = $first->items->all();

        $this->actingAs($this->user)->get(route('delivery-notes.edit', $first))->assertOk()->assertSee('Pumpe P-200');
        $this->actingAs($this->user)->put(route('delivery-notes.update', $first), [
            'quantities' => [$pump->sqid => '2', $assembly->sqid => '0'],
            'delivered_on' => '2026-10-14',
        ])->assertRedirect(route('delivery-notes.show', $first));
        $this->actingAs($this->user)->post(route('delivery-notes.deliver', $first))->assertRedirect(route('delivery-notes.show', $first));

        $first->refresh();
        $this->assertSame(DeliveryNoteStatus::Delivered, $first->status);
        $this->assertSame('LS-2026-0001', $first->number);
        $this->assertSame('2026-10-14', $first->delivered_on?->toDateString());
        $this->assertSame(['Pumpe P-200'], $first->items->pluck('description')->all(), '0 streicht die Position.');

        // Der zweite Lieferschein übernimmt nur, was noch offen ist.
        $second = $this->noteFor($confirmation);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(['1.0000', '1.0000'], $second->items->pluck('quantity')->map(fn ($q): string => (string) $q)->all());
        app(DeliveryNoteService::class)->deliver($second, $this->user);

        $this->actingAs($this->user)->post(route('order-confirmations.delivery-note.store', $confirmation))
            ->assertSessionHas('error', __('billing.delivery_note.error.fully_delivered'));

        $this->actingAs($this->user)->get(route('delivery-notes.show', $first))->assertOk()->assertSee('LS-2026-0001')->assertSee($confirmation->number);
        $pdf = $this->actingAs($this->user)->get(route('delivery-notes.pdf', $first));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());
        $this->actingAs($this->user)->get(route('delivery-notes.mail.form', $first))->assertOk();

        $relations = collect(app(DocumentRelations::class)->of($confirmation))->keyBy(fn (array $r): string => (string) $r['node']->number);
        $this->assertSame(DocumentRelationKind::FollowUp, $relations['LS-2026-0001']['kind'] ?? null);
        $this->assertArrayHasKey('LS-2026-0002', $relations->all());

        $this->actingAs($this->user)
            ->withSession(['ui.daterange.preset' => 'custom', 'ui.daterange.from' => '2026-10-01', 'ui.daterange.to' => '2026-10-31'])
            ->get(route('billing.feed', ['tab' => 'other']))
            ->assertOk()
            ->assertSee('LS-2026-0002');

        $this->actingAs($this->user)->post(route('delivery-notes.cancel', $second))->assertRedirect();
        $this->assertSame(DeliveryNoteStatus::Cancelled, $second->refresh()->status);
    }

    public function test_draft_cannot_be_mailed_or_printed(): void {
        $note = $this->noteFor($this->confirmedOrder());

        $this->actingAs($this->user)->get(route('delivery-notes.pdf', $note))->assertStatus(409);
        $this->actingAs($this->user)->get(route('delivery-notes.mail.form', $note))->assertStatus(422);
    }

    public function test_manufacturing_delivery_brings_its_delivery_note(): void {
        [$order, $variant, $warehouse] = $this->manufacturingOrder();

        $delivery = app(DeliveryService::class)->deliver($variant, $warehouse, '2', $order, $this->customer, createdBy: $this->user->id);
        $note = $delivery->deliveryNoteItem()->firstOrFail()->deliveryNote()->firstOrFail();

        $this->assertSame(DeliveryNoteStatus::Delivered, $note->status);
        $this->assertSame('LS-2026-0001', $note->number);
        $this->assertSame($order->id, $note->manufacturing_order_id);
        $this->assertSame('2.0000', (string) $note->items->firstOrFail()->quantity);
        $this->actingAs($this->user)->post(route('delivery-notes.cancel', $note))->assertForbidden();
        $this->actingAs($this->user)->get(route('manufacturing-orders.show', $order))->assertOk()->assertSee('LS-2026-0001');
        $this->actingAs($this->user)->get(route('deliveries.index', ['q' => 'LS-2026-0001']))->assertOk()->assertSee('LS-2026-0001');

        $relations = collect(app(DocumentRelations::class)->of($order))->keyBy(fn (array $r): string => (string) $r['node']->number);
        $this->assertSame(DocumentRelationKind::FollowUp, $relations['LS-2026-0001']['kind'] ?? null);
    }

    public function test_delivery_without_delivery_note_keeps_its_old_number(): void {
        [$order, $variant, $warehouse] = $this->manufacturingOrder();
        $delivery = StockDelivery::query()->create([
            'organization_id' => $this->org->id, 'manufacturing_order_id' => $order->id, 'article_variant_id' => $variant->id,
            'warehouse_id' => $warehouse->id, 'customer_id' => $this->customer->id, 'quantity' => '1', 'unit' => 'Stk',
            'name_snapshot' => 'Altbestand', 'stock_status' => DeliveryStockStatus::Delivered, 'facturation_status' => DeliveryFacturationStatus::Pending,
            'delivered_at' => now()->subYear(),
        ]);

        $this->actingAs($this->user)->get(route('manufacturing-orders.deliveries.pdf', [$order, $delivery]))->assertRedirect();

        $note = $delivery->deliveryNoteItem()->firstOrFail()->deliveryNote()->firstOrFail();
        $this->assertSame('LS-' . str_pad((string) $delivery->id, 6, '0', STR_PAD_LEFT), $note->number);
        $this->assertSame(DeliveryNoteStatus::Delivered, $note->status);
    }

    public function test_lexoffice_numbers_the_delivery_note(): void {
        $this->lexoffice();
        app(NumberSequenceService::class)->setFormat($this->org, NumberScope::DeliveryNote, ['source' => LexofficePlugin::ID]);
        $note = $this->noteFor($this->confirmedOrder());
        $this->assertTrue(NumberSequenceService::isDraft($note->number));
        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/delivery-notes?finalize=true' => self::reply(['id' => 'lex-ls-7'], 201),
            'https://api.lexware.io/v1/delivery-notes/lex-ls-7' => self::reply(['voucherNumber' => 'LS0007']),
        ]);

        $this->actingAs($this->user)->post(route('delivery-notes.deliver', $note))->assertSessionHasNoErrors();

        $this->assertSame('LS0007', $note->refresh()->number);
        // DeliveryNotesEndpoint::create(…, finalize: true) und ::get(): ohne Vorgänger, ohne Preise, Body wie vorher roh.
        $this->assertSame(
            ['POST https://api.lexware.io/v1/delivery-notes?finalize=true', 'GET https://api.lexware.io/v1/delivery-notes/lex-ls-7'],
            array_map(static fn (array $entry): string => $entry['request']->getMethod() . ' ' . $entry['request']->getUri(), $fake->recorded()),
        );
        $body = json_decode((string) $fake->recorded()[0]['request']->getBody(), true);
        $this->assertSame(['Pumpe P-200', 'Montage'], array_column($body['lineItems'], 'name'));
        $this->assertEquals([
            'voucherDate' => now()->format('Y-m-d\\TH:i:s.vP'),
            'address' => ['contactId' => 'e5b7a6c4-1f2d-4c3b-9a8e-7d6c5b4a3f21'],
            'lineItems' => $note->items->map(static fn ($item): array => [
                'type' => 'custom',
                'name' => trim($item->description) . (filled($item->sku) ? ' (' . $item->sku . ')' : ''),
                'quantity' => (float) $item->quantity,
                'unitName' => $item->unit ?: (string) __('invoicing.unit_piece'),
            ])->all(),
            'taxConditions' => ['taxType' => 'net'],
            'shippingConditions' => ['shippingType' => 'delivery', 'shippingDate' => ($note->delivered_on ?? now())->copy()->startOfDay()->format('Y-m-d\\TH:i:s.vP')],
            'title' => (string) __('manufacturing.delivery_note.title'),
        ] + array_filter(['remark' => $note->note ?: null]), $body);
        $relations = collect(app(DocumentRelations::class)->of($note))->keyBy(fn (array $r): string => (string) $r['node']->number);
        $this->assertSame(DocumentRelationKind::Counterpart, $relations['LS0007']['kind'] ?? null);
    }

    public function test_easybill_delivery_refers_to_the_transferred_order(): void {
        PluginSetting::create(['organization_id' => $this->org->id, 'plugin_id' => EasybillPlugin::ID, 'enabled' => true, 'settings' => ['api_key' => 'k']]);
        ExternalReference::create(['organization_id' => $this->org->id, 'plugin_id' => EasybillPlugin::ID, 'external_type' => ExternalReference::TYPE_CONTACT,
            'referenceable_type' => $this->customer->getMorphClass(), 'referenceable_id' => $this->customer->id, 'external_id' => '501']);
        app(NumberSequenceService::class)->setFormat($this->org, NumberScope::DeliveryNote, ['source' => EasybillPlugin::ID]);
        $confirmation = $this->confirmedOrder();
        ExternalReference::create(['organization_id' => $this->org->id, 'plugin_id' => EasybillPlugin::ID, 'external_type' => EasybillDocumentIssuer::EXT_TYPE_ORDER,
            'referenceable_type' => $confirmation->getMorphClass(), 'referenceable_id' => $confirmation->id, 'external_id' => '8001', 'external_number' => 'AB-EB-1']);
        $note = $this->noteFor($confirmation);
        $fake = FakePluginHttp::fake([
            'https://api.easybill.de/rest/v1/customers/501' => self::reply(['id' => 501]),
            'https://api.easybill.de/rest/v1/documents/8002/done' => self::reply(['id' => 8002, 'number' => 'LS-EB-4']),
            'https://api.easybill.de/rest/v1/documents' => self::reply(['id' => 8002], 201),
        ]);

        $this->actingAs($this->user)->post(route('delivery-notes.deliver', $note))->assertSessionHasNoErrors();

        $this->assertSame('LS-EB-4', $note->refresh()->number);
        $fake->assertSent(function (RequestInterface $request): bool {
            $body = json_decode((string) $request->getBody(), true);

            return $request->getMethod() === 'POST' && data_get($body, 'type') === 'DELIVERY' && data_get($body, 'ref_id') === 8001
                && data_get($body, 'items.0.single_price_net') === null;
        });
    }

    public function test_rejected_transfer_keeps_the_stock_delivery_and_a_draft(): void {
        PluginSetting::create(['organization_id' => $this->org->id, 'plugin_id' => LexofficePlugin::ID, 'enabled' => true, 'settings' => []]);
        app(NumberSequenceService::class)->setFormat($this->org, NumberScope::DeliveryNote, ['source' => LexofficePlugin::ID]);
        [$order] = $this->manufacturingOrder();

        $this->actingAs($this->user)->post(route('manufacturing-orders.deliver', $order), ['quantity' => '1'])->assertSessionHas('warning');

        $delivery = StockDelivery::query()->where('manufacturing_order_id', $order->id)->firstOrFail();
        $note = $delivery->deliveryNoteItem()->firstOrFail()->deliveryNote()->firstOrFail();
        $this->assertSame(DeliveryNoteStatus::Draft, $note->status);
        $this->assertTrue(NumberSequenceService::isDraft($note->number));
    }

    private function lexoffice(): void {
        PluginSetting::create(['organization_id' => $this->org->id, 'plugin_id' => LexofficePlugin::ID, 'enabled' => true, 'settings' => ['api_key' => 'k']]);
        ExternalReference::create(['organization_id' => $this->org->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => ExternalReference::TYPE_CONTACT,
            'referenceable_type' => $this->customer->getMorphClass(), 'referenceable_id' => $this->customer->id, 'external_id' => 'e5b7a6c4-1f2d-4c3b-9a8e-7d6c5b4a3f21']);
    }

    /** @return array{0: ManufacturingOrder, 1: ArticleVariant, 2: Warehouse} */
    private function manufacturingOrder(): array {
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->org->id]);
        $article = Article::factory()->create(['organization_id' => $this->org->id]);
        $variant = ArticleVariant::factory()->create([
            'organization_id' => $this->org->id,
            'article_id' => $article->id,
            'is_default' => true,
            'option_signature' => 'default-' . $article->id,
        ]);
        $order = app(ManufacturingOrderService::class)->createDraft($this->org, $article, $variant, '5', 'Stk', ['warehouse_id' => $warehouse->id, 'customer_id' => $this->customer->id]);
        app(InventoryLedger::class)->receipt($variant, $warehouse, '10');

        return [$order, $variant, $warehouse];
    }

    /** Sicherheitsaudit 2026-10-21, li-1: ein veraltetes zweites Ausliefern zieht keine zweite Nummer. */
    public function test_a_stale_second_delivery_is_rejected(): void {
        $note = $this->noteFor($this->confirmedOrder());
        $stale = DeliveryNote::query()->findOrFail($note->id);

        app(\App\Services\Billing\DeliveryNoteService::class)->deliver($note, $this->user);
        $number = (string) $note->fresh()->number;

        try {
            app(\App\Services\Billing\DeliveryNoteService::class)->deliver($stale, $this->user);
            $this->fail('Ein ausgelieferter Lieferschein darf nicht erneut ausgeliefert werden.');
        } catch (\RuntimeException) {
        }
        $this->assertSame($number, (string) $note->fresh()->number);
        $this->assertSame(DeliveryNoteStatus::Delivered, $note->fresh()->status);
    }
}
