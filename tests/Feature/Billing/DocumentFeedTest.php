<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentFeedTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Billing;

use App\Enums\Billing\{DocumentDirection, DocumentKind, DocumentOrigin};
use App\Enums\Expense\{ExpenseStatus, PaymentMethod};
use App\Models\{Customer, Expense, Invoice, LexofficeVoucher, OrgaMaxInvoice, PluginSetting, Supplier, User};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Billing\{DocumentFeedFilters, DocumentFeedQuery, DocumentLinks};
use App\Support\Billing\VoucherTypes;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Belegfluss (Feature 105): eine Liste über sechs Quellen mit richtungs- und
 * vorzeichenrichtigen Kennzahlen.
 *
 * Kern der Prüfung ist, was die alte Belegliste falsch machte: sie addierte
 * Ausgangsrechnung, Eingangsrechnung und Gutschrift ohne Vorzeichen zu einer
 * einzigen Seitensumme.
 */
final class DocumentFeedTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    private Supplier $supplier;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();

        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'test-key'],
        ]);

        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Marina Vulkan Werft',
        ]);
        $this->supplier = Supplier::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Haufe Service Center GmbH',
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function voucher(array $attributes): LexofficeVoucher {
        return LexofficeVoucher::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'voucher_date' => '2026-08-10',
            'currency' => 'EUR',
            'archived' => false,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function orgamaxInvoice(array $attributes): OrgaMaxInvoice {
        return OrgaMaxInvoice::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'invoice_date' => '2026-08-10',
            'invoice_type' => 'invoice',
            'currency' => 'EUR',
        ]);
    }

    private function filters(bool $allExpenses = false, ?User $as = null): DocumentFeedFilters {
        $user = $as ?? $this->admin;

        return new DocumentFeedFilters(
            organizationId: (int) $this->organization->id,
            userId: (int) $user->id,
            from: CarbonImmutable::parse('2026-01-01')->startOfDay(),
            to: CarbonImmutable::parse('2026-12-31')->endOfDay(),
            allExpenses: $allExpenses,
            sources: [
                'invoice' => true, 'quote' => true, 'voucher' => true,
                'incoming_einvoice' => true, 'expense' => true,
            ],
        );
    }

    public function test_classification_maps_direction_and_sign(): void {
        $sales = VoucherTypes::classify('salesinvoice');
        $this->assertSame(DocumentDirection::Outgoing, $sales->direction);
        $this->assertSame(DocumentKind::Invoice, $sales->kind);
        $this->assertSame(1, $sales->sign());

        $this->assertSame(-1, VoucherTypes::sign('salescreditnote'));
        $this->assertSame(-1, VoucherTypes::sign('purchasecreditnote'));
        $this->assertSame(DocumentDirection::Incoming, VoucherTypes::classify('purchaseinvoice')->direction);

        // Angebote/Lieferscheine haben keinen Geldwert und dürfen nie in eine
        // Summe fließen — auch nicht mit +0-Betrag.
        $this->assertSame(0, VoucherTypes::sign('quotation'));
        $this->assertSame(0, VoucherTypes::sign('deliverynote'));
        $this->assertSame(0, VoucherTypes::sign('unbekannt'));
    }

    public function test_totals_separate_direction_and_apply_sign(): void {
        $this->voucher(['external_id' => 'v1', 'customer_id' => $this->customer->id,
            'voucher_type' => 'salesinvoice', 'voucher_status' => 'paid',
            'voucher_number' => 'RE/2026/1110', 'total_amount' => '1000.00']);
        $this->voucher(['external_id' => 'v2', 'customer_id' => $this->customer->id,
            'voucher_type' => 'salescreditnote', 'voucher_status' => 'paid',
            'voucher_number' => 'GS/2026/0014', 'total_amount' => '100.00']);
        $this->voucher(['external_id' => 'v3', 'supplier_id' => $this->supplier->id,
            'voucher_type' => 'purchaseinvoice', 'voucher_status' => 'paid',
            'voucher_number' => 'ER-1', 'total_amount' => '39.15']);

        $totals = (new DocumentFeedQuery($this->filters()))->totals();

        $this->assertCount(1, $totals);
        $this->assertSame('EUR', $totals[0]['currency']);
        // 1000 − 100 Gutschrift; die Eingangsrechnung gehört NICHT dazu.
        $this->assertEqualsWithDelta(900.0, $totals[0]['revenue'], 0.001);
        $this->assertEqualsWithDelta(39.15, $totals[0]['expense'], 0.001);
        $this->assertEqualsWithDelta(860.85, $totals[0]['balance'], 0.001);
    }

    public function test_neutral_documents_are_counted_but_never_summed(): void {
        $this->voucher(['external_id' => 'v4', 'customer_id' => $this->customer->id,
            'voucher_type' => 'quotation', 'voucher_status' => 'open',
            'voucher_number' => 'AN-1', 'total_amount' => '5000.00']);

        $totals = (new DocumentFeedQuery($this->filters()))->totals();

        $this->assertEqualsWithDelta(0.0, $totals[0]['revenue'], 0.001);
        $this->assertSame(1, $totals[0]['neutralCount']);
    }

    public function test_draft_and_voided_vouchers_do_not_count(): void {
        $this->voucher(['external_id' => 'v5', 'customer_id' => $this->customer->id,
            'voucher_type' => 'salesinvoice', 'voucher_status' => 'draft',
            'voucher_number' => 'RE-DRAFT', 'total_amount' => '500.00']);
        $this->voucher(['external_id' => 'v6', 'customer_id' => $this->customer->id,
            'voucher_type' => 'salesinvoice', 'voucher_status' => 'voided',
            'voucher_number' => 'RE-VOID', 'total_amount' => '700.00']);

        $totals = (new DocumentFeedQuery($this->filters()))->totals();

        $this->assertEqualsWithDelta(0.0, $totals[0]['revenue'], 0.001);
    }

    public function test_local_invoice_and_mirrored_voucher_appear_once(): void {
        Invoice::query()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'RE/2026/1110',
            'status' => Invoice::STATUS_ISSUED,
            'type' => Invoice::TYPE_INVOICE,
            'category' => Invoice::CATEGORY_SERVICE,
            'issued_on' => '2026-08-10',
            'currency' => 'EUR',
            'total' => '1000.00',
        ]);
        $this->voucher(['external_id' => 'v7', 'customer_id' => $this->customer->id,
            'voucher_type' => 'salesinvoice', 'voucher_status' => 'open',
            'voucher_number' => 'RE/2026/1110', 'total_amount' => '1000.00']);

        $rows = (new DocumentFeedQuery($this->filters()))->paginate(30, 'date', 'desc');

        $matching = collect($rows->items())->filter(fn(object $row): bool => $row->number === 'RE/2026/1110');
        $this->assertCount(1, $matching, 'Übergebene Rechnung darf nur einmal erscheinen.');
        $this->assertSame('voucher', $matching->first()->source_type, 'Extern führt.');

        $totals = (new DocumentFeedQuery($this->filters()))->totals();
        $this->assertEqualsWithDelta(1000.0, $totals[0]['revenue'], 0.001);
    }

    public function test_expenses_are_reported_separately_from_external_cost(): void {
        Expense::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'date' => '2026-08-05',
            'status' => ExpenseStatus::Approved,
            'payment_method' => PaymentMethod::PrivatePaid,
            'currency' => 'EUR',
            // Expense::saving() rechnet Brutto aus Netto — sonst gewinnt der
            // Zufallswert der Factory.
            'amount_net' => '25.00',
            'tax_rate' => '0',
        ]);
        $this->voucher(['external_id' => 'v8', 'supplier_id' => $this->supplier->id,
            'voucher_type' => 'purchaseinvoice', 'voucher_status' => 'paid',
            'voucher_number' => 'ER-2', 'total_amount' => '39.15']);

        $totals = (new DocumentFeedQuery($this->filters(allExpenses: true)))->totals();

        $this->assertEqualsWithDelta(39.15, $totals[0]['expense'], 0.001, 'Auslage gehört nicht in den externen Aufwand.');
        $this->assertEqualsWithDelta(25.0, $totals[0]['internal'], 0.001);
    }

    public function test_linked_expense_stops_counting_as_own_cost(): void {
        $expense = Expense::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'date' => '2026-08-10',
            'status' => ExpenseStatus::Approved,
            'payment_method' => PaymentMethod::PrivatePaid,
            'currency' => 'EUR',
            'amount_net' => '39.15',
            'tax_rate' => '0',
        ]);
        $voucher = $this->voucher(['external_id' => 'v9', 'supplier_id' => $this->supplier->id,
            'voucher_type' => 'purchaseinvoice', 'voucher_status' => 'paid',
            'voucher_number' => 'ER-3', 'total_amount' => '39.15']);

        $links = new DocumentLinks();
        $this->assertTrue($links->suggestionsFor($expense)->contains('id', $voucher->id));

        $links->link($expense, $voucher);

        $totals = (new DocumentFeedQuery($this->filters(allExpenses: true)))->totals();
        $this->assertEqualsWithDelta(39.15, $totals[0]['expense'], 0.001);
        $this->assertEqualsWithDelta(0.0, $totals[0]['internal'], 0.001, 'Verknüpfte Auslage zählt nicht doppelt.');
    }

    public function test_expense_scope_defaults_to_own_rows(): void {
        $other = $this->orgUser();
        Expense::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $other->id,
            'date' => '2026-08-05',
            'status' => ExpenseStatus::Approved,
            'payment_method' => PaymentMethod::PrivatePaid,
            'currency' => 'EUR',
            'amount_net' => '80.00',
            'tax_rate' => '0',
        ]);

        $mine = (new DocumentFeedQuery($this->filters()))->totals();
        $this->assertEqualsWithDelta(0.0, $mine[0]['internal'] ?? 0.0, 0.001);

        $all = (new DocumentFeedQuery($this->filters(allExpenses: true)))->totals();
        $this->assertEqualsWithDelta(80.0, $all[0]['internal'], 0.001);
    }

    public function test_overdue_filter_and_column(): void {
        $this->voucher(['external_id' => 'v10', 'customer_id' => $this->customer->id,
            'voucher_type' => 'salesinvoice', 'voucher_status' => 'open',
            'voucher_number' => 'RE-OVERDUE', 'total_amount' => '90.00',
            'due_date' => '2026-01-15', 'open_amount' => '90.00']);
        $this->voucher(['external_id' => 'v11', 'customer_id' => $this->customer->id,
            'voucher_type' => 'salesinvoice', 'voucher_status' => 'paid',
            'voucher_number' => 'RE-PAID', 'total_amount' => '10.00',
            'due_date' => '2026-01-15']);

        $totals = (new DocumentFeedQuery($this->filters()))->totals();
        $this->assertEqualsWithDelta(90.0, $totals[0]['overdue'], 0.001);
        $this->assertSame(1, $totals[0]['overdueCount']);

        // Überfällig ist eine TEILMENGE von Offen — nicht daneben, sondern
        // darin. Die Kachel nennt deshalb beide Zahlen (7 von 12).
        $this->assertEqualsWithDelta(90.0, $totals[0]['open'], 0.001);
        $this->assertSame(1, $totals[0]['openCount']);

        $response = $this->actingAs($this->admin)
            ->withSession($this->range())
            ->get(route('billing.feed', ['overdue' => 1]));

        $response->assertOk()->assertSee('RE-OVERDUE')->assertDontSee('RE-PAID');
    }

    /**
     * Die Kachel darf nicht so aussehen, als stünden offen und überfällig
     * nebeneinander: der überfällige Betrag steckt im offenen.
     */
    public function test_the_overdue_tile_names_its_share_of_the_open_total(): void {
        // Zwei offene Rechnungen, davon eine überfällig.
        $this->voucher(['external_id' => 'v20', 'customer_id' => $this->customer->id,
            'voucher_type' => 'salesinvoice', 'voucher_status' => 'open',
            'voucher_number' => 'RE-LATE', 'total_amount' => '90.00',
            'due_date' => '2026-01-15', 'open_amount' => '90.00']);
        $this->voucher(['external_id' => 'v21', 'customer_id' => $this->customer->id,
            'voucher_type' => 'salesinvoice', 'voucher_status' => 'open',
            'voucher_number' => 'RE-SOON', 'total_amount' => '60.00',
            'due_date' => '2027-12-31', 'open_amount' => '60.00']);

        $totals = (new DocumentFeedQuery($this->filters()))->totals();

        $this->assertEqualsWithDelta(150.0, $totals[0]['open'], 0.001);
        $this->assertSame(2, $totals[0]['openCount']);
        $this->assertEqualsWithDelta(90.0, $totals[0]['overdue'], 0.001);
        $this->assertSame(1, $totals[0]['overdueCount']);

        $this->actingAs($this->admin)
            ->withSession($this->range())
            ->get(route('billing.feed'))
            ->assertOk()
            ->assertSee(__('billing.feed.kpi.overdue'))
            ->assertSee(__('billing.feed.kpi.overdue_count', ['count' => 1, 'total' => 2]));
    }

    public function test_feed_page_renders_with_tabs(): void {
        $this->voucher(['external_id' => 'v12', 'customer_id' => $this->customer->id,
            'voucher_type' => 'salesinvoice', 'voucher_status' => 'open',
            'voucher_number' => 'RE/2026/1111', 'total_amount' => '90.00']);

        $this->actingAs($this->admin)
            ->withSession($this->range())
            ->get(route('billing.feed'))
            ->assertOk()
            ->assertSee(__('billing.feed.tab.outgoing'))
            ->assertSee(__('billing.feed.tab.incoming'))
            ->assertSee('RE/2026/1111');
    }

    public function test_incoming_tab_hides_outgoing_documents(): void {
        $this->voucher(['external_id' => 'v13', 'customer_id' => $this->customer->id,
            'voucher_type' => 'salesinvoice', 'voucher_status' => 'open',
            'voucher_number' => 'RE-OUT', 'total_amount' => '90.00']);
        $this->voucher(['external_id' => 'v14', 'supplier_id' => $this->supplier->id,
            'voucher_type' => 'purchaseinvoice', 'voucher_status' => 'open',
            'voucher_number' => 'ER-IN', 'total_amount' => '39.15']);

        $this->actingAs($this->admin)
            ->withSession($this->range())
            ->get(route('billing.feed', ['tab' => 'incoming']))
            ->assertOk()
            ->assertSee('ER-IN')
            ->assertDontSee('RE-OUT');
    }

    public function test_orgamax_invoices_appear_with_origin_and_sign(): void {
        $this->orgamaxInvoice(['external_id' => 'om-1', 'customer_id' => $this->customer->id,
            'invoice_number' => 'OM-100', 'invoice_status' => 'locked',
            'total_gross' => '119.00', 'outstanding_amount' => '119.00']);
        // Entwurf und Storno sind nicht geldwirksam.
        $this->orgamaxInvoice(['external_id' => 'om-2', 'invoice_number' => 'OM-101',
            'invoice_status' => 'draft', 'total_gross' => '500.00']);
        $this->orgamaxInvoice(['external_id' => 'om-3', 'invoice_number' => 'OM-102',
            'invoice_status' => 'cancelled', 'total_gross' => '700.00']);
        // Eine Wiederholungs-Vorlage ist kein Beleg.
        $this->orgamaxInvoice(['external_id' => 'om-4', 'invoice_number' => 'OM-VORLAGE',
            'invoice_type' => 'recurringInvoiceTemplate', 'invoice_status' => 'locked',
            'total_gross' => '900.00']);

        $rows = collect((new DocumentFeedQuery($this->filters()))->paginate(30, 'date', 'desc')->items());

        $this->assertNull($rows->firstWhere('number', 'OM-VORLAGE'), 'Vorlagen gehören nicht in die Belegliste.');
        $row = $rows->firstWhere('number', 'OM-100');
        $this->assertNotNull($row);
        $this->assertSame(DocumentOrigin::OrgaMax->value, $row->origin);
        $this->assertSame(DocumentDirection::Outgoing->value, $row->direction);
        $this->assertSame(DocumentKind::Invoice->value, $row->kind);
        $this->assertSame('open', $row->state);
        $this->assertSame('Marina Vulkan Werft', $row->contact_name, 'Verknüpfter Kunde gewinnt gegen den Fremdnamen.');

        $totals = (new DocumentFeedQuery($this->filters()))->totals();
        $this->assertEqualsWithDelta(119.0, $totals[0]['revenue'], 0.001, 'Nur der gültige Beleg zählt.');
    }

    public function test_orgamax_deposit_invoice_is_a_down_payment_and_filterable_by_origin(): void {
        $this->orgamaxInvoice(['external_id' => 'om-5', 'customer_name' => 'Externer Kunde',
            'invoice_number' => 'OM-ABSCHLAG', 'invoice_type' => 'depositInvoice',
            'invoice_status' => 'partiallyPaid', 'total_gross' => '238.00',
            'outstanding_amount' => '100.00']);
        $this->voucher(['external_id' => 'v9', 'customer_id' => $this->customer->id,
            'voucher_type' => 'salesinvoice', 'voucher_status' => 'open',
            'voucher_number' => 'LX-1', 'total_amount' => '50.00']);

        $filters = new DocumentFeedFilters(
            organizationId: (int) $this->organization->id,
            userId: (int) $this->admin->id,
            from: CarbonImmutable::parse('2026-01-01')->startOfDay(),
            to: CarbonImmutable::parse('2026-12-31')->endOfDay(),
            origin: DocumentOrigin::OrgaMax,
            sources: [
                'invoice' => true, 'quote' => true, 'voucher' => true,
                'incoming_einvoice' => true, 'expense' => true,
            ],
        );

        $rows = collect((new DocumentFeedQuery($filters))->paginate(30, 'date', 'desc')->items());

        $this->assertCount(1, $rows, 'Der Herkunftsfilter blendet Lexoffice aus.');
        $this->assertSame(DocumentKind::DownPayment->value, $rows->first()->kind);
        $this->assertSame('Externer Kunde', $rows->first()->contact_name, 'Ohne Zuordnung bleibt der Fremdname.');
        $this->assertEqualsWithDelta(100.0, (float) $rows->first()->open_amount, 0.001, 'Teilzahlung lässt den Rest offen.');
    }

    public function test_invoice_transferred_to_orgamax_appears_only_once(): void {
        Invoice::query()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'RE/2026/2220',
            'status' => Invoice::STATUS_ISSUED,
            'type' => Invoice::TYPE_INVOICE,
            'category' => Invoice::CATEGORY_SERVICE,
            'issued_on' => '2026-08-10',
            'currency' => 'EUR',
            'total' => '300.00',
        ]);
        $this->orgamaxInvoice(['external_id' => 'om-6', 'customer_id' => $this->customer->id,
            'invoice_number' => 'RE/2026/2220', 'invoice_status' => 'locked',
            'total_gross' => '300.00', 'outstanding_amount' => '300.00']);

        $rows = collect((new DocumentFeedQuery($this->filters()))->paginate(30, 'date', 'desc')->items())
            ->filter(fn (object $row): bool => $row->number === 'RE/2026/2220');

        $this->assertCount(1, $rows, 'Übergebene Rechnung darf nur einmal erscheinen.');
        $this->assertSame('orgamax_invoice', $rows->first()->source_type, 'Extern führt.');
        $this->assertEqualsWithDelta(300.0, (new DocumentFeedQuery($this->filters()))->totals()[0]['revenue'], 0.001);
    }

    public function test_legacy_routes_redirect_into_the_feed(): void {
        $this->actingAs($this->admin)->get(route('invoices.index'))
            ->assertRedirect(route('billing.feed', ['tab' => 'outgoing']));
        $this->actingAs($this->admin)->get(route('quotes.index'))
            ->assertRedirect(route('billing.feed', ['tab' => 'quotes']));
        $this->actingAs($this->admin)->get(route('lexoffice.vouchers.index'))
            ->assertRedirect(route('billing.feed', ['origin' => 'lexoffice']));
    }

    /** @return array<string, string> */
    private function range(): array {
        return [
            'ui.daterange.preset' => 'custom',
            'ui.daterange.from' => '2026-01-01',
            'ui.daterange.to' => '2026-12-31',
        ];
    }
}
