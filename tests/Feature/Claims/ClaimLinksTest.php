<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimLinksTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Claims;

use App\Enums\Claims\{ClaimFinancialKind, ClaimFinancialStatus, ClaimRecourseStatus};
use App\Enums\Inventory\{SerialSource, SerialStatus};
use App\Enums\Invoicing\InvoiceStatus;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Asset\Asset;
use App\Models\Claims\{ClaimCase, ClaimFinancialOutcome, ClaimSupplierRecourse};
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Inventory\{StockSerial, Warehouse};
use App\Models\Invoicing\Invoice;
use App\Models\Platform\{Organization, User};
use App\Models\Procurement\PurchaseOrder;
use App\Models\ServiceTicket\ServiceTicket;
use App\Models\Supplier\Supplier;
use App\Services\Claims\ClaimCaseService;
use App\Services\Licensing\FeatureFlagResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1577: Betroffene Objekte an der Akte verknüpfen, Einstieg aus Rechnung,
 * Auftrag, Asset und Ticket, Gutschrift/Storno aus der Akte, Regress mit
 * Bezügen und eigener Antwortfrist — alles über die HTTP-Wege.
 */
final class ClaimLinksTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $overrides */
    private function invoice(InvoiceStatus $status, array $overrides = []): Invoice {
        return Invoice::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'RE-2027-' . fake()->unique()->numerify('####'),
            'status' => $status,
            'type' => Invoice::TYPE_INVOICE,
            'category' => Invoice::CATEGORY_SERVICE,
            'issued_on' => '2027-03-01',
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'subtotal' => '100.00',
            'tax_amount' => '19.00',
            'total' => '119.00',
            'created_by' => $this->admin->id,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function openCase(array $overrides = []): ClaimCase {
        return app(ClaimCaseService::class)->open($this->organization, $this->admin, array_merge([
            'title' => 'Kessel verliert Druck',
            'source' => 'manual',
            'priority' => 'normal',
            'severity' => 'minor',
            'customer_id' => $this->customer->id,
        ], $overrides));
    }

    /** @return array<string, string> */
    private function basics(): array {
        return ['title' => 'Kessel verliert Druck', 'source' => 'manual', 'priority' => 'normal', 'severity' => 'minor'];
    }

    public function test_create_dialog_links_affected_objects_and_rejects_foreign_ones(): void {
        $invoice = $this->invoice(InvoiceStatus::Issued);
        $order = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'user_id' => $this->admin->id]);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'serial_no' => 'KS-4711']);
        $ticket = ServiceTicket::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id]);
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $article = Article::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)->get(route('claims.create'))->assertOk()
            ->assertSee('name="invoice_id"', false)->assertSee('name="diary_entry_id"', false)
            ->assertSee('name="asset_id"', false)->assertSee('name="service_ticket_id"', false)
            ->assertSee('name="supplier_id"', false)->assertSee('name="article_id"', false)
            ->assertSee('data-prefill-serial="KS-4711"', false)
            ->assertSee($invoice->sqid)->assertSee($asset->sqid);

        $this->actingAs($this->admin)->post(route('claims.store'), $this->basics() + [
            'customer_id' => $this->customer->sqid,
            'invoice_id' => $invoice->sqid,
            'diary_entry_id' => $order->sqid,
            'asset_id' => $asset->sqid,
            'service_ticket_id' => $ticket->sqid,
            'supplier_id' => $supplier->sqid,
            'article_id' => $article->sqid,
            'serial_no' => 'KS-4711',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $case = ClaimCase::query()->sole();
        $this->assertSame(
            [$invoice->id, $order->id, $asset->id, $ticket->id, $supplier->id, $article->id],
            [$case->invoice_id, $case->diary_entry_id, $case->asset_id, $case->service_ticket_id, $case->supplier_id, $case->article_id],
        );
        $this->actingAs($this->admin)->get(route('claims.show', $case))->assertOk()
            ->assertSee($invoice->number)->assertSee($asset->name)->assertSee($ticket->ticket_no)->assertSee($supplier->name);

        // Zweiter Fall zur selben Rechnung: Dublettenhinweis.
        $this->actingAs($this->admin)->post(route('claims.store'), $this->basics() + ['customer_id' => $this->customer->sqid, 'invoice_id' => $invoice->sqid])
            ->assertSessionHas('status', fn (string $text): bool => str_contains($text, $case->number));

        $foreignOrg = Organization::factory()->create();
        $foreignAsset = Asset::factory()->create(['organization_id' => $foreignOrg->id]);
        $this->actingAs($this->admin)->post(route('claims.store'), $this->basics() + ['asset_id' => $foreignAsset->sqid])
            ->assertSessionHasErrors('asset_id');
    }

    public function test_edit_dialog_changes_links_and_keeps_the_deadline_time(): void {
        $case = $this->openCase();
        $due = $case->due_at->copy();
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id]);
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)->get(route('claims.show', $case))->assertOk()->assertSee(route('claims.edit', $case), false);
        $this->actingAs($this->admin)->get(route('claims.edit', $case))->assertOk()
            ->assertSee(route('claims.update', $case), false)
            ->assertSee('value="Kessel verliert Druck"', false);

        $this->actingAs($this->admin)->put(route('claims.update', $case), $this->basics() + [
            'customer_id' => $this->customer->sqid,
            'asset_id' => $asset->sqid,
            'supplier_id' => $supplier->sqid,
            'due_at' => $due->format('Y-m-d'),
        ])->assertSessionHasNoErrors();

        $case->refresh();
        $this->assertSame($asset->id, $case->asset_id);
        $this->assertSame($supplier->id, $case->supplier_id);
        $this->assertTrue($due->equalTo($case->due_at));

        // Verknüpfung wieder lösen.
        $this->actingAs($this->admin)->put(route('claims.update', $case), $this->basics() + ['asset_id' => ''])->assertSessionHasNoErrors();
        $this->assertNull($case->fresh()->asset_id);
        $this->assertSame($supplier->id, $case->fresh()->supplier_id);
    }

    public function test_serial_text_is_linked_to_the_known_stock_serial(): void {
        $article = Article::factory()->create(['organization_id' => $this->organization->id]);
        $variant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $article->id]);
        $serial = StockSerial::query()->create([
            'organization_id' => $this->organization->id, 'article_id' => $article->id, 'article_variant_id' => $variant->id,
            'serial_no' => 'KS-9001', 'status' => SerialStatus::Shipped->value, 'source' => SerialSource::Purchased->value, 'customer_id' => $this->customer->id,
        ]);

        $this->actingAs($this->admin)->post(route('claims.store'), $this->basics() + ['customer_id' => $this->customer->sqid, 'article_id' => '', 'serial_no' => 'KS-9001'])
            ->assertSessionHasNoErrors();
        $case = ClaimCase::query()->sole();
        $this->assertSame($serial->id, $case->stock_serial_id);
        $this->assertSame($article->id, $case->article_id);

        $this->actingAs($this->admin)->post(route('claims.store'), $this->basics() + ['serial_no' => 'unbekannt'])->assertSessionHasNoErrors();
        $this->assertNull(ClaimCase::query()->latest('id')->first()->stock_serial_id);
    }

    public function test_batch_of_a_lot_tracked_article_is_recorded_and_shown(): void {
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'batch_required' => true, 'name' => 'Dichtmasse']);
        $variant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $article->id]);
        $lot = app(\App\Services\Inventory\LotService::class)->register($variant, 'L-2027-07');
        $other = Article::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)->get(route('claims.create'))->assertOk()
            ->assertSee('name="stock_lot_id"', false)->assertSee('L-2027-07');

        $this->actingAs($this->admin)->post(route('claims.store'), $this->basics() + ['article_id' => $other->sqid, 'stock_lot_id' => $lot->sqid])
            ->assertSessionHasErrors(['stock_lot_id' => __('claims.form.lot_article_mismatch')]);

        // Ohne Artikel folgt der Artikel der Charge (MVP-1626).
        $this->actingAs($this->admin)->post(route('claims.store'), $this->basics() + ['stock_lot_id' => $lot->sqid])->assertSessionHasNoErrors();
        $case = ClaimCase::query()->sole();
        $this->assertSame([$lot->id, $article->id], [$case->stock_lot_id, $case->article_id]);
        $this->actingAs($this->admin)->get(route('claims.show', $case))->assertOk()->assertSee('L-2027-07');
        $this->actingAs($this->admin)->get(route('claims.edit', $case))->assertOk()->assertSee('value="' . $lot->sqid . '"', false);
    }

    public function test_entry_points_prefill_the_create_dialog(): void {
        $invoice = $this->invoice(InvoiceStatus::Issued);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'serial_no' => 'KS-77']);
        $order = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'user_id' => $this->admin->id]);
        $ticket = ServiceTicket::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'asset_id' => $asset->id]);

        $this->actingAs($this->admin)->get(route('invoices.show', $invoice))->assertOk()
            ->assertSee(route('claims.create', ['invoice' => $invoice->sqid]), false);
        $this->actingAs($this->admin)->get(route('assets.show', $asset))->assertOk()
            ->assertSee(route('claims.create', ['asset' => $asset->sqid]), false);
        $this->actingAs($this->admin)->get(route('service-tickets.show', $ticket))->assertOk()
            ->assertSee(route('claims.create', ['ticket' => $ticket->sqid]), false);
        $this->actingAs($this->admin)->get(route('diary.show', $order))->assertOk()
            ->assertSee(route('claims.create', ['order' => $order->sqid]), false);

        $this->actingAs($this->admin)->get(route('claims.create', ['invoice' => $invoice->sqid]))->assertOk()
            ->assertSee('<option value="' . $this->customer->sqid . '" selected', false)
            ->assertSeeInOrder(['name="invoice_id"', 'value="' . $invoice->sqid . '"', 'selected'], false);
        $this->actingAs($this->admin)->get(route('claims.create', ['asset' => $asset->sqid]))->assertOk()
            ->assertSee('value="KS-77"', false);
        // Das Ticket bringt sein Asset mit.
        $html = (string) $this->actingAs($this->admin)->get(route('claims.create', ['ticket' => $ticket->sqid]))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="' . preg_quote($ticket->sqid, '/') . '"[^>]*selected/', $html);
        $this->assertMatchesRegularExpression('/<option value="' . preg_quote($asset->sqid, '/') . '"[^>]*selected/', $html);
        $html = (string) $this->actingAs($this->admin)->get(route('claims.create', ['order' => $order->sqid]))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="' . preg_quote($order->sqid, '/') . '"[^>]*selected/', $html);

        // Ohne Modul kein Einstieg.
        config(['license.feature_overrides' => ['module.claims' => false]]);
        app(FeatureFlagResolver::class)->flush();
        $this->actingAs($this->admin)->get(route('assets.show', $asset))->assertOk()->assertDontSee('reklamationen/neu', false);
    }

    public function test_entry_point_is_hidden_without_the_create_permission(): void {
        $invoice = $this->invoice(InvoiceStatus::Issued);
        $accounting = $this->userWithRole('buchhaltung');
        $this->assertFalse($accounting->can('create', ClaimCase::class));

        $this->actingAs($accounting)->get(route('invoices.show', $invoice))->assertOk()->assertDontSee('reklamationen/neu', false);
        $this->actingAs($accounting)->get(route('claims.create', ['invoice' => $invoice->sqid]))->assertForbidden();
    }

    public function test_credit_note_and_cancellation_run_from_the_case_file(): void {
        $paid = $this->invoice(InvoiceStatus::Paid);
        $open = $this->invoice(InvoiceStatus::Issued);
        $case = $this->openCase(['invoice_id' => $paid->id]);
        $approver = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)->get(route('claims.show', $case))->assertOk()
            ->assertSee('id="claim-financial-create"', false)
            ->assertSee('<option value="' . $paid->sqid . '" selected', false)
            ->assertSee($open->sqid);

        // Gutschrift zur bezahlten Rechnung der Akte.
        $this->actingAs($this->admin)->post(route('claims.financial.store', $case), [
            'kind' => ClaimFinancialKind::CreditNote->value, 'amount' => '119.00', 'invoice_id' => $paid->sqid,
            'justification' => 'Mangel bestätigt, Rechnung wird gutgeschrieben.',
        ])->assertSessionHasNoErrors();
        $credit = ClaimFinancialOutcome::query()->sole();
        $this->assertSame($paid->id, $credit->invoice_id);
        $this->actingAs($approver)->post(route('claims.financial.approve', $credit))->assertSessionHasNoErrors();
        $this->actingAs($approver)->post(route('claims.financial.execute', $credit))->assertSessionHasNoErrors()->assertSessionHas('status');
        $credit->refresh();
        $this->assertSame(ClaimFinancialStatus::Executed, $credit->status);
        $this->assertSame(Invoice::TYPE_CREDIT_NOTE, $credit->resultInvoice->type);
        $this->assertSame($paid->id, $credit->resultInvoice->parent_invoice_id);
        $this->assertSame('credit_note', $credit->resultInvoice->reason_kind);

        // Storno zur offenen Rechnung, im Formular gewählt.
        $this->actingAs($this->admin)->post(route('claims.financial.store', $case), [
            'kind' => ClaimFinancialKind::Cancellation->value, 'invoice_id' => $open->sqid,
            'justification' => 'Leistung wurde nicht erbracht, Rechnung entfällt.',
        ])->assertSessionHasNoErrors();
        $cancellation = ClaimFinancialOutcome::query()->latest('id')->first();
        $this->actingAs($approver)->post(route('claims.financial.approve', $cancellation));
        $this->actingAs($approver)->post(route('claims.financial.execute', $cancellation))->assertSessionHasNoErrors();
        $this->assertSame(Invoice::TYPE_CANCELLATION, $cancellation->fresh()->resultInvoice->type);
        $this->assertSame($open->id, $cancellation->fresh()->resultInvoice->parent_invoice_id);

        // Storno einer bezahlten Rechnung: verständliche Meldung statt Abbruch.
        $this->actingAs($this->admin)->post(route('claims.financial.store', $case), [
            'kind' => ClaimFinancialKind::Cancellation->value, 'invoice_id' => $paid->sqid,
            'justification' => 'Versuch, eine bezahlte Rechnung zu stornieren.',
        ]);
        $blocked = ClaimFinancialOutcome::query()->latest('id')->first();
        $this->actingAs($approver)->post(route('claims.financial.approve', $blocked));
        $this->actingAs($approver)->post(route('claims.financial.execute', $blocked))->assertSessionHasErrors('outcome');
        $this->assertSame(ClaimFinancialStatus::Approved, $blocked->fresh()->status);
    }

    public function test_correction_without_invoice_is_rejected_when_billing_is_local(): void {
        $case = $this->openCase();

        $this->actingAs($this->admin)->post(route('claims.financial.store', $case), [
            'kind' => ClaimFinancialKind::CreditNote->value, 'justification' => 'Gutschrift ohne Quellbeleg.',
        ])->assertSessionHasErrors('invoice_id');
        // Rückerstattung braucht keinen Beleg.
        $this->actingAs($this->admin)->post(route('claims.financial.store', $case), [
            'kind' => ClaimFinancialKind::Refund->value, 'amount' => '20', 'justification' => 'Erstattung der Anfahrt.',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, ClaimFinancialOutcome::query()->count());
    }

    public function test_recourse_carries_references_terms_and_its_own_response_period(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Armaturen Nord']);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $order = PurchaseOrder::create([
            'organization_id' => $this->organization->id, 'number' => 'BE-0042', 'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id, 'status' => PurchaseOrderStatus::Received->value, 'currency' => 'EUR',
        ]);
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Sicherheitsventil']);
        $case = $this->openCase(['supplier_id' => $supplier->id, 'article_id' => $article->id, 'serial_no' => 'SV-12']);

        $this->actingAs($this->admin)->get(route('claims.show', $case))->assertOk()
            ->assertSee('id="claim-recourse-create"', false)
            ->assertSee('<option value="' . $supplier->sqid . '" selected', false)
            ->assertSee('name="warranty_terms"', false)->assertSee('name="response_due_days"', false)
            ->assertSee('BE-0042')
            ->assertDontSee('Lieferant (Sqid)');

        $this->actingAs($this->admin)->post(route('claims.recourses.store', $case), [
            'supplier_id' => $supplier->sqid, 'purchase_order_id' => $order->sqid, 'article_id' => $article->sqid,
            'serial_no' => 'SV-12', 'warranty_terms' => '24 Monate ab Lieferung, Rüge binnen 8 Tagen.',
            'amount_claimed' => '80.00', 'response_due_days' => '10',
        ])->assertSessionHasNoErrors();
        $recourse = ClaimSupplierRecourse::query()->sole();
        $this->assertSame([$order->id, $article->id, 10], [$recourse->purchase_order_id, $recourse->article_id, $recourse->response_due_days]);

        $this->actingAs($this->admin)->get(route('claims.show', $case))->assertOk()
            ->assertSee('24 Monate ab Lieferung')->assertSee('Sicherheitsventil')
            ->assertSee(__('claims.recourse.due_days', ['days' => 10]));

        $this->travelTo('2027-04-01 09:00:00');
        $this->actingAs($this->admin)->put(route('claims.recourses.update', $recourse), ['status' => ClaimRecourseStatus::Submitted->value])
            ->assertSessionHasNoErrors();
        $this->assertSame('2027-04-11', $recourse->fresh()->response_due_at->toDateString());
    }
}
