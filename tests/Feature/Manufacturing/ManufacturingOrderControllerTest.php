<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManufacturingOrderControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Manufacturing;

use App\Enums\Manufacturing\ManufacturingOrderStatus;
use App\Models\{Article, ArticleVariant, ManufacturingOrder, ProcedureMaterialRequirement, ProcedureTemplateVersion, User, Warehouse};
use App\Services\Manufacturing\ManufacturingOrderService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Fertigungsauftrags-UI (Feature 047): CRUD-Berechtigungen, Anlage, Freigabe und
 * Teilrückmeldung über HTTP.
 */
final class ManufacturingOrderControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Warehouse $warehouse;
    private Article $product;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);

        $version = ProcedureTemplateVersion::factory()->create();
        ProcedureMaterialRequirement::factory()->perUnit('1')->create([
            'procedure_template_version_id' => $version->id,
            'article_id' => Article::factory()->create(['organization_id' => $this->organization->id])->id,
        ]);
        $this->product = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'manufacturable' => true,
            'default_procedure_template_version_id' => $version->id,
        ]);
        ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $this->product->id,
            'is_default' => true,
            'option_signature' => 'default',
        ]);
    }

    public function test_index_requires_view_permission(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->get(route('manufacturing-orders.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('manufacturing-orders.index'))->assertOk();
    }

    public function test_store_creates_draft_order(): void {
        $response = $this->actingAs($this->admin)->post(route('manufacturing-orders.store'), [
            'article' => $this->product->sqid,
            'target_qty' => '5',
            'unit' => 'Stk',
            'warehouse' => $this->warehouse->sqid,
        ]);

        $order = ManufacturingOrder::query()->firstOrFail();
        $response->assertRedirect(route('manufacturing-orders.show', $order));
        $this->assertSame(ManufacturingOrderStatus::Draft, $order->status);
        $this->assertStringStartsWith('FA-', (string) $order->number);
    }

    public function test_store_forbidden_without_post_permission(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->post(route('manufacturing-orders.store'), [
            'article' => $this->product->sqid, 'target_qty' => '1', 'unit' => 'Stk',
        ])->assertForbidden();
    }

    public function test_release_start_report_and_show_render(): void {
        $order = app(ManufacturingOrderService::class)->createDraft(
            $this->organization, $this->product, null, '5', 'Stk', ['warehouse_id' => $this->warehouse->id],
        );

        $this->actingAs($this->admin)->post(route('manufacturing-orders.release', $order))->assertRedirect();
        $this->assertSame(ManufacturingOrderStatus::Released, $order->fresh()->status);

        $this->actingAs($this->admin)->get(route('manufacturing-orders.show', $order))->assertOk();

        $this->actingAs($this->admin)->post(route('manufacturing-orders.report', $order), [
            'produced_qty' => '5', 'good_qty' => '5',
        ])->assertRedirect();
        $this->assertSame('5.0000', $order->fresh()->goodTotal());
    }

    public function test_consume_material_books_actual_consumption(): void {
        $order = app(ManufacturingOrderService::class)->createDraft(
            $this->organization, $this->product, null, '5', 'Stk', ['warehouse_id' => $this->warehouse->id],
        );
        $this->actingAs($this->admin)->post(route('manufacturing-orders.release', $order));
        $material = $order->materials()->firstOrFail();

        $this->actingAs($this->admin)->post(route('manufacturing-orders.materials.consume', [$order, $material]), [
            'quantity' => '2',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('2.0000', $material->fresh()->consumed_qty);
    }

    public function test_consume_is_rejected_for_cancelled_order(): void {
        $order = app(ManufacturingOrderService::class)->createDraft(
            $this->organization, $this->product, null, '5', 'Stk', ['warehouse_id' => $this->warehouse->id],
        );
        $this->actingAs($this->admin)->post(route('manufacturing-orders.release', $order));
        $material = $order->materials()->firstOrFail();
        $this->actingAs($this->admin)->post(route('manufacturing-orders.cancel', $order));

        $this->actingAs($this->admin)->post(route('manufacturing-orders.materials.consume', [$order, $material]), [
            'quantity' => '2',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame('0.0000', $material->fresh()->consumed_qty);
    }

    public function test_consume_rejects_material_of_other_order(): void {
        $service = app(ManufacturingOrderService::class);
        $orderA = $service->createDraft($this->organization, $this->product, null, '5', 'Stk', ['warehouse_id' => $this->warehouse->id]);
        $orderB = $service->createDraft($this->organization, $this->product, null, '5', 'Stk', ['warehouse_id' => $this->warehouse->id]);
        $this->actingAs($this->admin)->post(route('manufacturing-orders.release', $orderB));
        $foreignMaterial = $orderB->materials()->firstOrFail();

        $this->actingAs($this->admin)->post(route('manufacturing-orders.materials.consume', [$orderA, $foreignMaterial]), [
            'quantity' => '1',
        ])->assertNotFound();
    }

    public function test_record_pdf_renders(): void {
        $order = ManufacturingOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('manufacturing-orders.record.pdf', $order))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_show_links_procedure_run_and_run_links_back(): void {
        $order = app(ManufacturingOrderService::class)->createDraft(
            $this->organization, $this->product, null, '5', 'Stk', ['warehouse_id' => $this->warehouse->id],
        );
        $this->actingAs($this->admin)->post(route('manufacturing-orders.release', $order));
        $this->actingAs($this->admin)->post(route('manufacturing-orders.start', $order));

        $order->refresh();
        $this->assertNotNull($order->procedure_run_id);
        $run = $order->procedureRun;

        // MVP-063: Einstieg vom Auftrag in die mobile Lauf-Ansicht …
        $this->actingAs($this->admin)->get(route('manufacturing-orders.show', $order))
            ->assertOk()
            ->assertSee(route('procedure-runs.show', $run), false);

        // … und zurück vom Lauf zum Auftrag (backUrl).
        $this->actingAs($this->admin)->get(route('procedure-runs.show', $run))
            ->assertOk()
            ->assertSee(route('manufacturing-orders.show', $order), false);
    }

    public function test_push_delivery_note_to_lexoffice(): void {
        config()->set('plugins.lexoffice.api_key', 'test-key');
        config()->set('plugins.lexoffice.base_url', 'https://api.lexoffice.io/v1');

        $customer = \App\Models\Customer::factory()->create([
            'organization_id' => $this->organization->id, 'email' => 'kunde@example.com',
        ]);
        \App\Models\ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => \App\Plugins\Lexoffice\LexofficePlugin::ID,
            'external_type' => \App\Plugins\Lexoffice\LexofficePlugin::EXT_TYPE_CONTACT,
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->getKey(),
            'external_id' => 'lex-contact-1', 'synced_at' => now(),
        ]);

        $order = ManufacturingOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]);
        $variant = ArticleVariant::query()->where('article_id', $this->product->id)->firstOrFail();
        $delivery = \App\Models\StockDelivery::query()->create([
            'organization_id' => $this->organization->id,
            'manufacturing_order_id' => $order->id,
            'article_variant_id' => $variant->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $customer->id,
            'quantity' => '2.0000', 'unit' => 'Stk', 'sku_snapshot' => 'S-1', 'name_snapshot' => 'Produkt',
            'currency' => 'EUR', 'stock_status' => 'delivered',
            'facturation_status' => \App\Enums\Manufacturing\DeliveryFacturationStatus::Pending->value,
            'facturation_target' => 'lexoffice', 'delivered_at' => now(),
        ]);

        \Tests\Support\FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/delivery-notes*' => \Tests\Support\FakePluginHttp::response(['id' => 'lex-dn-1'], 201),
        ]);

        $this->actingAs($this->admin)
            ->post(route('manufacturing-orders.deliveries.lexoffice', [$order, $delivery]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(
            \App\Enums\Manufacturing\DeliveryFacturationStatus::HandedOver,
            $delivery->fresh()->facturation_status,
        );
        $this->assertSame('lex-dn-1', $delivery->fresh()->external_id);
    }

    public function test_push_order_confirmation_to_lexoffice(): void {
        config()->set('plugins.lexoffice.api_key', 'test-key');
        config()->set('plugins.lexoffice.base_url', 'https://api.lexoffice.io/v1');

        $customer = \App\Models\Customer::factory()->create([
            'organization_id' => $this->organization->id, 'email' => 'kunde@example.com',
        ]);
        \App\Models\ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => \App\Plugins\Lexoffice\LexofficePlugin::ID,
            'external_type' => \App\Plugins\Lexoffice\LexofficePlugin::EXT_TYPE_CONTACT,
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->getKey(),
            'external_id' => 'lex-contact-1', 'synced_at' => now(),
        ]);

        $variant = ArticleVariant::query()->where('article_id', $this->product->id)->firstOrFail();
        $order = ManufacturingOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $this->product->id,
            'article_variant_id' => $variant->id,
            'customer_id' => $customer->id,
            'target_qty' => '3', 'unit' => 'Stk',
            'status' => ManufacturingOrderStatus::Released->value,
        ]);

        \Tests\Support\FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/order-confirmations*' => \Tests\Support\FakePluginHttp::response(['id' => 'lex-oc-1'], 201),
        ]);

        $this->actingAs($this->admin)
            ->post(route('manufacturing-orders.order-confirmation.lexoffice', $order))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('external_references', [
            'plugin_id' => \App\Plugins\Lexoffice\LexofficePlugin::ID,
            'external_type' => \App\Plugins\Lexoffice\LexofficeOrderConfirmationService::EXT_TYPE_ORDER_CONFIRMATION,
            'external_id' => 'lex-oc-1',
            'referenceable_id' => $order->id,
        ]);
    }

    public function test_push_quotation_to_lexoffice(): void {
        config()->set('plugins.lexoffice.api_key', 'test-key');
        config()->set('plugins.lexoffice.base_url', 'https://api.lexoffice.io/v1');

        $customer = \App\Models\Customer::factory()->create([
            'organization_id' => $this->organization->id, 'email' => 'kunde@example.com',
        ]);
        \App\Models\ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => \App\Plugins\Lexoffice\LexofficePlugin::ID,
            'external_type' => \App\Plugins\Lexoffice\LexofficePlugin::EXT_TYPE_CONTACT,
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->getKey(),
            'external_id' => 'lex-contact-1', 'synced_at' => now(),
        ]);

        $variant = ArticleVariant::query()->where('article_id', $this->product->id)->firstOrFail();
        $order = ManufacturingOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $this->product->id,
            'article_variant_id' => $variant->id,
            'customer_id' => $customer->id,
            'target_qty' => '2', 'unit' => 'Stk',
            'status' => ManufacturingOrderStatus::Draft->value,
        ]);

        \Tests\Support\FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/quotations*' => \Tests\Support\FakePluginHttp::response(['id' => 'lex-q-1'], 201),
        ]);

        $this->actingAs($this->admin)
            ->post(route('manufacturing-orders.quotation.lexoffice', $order))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('external_references', [
            'plugin_id' => \App\Plugins\Lexoffice\LexofficePlugin::ID,
            'external_type' => \App\Plugins\Lexoffice\LexofficeQuotationService::EXT_TYPE_QUOTATION,
            'external_id' => 'lex-q-1',
            'referenceable_id' => $order->id,
        ]);
    }
}
