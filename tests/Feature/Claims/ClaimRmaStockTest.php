<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimRmaStockTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Claims;

use App\Enums\Claims\{ClaimRmaDisposition, ClaimRmaStatus};
use App\Enums\CustomerPortal\PortalCapability;
use App\Enums\Inventory\{SerialSource, SerialStatus, StockState};
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Claims\{ClaimCase, ClaimRmaReturn};
use App\Models\Customer\Customer;
use App\Models\Inventory\{StockDelivery, StockMovement, StockSerial, Warehouse};
use App\Models\Platform\User;
use App\Services\Claims\{ClaimCaseService, ClaimRmaService};
use App\Services\Inventory\InventoryLedger;
use App\Services\Licensing\FeatureFlagResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * MVP-1576: Die Formulare der Reklamationsakte senden den Lagerbezug, der
 * Rückläufer bucht dann wirklich — und die Meldung sagt nur „gebucht“, wenn
 * Bestand bewegt wurde.
 */
final class ClaimRmaStockTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private User $admin;

    private Customer $customer;

    private Article $article;

    private ArticleVariant $variant;

    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Umwälzpumpe']);
        $this->variant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $this->article->id, 'sku' => 'UP-25']);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Hauptlager']);
    }

    /** @param array<string, mixed> $overrides */
    private function openCase(array $overrides = []): ClaimCase {
        return app(ClaimCaseService::class)->open($this->organization, $this->admin, array_merge([
            'title' => 'Pumpe läuft nicht an',
            'source' => 'manual',
            'priority' => 'normal',
            'severity' => 'minor',
            'customer_id' => $this->customer->id,
        ], $overrides));
    }

    private function serial(string $no): StockSerial {
        return StockSerial::query()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $this->article->id,
            'article_variant_id' => $this->variant->id,
            'serial_no' => $no,
            'status' => SerialStatus::Shipped->value,
            'source' => SerialSource::Purchased->value,
            'customer_id' => $this->customer->id,
        ]);
    }

    public function test_case_file_offers_stock_fields_prefilled_from_article_and_serial(): void {
        $serial = $this->serial('SN-UP-1');
        $case = $this->openCase(['article_id' => $this->article->id, 'stock_serial_id' => $serial->id, 'serial_no' => 'SN-UP-1']);

        $this->actingAs($this->admin)->get(route('claims.show', $case))->assertOk()
            ->assertSee('id="claim-rma-create"', false)
            ->assertSee('name="warehouse_id"', false)
            ->assertSee('name="qty"', false)
            ->assertSee('<option value="' . $this->variant->sqid . '" selected', false)
            ->assertSee('data-prefill-variant="' . $this->variant->sqid . '"', false)
            ->assertSee('Hauptlager');
    }

    public function test_receipt_and_disposition_book_stock_over_http(): void {
        $case = $this->openCase(['article_id' => $this->article->id]);

        $this->actingAs($this->admin)->post(route('claims.rma.store', $case), [
            'article_variant_id' => $this->variant->sqid,
            'qty' => '2',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $rma = $case->rmaReturns()->sole();
        $this->assertSame($this->article->id, $rma->article_id);

        $this->actingAs($this->admin)->post(route('claims.rma.receive', $rma), [
            'stock_state' => 'quality',
            'warehouse_id' => $this->warehouse->sqid,
        ])->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('claims.rma.flash.received_booked', ['state' => StockState::Quality->label()]));

        $ledger = app(InventoryLedger::class);
        $this->assertSame('2.0000', $ledger->balance($this->variant, $this->warehouse, StockState::Quality));

        $this->actingAs($this->admin)->get(route('claims.show', $case))->assertOk()
            ->assertSee('UP-25')->assertSee('Hauptlager')
            ->assertSee('id="claim-rma-disposition-' . $rma->id . '"', false)
            ->assertSee(__('claims.rma.disposition_books'));

        $this->actingAs($this->admin)->post(route('claims.rma.disposition', $rma), [
            'disposition' => ClaimRmaDisposition::Restock->value,
            'disposition_note' => 'ohne Befund',
        ])->assertSessionHasNoErrors()->assertSessionHas('status', __('claims.rma.flash.disposition_booked'));

        $this->assertSame('0.0000', $ledger->balance($this->variant, $this->warehouse, StockState::Quality));
        $this->assertSame('2.0000', $ledger->balance($this->variant, $this->warehouse, StockState::Physical));
        $this->assertSame('ohne Befund', $rma->fresh()->disposition_note);
    }

    public function test_return_without_stock_reference_is_reported_as_unbooked(): void {
        $case = $this->openCase();
        $this->actingAs($this->admin)->post(route('claims.rma.store', $case), ['serial_no' => 'unbekannt'])->assertSessionHasNoErrors();
        $rma = $case->rmaReturns()->sole();

        $this->actingAs($this->admin)->post(route('claims.rma.receive', $rma), ['stock_state' => 'quality'])
            ->assertSessionHas('status', __('claims.rma.flash.received_unbooked'));
        $this->actingAs($this->admin)->post(route('claims.rma.disposition', $rma), ['disposition' => ClaimRmaDisposition::Scrap->value])
            ->assertSessionHas('status', __('claims.rma.flash.disposition_unbooked'));

        $this->assertSame(ClaimRmaStatus::Completed, $rma->fresh()->status);
        $this->assertSame(0, StockMovement::query()->count());
        $this->actingAs($this->admin)->get(route('claims.show', $case))->assertOk()->assertSee(__('claims.rma.without_stock'));
    }

    public function test_variant_without_warehouse_is_rejected_instead_of_silently_skipped(): void {
        $case = $this->openCase();
        $rma = app(ClaimRmaService::class)->announce($case, ['article_variant_id' => $this->variant->id, 'qty' => '1']);

        $this->actingAs($this->admin)->post(route('claims.rma.receive', $rma), ['stock_state' => 'quality'])
            ->assertSessionHasErrors('warehouse_id');
        $this->assertSame(ClaimRmaStatus::Announced, $rma->fresh()->status);
    }

    public function test_serial_selection_sets_variant_and_quantity_and_follows_the_return(): void {
        $serial = $this->serial('SN-UP-2');
        $case = $this->openCase(['serial_no' => 'SN-UP-2']);

        // Nur die Seriennummer als Text: eindeutig im Lager, also zugeordnet.
        $this->actingAs($this->admin)->post(route('claims.rma.store', $case), ['serial_no' => 'SN-UP-2'])->assertSessionHasNoErrors();
        $rma = $case->rmaReturns()->sole();
        $this->assertSame($serial->id, $rma->stock_serial_id);
        $this->assertSame($this->variant->id, $rma->article_variant_id);
        $this->assertSame('1.0000', $rma->qty);

        $this->actingAs($this->admin)->post(route('claims.rma.receive', $rma), ['stock_state' => 'blocked', 'warehouse_id' => $this->warehouse->sqid])
            ->assertSessionHasNoErrors();
        $this->assertSame(SerialStatus::Returned, $serial->fresh()->status);
        $this->assertSame('1.0000', app(InventoryLedger::class)->balance($this->variant, $this->warehouse, StockState::Blocked));

        $this->actingAs($this->admin)->post(route('claims.rma.disposition', $rma), ['disposition' => ClaimRmaDisposition::Scrap->value])
            ->assertSessionHas('status', __('claims.rma.flash.disposition_booked'));
        $this->assertSame(SerialStatus::Scrapped, $serial->fresh()->status);
    }

    public function test_serial_of_another_variant_and_quantity_above_one_are_rejected(): void {
        $serial = $this->serial('SN-UP-3');
        $otherVariant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $this->article->id, 'option_signature' => 'dn32']);
        $case = $this->openCase();

        $this->actingAs($this->admin)->post(route('claims.rma.store', $case), [
            'article_variant_id' => $otherVariant->sqid, 'stock_serial_id' => $serial->sqid,
        ])->assertSessionHasErrors('stock_serial_id');
        $this->actingAs($this->admin)->post(route('claims.rma.store', $case), [
            'stock_serial_id' => $serial->sqid, 'qty' => '3',
        ])->assertSessionHasErrors('qty');
        $this->assertSame(0, ClaimRmaReturn::query()->count());
    }

    public function test_without_inventory_module_the_return_only_documents(): void {
        config(['license.feature_overrides' => ['module.lager' => false]]);
        app(FeatureFlagResolver::class)->flush();

        $case = $this->openCase();
        $rma = app(ClaimRmaService::class)->announce($case, ['article_variant_id' => $this->variant->id, 'warehouse_id' => $this->warehouse->id, 'qty' => '1']);

        $this->actingAs($this->admin)->get(route('claims.show', $case))->assertOk()
            ->assertSee(__('claims.rma.no_stock_module'))
            ->assertDontSee('name="warehouse_id"', false);
        $this->actingAs($this->admin)->post(route('claims.rma.receive', $rma), ['stock_state' => 'quality'])
            ->assertSessionHas('status', __('claims.rma.flash.received_unbooked'));
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_portal_return_carries_variant_and_internal_receipt_adds_the_warehouse(): void {
        $delivery = StockDelivery::query()->create([
            'organization_id' => $this->organization->id, 'article_variant_id' => $this->variant->id, 'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id, 'quantity' => '2', 'unit' => 'Stk', 'name_snapshot' => 'Umwälzpumpe', 'sku_snapshot' => 'UP-25',
            'stock_status' => 'delivered', 'facturation_status' => 'pending', 'delivered_at' => now()->subWeek(),
        ]);
        $this->allowPortal($this->customer, [PortalCapability::Claims->value, PortalCapability::Returns->value]);
        $portalUser = User::factory()->kunde((int) $this->customer->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);

        $this->actingAs($portalUser, 'customer')->post(route('customer.returns.store'), [
            'delivery_id' => $delivery->sqid, 'quantity' => '2',
            'title' => 'Pumpe läuft nicht an', 'description' => 'Seit der Inbetriebnahme ohne Funktion.',
        ])->assertRedirect()->assertSessionHas('status');
        $rma = ClaimRmaReturn::query()->sole();
        $this->assertSame($this->variant->id, $rma->article_variant_id);
        $this->assertNull($rma->warehouse_id);

        $this->actingAs($this->admin)->get(route('claims.show', $rma->claimCase))->assertOk()
            ->assertSee('id="claim-rma-receive-' . $rma->id . '"', false);
        $this->actingAs($this->admin)->post(route('claims.rma.receive', $rma), [
            'stock_state' => 'quality', 'warehouse_id' => $this->warehouse->sqid,
        ])->assertSessionHas('status', __('claims.rma.flash.received_booked', ['state' => StockState::Quality->label()]));
        $this->assertSame('2.0000', app(InventoryLedger::class)->balance($this->variant, $this->warehouse, StockState::Quality));
    }
}
