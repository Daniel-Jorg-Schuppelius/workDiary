<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskVoucherPullTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\SevDesk;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Enums\Finance\AccountingVoucherState;
use App\Models\Finance\AccountingVoucher;
use App\Models\Platform\PluginSetting;
use App\Models\Supplier\Supplier;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Services\Billing\Contracts\{ExternalPurchases, ExternalRevenue};
use App\Services\Billing\Dto\ExternalInvoiceQuery;
use App\Services\Finance\Accounting\Vouchers\VoucherPullerRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-611/731: Beleg-Rückabruf aus sevDesk auf dem gemeinsamen
 * VoucherPuller-Vertrag — inkl. der normalisierten Spalten (Richtung,
 * Belegart, Zustand) und des zentralen Kommandos `accounting:pull-vouchers`.
 */
class SevDeskVoucherPullTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string VOUCHERS = 'https://my.sevdesk.de/api/v1/Voucher*';

    private const string INVOICES = 'https://my.sevdesk.de/api/v1/Invoice*';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => SevDeskPlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'tok-123'],
        ]);
    }

    /** @return array<string, mixed> */
    private function fixture(): array {
        /** @var array<string, mixed> $data */
        $data = json_decode((string) file_get_contents(base_path('tests/Fixtures/Plugins/SevDesk/vouchers.json')), true);

        return $data;
    }

    public function test_maps_direction_state_and_supplier(): void {
        Supplier::create([
            'organization_id' => $this->organization->id,
            'name' => 'Bueromarkt AG',
        ]);

        FakePluginHttp::fake([self::VOUCHERS => FakePluginHttp::response($this->fixture()), self::INVOICES => FakePluginHttp::response(['objects' => []])]);

        $puller = app(VoucherPullerRegistry::class)->find(SevDeskPlugin::ID);
        $this->assertNotNull($puller);
        $result = $puller->pull((int) $this->organization->id);

        $this->assertSame(2, $result['created']);

        // C = credit, Ausgabe („You bought something“) → eingehend, Status 1000 = bezahlt.
        $expense = AccountingVoucher::query()->where('external_id', '77001')->firstOrFail();
        $this->assertSame(DocumentDirection::Incoming->value, $expense->direction);
        $this->assertSame(DocumentKind::Other->value, $expense->document_kind);
        $this->assertSame(AccountingVoucherState::Paid, $expense->voucher_state);
        $this->assertSame(['59.50', '0.00', 'BELEG-77001', '2026-08-16'], [(string) $expense->total_amount, (string) $expense->open_amount, $expense->voucher_number, $expense->due_date?->toDateString()]);
        $this->assertNotNull($expense->supplier_id);
        $this->assertFalse($expense->is_cancellation);

        // D = debit, Einnahme („You sold something“) → ausgehend, Status 750 = teilbezahlt, offen aus `paidAmount`.
        $income = AccountingVoucher::query()->where('external_id', '77002')->firstOrFail();
        $this->assertSame(DocumentDirection::Outgoing->value, $income->direction);
        $this->assertSame(AccountingVoucherState::Open, $income->voucher_state);
        $this->assertSame('790.00', (string) $income->open_amount);
        $this->assertNull($income->supplier_id);
    }

    public function test_expense_voucher_is_a_purchase_and_never_revenue(): void {
        $supplier = Supplier::create(['organization_id' => $this->organization->id, 'name' => 'Bueromarkt AG']);
        FakePluginHttp::fake([self::VOUCHERS => FakePluginHttp::response($this->fixture()), self::INVOICES => FakePluginHttp::response(['objects' => []])]);
        app(VoucherPullerRegistry::class)->find(SevDeskPlugin::ID)?->pull((int) $this->organization->id);

        $revenue = collect(app(ExternalRevenue::class)->invoices(new ExternalInvoiceQuery))->pluck('number')->all();
        $this->assertSame(['BELEG-77002'], $revenue, 'Nur der Einnahmebeleg (D) zählt als Umsatz');
        $purchases = collect(app(ExternalPurchases::class)->purchases(null, null, [(int) $supplier->id]))->pluck('amount')->all();
        $this->assertSame([59.5], $purchases, 'Der Ausgabebeleg (C) ist ein Einkauf');
    }

    public function test_mirror_row_of_a_transferred_incoming_invoice_stays_incoming_after_the_pull(): void {
        // Der Rechnungseingang (MVP-1507) legt den Beleg mit C an und spiegelt ihn sofort als eingehend.
        AccountingVoucher::factory()->create([
            'organization_id' => $this->organization->id, 'plugin_id' => SevDeskPlugin::ID, 'external_id' => '77001',
            'voucher_type' => 'C', 'direction' => DocumentDirection::Incoming->value, 'document_kind' => DocumentKind::Other->value,
        ]);
        FakePluginHttp::fake([self::VOUCHERS => FakePluginHttp::response($this->fixture()), self::INVOICES => FakePluginHttp::response(['objects' => []])]);

        app(VoucherPullerRegistry::class)->find(SevDeskPlugin::ID)?->pull((int) $this->organization->id);

        $this->assertSame([DocumentDirection::Incoming->value], AccountingVoucher::query()->where('external_id', '77001')->pluck('direction')->all());
    }

    public function test_invoices_are_mirrored_with_their_positions_and_recurring_invoices_as_plan(): void {
        // `GET /Invoice` (Feature 185, MVP-1425): RE = Rechnung mit Status 200 offen; WKR = Serienrechnung als Plan (accountIntervall ISO-8601).
        FakePluginHttp::fake([
            self::VOUCHERS => FakePluginHttp::response(['objects' => []]),
            'https://my.sevdesk.de/api/v1/Invoice/9001/getPositions*' => FakePluginHttp::response(['objects' => [
                ['id' => '1', 'positionNumber' => '0', 'name' => 'Microsoft 365 Business Premium', 'text' => 'Jahreslizenz', 'quantity' => 2, 'priceNet' => '247.2', 'sumNetAccounting' => '494.4', 'taxRate' => '19', 'part' => ['id' => '555', 'objectName' => 'Part'], 'unity' => ['id' => '1', 'objectName' => 'Unity', 'name' => 'Stück']],
            ]]),
            self::INVOICES => FakePluginHttp::response(['objects' => [
                ['id' => '9001', 'objectName' => 'Invoice', 'invoiceNumber' => 'RE-1001', 'invoiceType' => 'RE', 'status' => '200', 'invoiceDate' => '2026-09-03 00:00:00', 'sumNet' => '494.4', 'sumGross' => '588.34', 'paidAmount' => 288.34, 'currency' => 'EUR', 'contact' => ['id' => '42', 'objectName' => 'Contact'], 'update' => '2026-09-03 10:00:00'],
                ['id' => '9002', 'objectName' => 'Invoice', 'invoiceNumber' => 'WKR-7', 'invoiceType' => 'WKR', 'status' => '200', 'invoiceDate' => '2025-09-03 00:00:00', 'accountIntervall' => 'P1Y', 'accountNextInvoice' => 1788652800, 'currency' => 'EUR', 'contact' => ['id' => '42', 'objectName' => 'Contact']],
            ]]),
        ]);
        $puller = app(VoucherPullerRegistry::class)->find(SevDeskPlugin::ID);
        $this->assertInstanceOf(\App\Services\Finance\Accounting\Vouchers\LineAwareVoucherPuller::class, $puller);
        $result = $puller->pull((int) $this->organization->id);
        $this->assertSame(2, $result['created']);

        $invoice = AccountingVoucher::query()->where('external_id', 'invoice:9001')->firstOrFail();
        $this->assertSame(DocumentDirection::Outgoing->value, $invoice->direction);
        $this->assertSame(DocumentKind::Invoice->value, $invoice->document_kind);
        $this->assertSame(AccountingVoucherState::Open, $invoice->voucher_state);
        $this->assertSame('494.40', (string) $invoice->net_amount);
        // Offener Betrag aus `paidAmount` (Model_InvoiceResponse), Grundlage des Pauschal-Modus (MVP-1505).
        $this->assertSame('300.00', (string) $invoice->open_amount);
        $this->assertNull(AccountingVoucher::query()->where('external_id', 'invoice:9002')->value('open_amount'), 'Serienvorlage ohne offenen Betrag');
        $this->assertNull($invoice->lines_synced_at, 'Positionen kommen erst im Einzelabruf');
        $lines = $puller->pullLines((int) $this->organization->id, $invoice);
        $this->assertCount(1, $lines);
        $this->assertSame(['Microsoft 365 Business Premium', 2.0, '247.2000', '494.40', '555', 'Stück'], [$lines[0]->name, $lines[0]->quantity, $lines[0]->unitNet, $lines[0]->totalNet, $lines[0]->externalArticleId, $lines[0]->unitName]);

        $plan = AccountingVoucher::query()->where('external_id', 'invoice:9002')->firstOrFail();
        $this->assertSame(DocumentKind::RecurringTemplate->value, $plan->document_kind);
        $this->assertSame(DocumentDirection::Neutral->value, $plan->direction);
        $this->assertSame(['ACTIVE', 'ANNUALLY', '2026-09-06'], [data_get($plan->payload, 'plan.status'), data_get($plan->payload, 'plan.interval'), data_get($plan->payload, 'plan.next_execution_on')]);

        // Der Belegabruf lädt die Positionen nach und stempelt den Beleg.
        $this->artisan('accounting:pull-vouchers', ['--organization' => $this->organization->id])->assertSuccessful();
        $this->assertNotNull($invoice->fresh()?->lines_synced_at);
        $this->assertSame(1, $invoice->lines()->count());
    }

    public function test_credit_notes_are_mirrored_as_revenue_reduction_with_positions(): void {
        // `GET /CreditNote` und `GET /CreditNotePos` (MVP-1504); Status laut Doku 100 Entwurf, 200 offen, 1000 bezahlt.
        $fake = FakePluginHttp::fake([
            self::VOUCHERS => FakePluginHttp::response(['objects' => []]),
            self::INVOICES => FakePluginHttp::response(['objects' => []]),
            'https://my.sevdesk.de/api/v1/CreditNotePos*' => FakePluginHttp::response(['objects' => [
                ['id' => '31', 'objectName' => 'CreditNotePos', 'positionNumber' => '0', 'name' => 'Gutschrift Wartungsvertrag', 'text' => 'Ausfall Oktober', 'quantity' => '2', 'priceNet' => '50', 'sumDiscount' => '10', 'taxRate' => '19', 'part' => ['id' => '555', 'objectName' => 'Part'], 'unity' => ['id' => '1', 'objectName' => 'Unity', 'name' => 'Stück']],
            ]]),
            'https://my.sevdesk.de/api/v1/CreditNote*' => FakePluginHttp::response(['objects' => [
                ['id' => '701', 'objectName' => 'CreditNote', 'creditNoteNumber' => 'GU-1001', 'status' => '200', 'creditNoteDate' => '2026-09-10T00:00:00+02:00', 'sumNet' => '90', 'sumGross' => '107.1', 'currency' => 'EUR', 'contact' => ['id' => '42', 'objectName' => 'Contact'], 'update' => '2026-09-10T10:00:00+02:00'],
                ['id' => '702', 'objectName' => 'CreditNote', 'status' => '100', 'creditNoteDate' => '2026-09-12T00:00:00+02:00', 'sumNet' => '20', 'sumGross' => '23.8', 'currency' => 'EUR'],
            ]]),
        ]);

        $puller = app(VoucherPullerRegistry::class)->find(SevDeskPlugin::ID);
        $this->assertInstanceOf(\App\Services\Finance\Accounting\Vouchers\LineAwareVoucherPuller::class, $puller);
        $this->assertSame(2, $puller->pull((int) $this->organization->id)['created']);

        $credit = AccountingVoucher::query()->where('external_id', 'creditnote:701')->firstOrFail();
        $this->assertSame([DocumentDirection::Outgoing->value, DocumentKind::CreditNote->value, AccountingVoucherState::Open, 'GU-1001', '107.10', '2026-09-10'], [$credit->direction, $credit->document_kind, $credit->voucher_state, $credit->voucher_number, (string) $credit->total_amount, $credit->voucher_date?->toDateString()]);
        $this->assertSame('42', $credit->contact_external_id);
        $this->assertSame(AccountingVoucherState::Draft, AccountingVoucher::query()->where('external_id', 'creditnote:702')->firstOrFail()->voucher_state);
        $fake->assertSent(static function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return str_ends_with($request->getUri()->getPath(), '/CreditNote') && ($query['orderByCreditNoteNumber'] ?? null) === 'DESC';
        });

        // Gutschriftpositionen tragen keine Summe: Menge × Preis abzüglich Rabattsumme.
        $lines = $puller->pullLines((int) $this->organization->id, $credit);
        $this->assertCount(1, $lines);
        $this->assertSame(['Gutschrift Wartungsvertrag', 2.0, '50.0000', '90.00', '555', 'Stück'], [$lines[0]->name, $lines[0]->quantity, $lines[0]->unitNet, $lines[0]->totalNet, $lines[0]->externalArticleId, $lines[0]->unitName]);
        $fake->assertSent(static function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return str_ends_with($request->getUri()->getPath(), '/CreditNotePos') && ($query['creditNote'] ?? null) === ['id' => '701', 'objectName' => 'creditNote'];
        });
    }

    public function test_command_pulls_configured_providers_without_duplicating(): void {
        FakePluginHttp::fake([self::VOUCHERS => FakePluginHttp::response($this->fixture()), self::INVOICES => FakePluginHttp::response(['objects' => []])]);

        $this->artisan('accounting:pull-vouchers', ['--organization' => $this->organization->id])
            ->assertSuccessful();
        $this->artisan('accounting:pull-vouchers', ['--organization' => $this->organization->id])
            ->assertSuccessful();

        $this->assertSame(2, AccountingVoucher::query()->count());
    }

    public function test_command_rejects_an_unknown_provider(): void {
        $this->artisan('accounting:pull-vouchers', ['--plugin' => 'nope'])->assertFailed();
    }

    public function test_scheduled_sevdesk_run_also_loads_positions(): void {
        // Geplant ist `sevdesk:pull-vouchers` (Nummernrückmeldung), nicht der allgemeine Abruf — er lädt die Positionen mit (MVP-1490).
        FakePluginHttp::fake([
            self::VOUCHERS => FakePluginHttp::response(['objects' => []]),
            'https://my.sevdesk.de/api/v1/Invoice/9001/getPositions*' => FakePluginHttp::response(['objects' => [
                ['id' => '1', 'positionNumber' => '0', 'name' => 'Wartungspauschale', 'quantity' => 1, 'priceNet' => '120', 'sumNetAccounting' => '120', 'taxRate' => '19'],
            ]]),
            self::INVOICES => FakePluginHttp::response(['objects' => [
                ['id' => '9001', 'objectName' => 'Invoice', 'invoiceNumber' => 'RE-1001', 'invoiceType' => 'RE', 'status' => '200', 'invoiceDate' => '2026-09-03 00:00:00', 'sumNet' => '120', 'sumGross' => '142.8', 'currency' => 'EUR', 'contact' => ['id' => '42', 'objectName' => 'Contact'], 'update' => '2026-09-03 10:00:00'],
            ]]),
        ]);

        $this->artisan('sevdesk:pull-vouchers', ['--organization' => $this->organization->id])
            ->expectsOutputToContain('Positionen für 1 Belege')
            ->assertSuccessful();

        $invoice = AccountingVoucher::query()->where('external_id', 'invoice:9001')->firstOrFail();
        $this->assertNotNull($invoice->lines_synced_at);
        $this->assertSame(['Wartungspauschale'], $invoice->lines()->pluck('name')->all());
    }

    public function test_switched_off_plugin_with_a_key_mirrors_nothing(): void {
        PluginSetting::query()->firstOrFail()->update(['enabled' => false]);
        $fake = FakePluginHttp::fake([self::VOUCHERS => FakePluginHttp::response($this->fixture()), self::INVOICES => FakePluginHttp::response(['objects' => []])]);

        $this->artisan('accounting:pull-vouchers', ['--plugin' => SevDeskPlugin::ID])->assertSuccessful();

        $fake->assertNothingSent();
        $this->assertSame(0, AccountingVoucher::query()->count());
    }
}
