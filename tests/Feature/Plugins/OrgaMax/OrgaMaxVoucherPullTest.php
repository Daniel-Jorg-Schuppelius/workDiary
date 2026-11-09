<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxVoucherPullTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\OrgaMax;

use App\Enums\Billing\{DocumentDirection, DocumentKind, DocumentOrigin};
use App\Enums\Finance\AccountingVoucherState;
use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Customer\Customer;
use App\Models\Finance\{AccountingArticle, AccountingVoucher};
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\{PluginSetting, User};
use App\Models\Supplier\Supplier;
use App\Plugins\OrgaMax\Enums\OrgaMaxConnectionStatus;
use App\Plugins\OrgaMax\Models\{OrgaMaxConnection, OrgaMaxInvoice};
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Services\Billing\{DocumentFeedFilters, DocumentFeedQuery};
use App\Services\Finance\Accounting\Vouchers\{LineAwareVoucherPuller, VoucherMirror, VoucherPullerRegistry};
use App\Services\Reselling\Plan\AccountingRecurringPlanSource;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * orgaMAX als Belegspiegel (MVP-1501): Rechnungen und Ausgaben landen in
 * `accounting_vouchers` und zugleich die Rechnungen in `orgamax_invoices`;
 * Positionen aus `GET /invoice/{id}`, Serienvorlagen für die Planquelle,
 * Artikel als Katalogquelle; der Belegfluss zeigt jede Rechnung einmal.
 */
final class OrgaMaxVoucherPullTest extends TestCase {
    use OrgaMaxApiResponses;
    use RefreshDatabase;
    use WithOrganization;

    private const string INVOICES = 'https://api.orgamax.de/openapi/invoice*';

    private const string EXPENSES = 'https://api.orgamax.de/openapi/expense*';

    private User $admin;

    private Customer $customer;

    private Supplier $supplier;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        PluginSetting::create(['organization_id' => $this->organization->id, 'plugin_id' => OrgaMaxPlugin::ID, 'enabled' => true, 'settings' => []]);
        OrgaMaxConnection::create([
            'organization_id' => $this->organization->id, 'mode' => OrgaMaxConnection::MODE_PRIVATE, 'api_key' => 'k', 'api_secret' => 's',
            'ownership_id' => 'own-1', 'bearer_token' => 'token', 'token_expires_at' => Carbon::now()->addHour(), 'status' => OrgaMaxConnectionStatus::Active,
            'capabilities' => [
                'billing' => ['enabled' => true, 'leader' => 'orgamax'],
                'expenses' => ['enabled' => true, 'leader' => 'orgamax'],
                'articles' => ['enabled' => true, 'leader' => 'orgamax'],
            ],
        ]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Strandkorb Verleih Nord']);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Werft Möwengrund']);
        // Der Stammdatenimport verknüpft unter `customer` bzw. `supplier`; Kunden- und Lieferanten-IDs zählen getrennt.
        ExternalReference::link($this->organization->id, OrgaMaxPlugin::ID, 'customer', $this->customer, '7');
        ExternalReference::link($this->organization->id, OrgaMaxPlugin::ID, 'supplier', $this->supplier, '7');
    }

    /** @return array<string, mixed> */
    private function fakes(): array {
        return [
            'https://api.orgamax.de/openapi/invoice/501' => self::itemResponse([
                'id' => 501, 'number' => 'OM-2026-501', 'type' => 'invoice', 'state' => 'locked', 'totalNet' => 494.4, 'totalGross' => 588.34,
                'positions' => [
                    ['id' => 'p1', 'title' => 'Wartungsvertrag Segelboot', 'description' => 'Jahrespauschale', 'amount' => 2, 'unit' => 'Stk.', 'vatPercent' => 19, 'priceNet' => 247.2, 'totalNet' => 494.4, 'metaData' => ['id' => 55, 'type' => 'article', 'number' => 'A-55']],
                ],
            ]),
            self::INVOICES => self::listResponse([
                ['id' => 501, 'number' => 'OM-2026-501', 'date' => '2026-09-03', 'dueToDate' => '2026-09-17', 'type' => 'invoice', 'state' => 'locked', 'customerId' => 7, 'totalNet' => 494.4, 'totalGross' => 588.34, 'outstandingAmount' => 588.34],
                ['id' => 502, 'number' => 'OM-2026-502', 'date' => '2026-09-04', 'type' => 'depositInvoice', 'state' => 'partiallyPaid', 'customerId' => 7, 'totalNet' => 200.0, 'totalGross' => 238.0, 'outstandingAmount' => 100.0],
                ['id' => 503, 'number' => 'OM-SERIE-1', 'date' => '2026-01-01', 'type' => 'recurringInvoiceTemplate', 'state' => 'draft', 'customerId' => 7, 'totalNet' => 99.0, 'totalGross' => 117.81],
                ['id' => 504, 'number' => 'OM-2026-504', 'date' => '2026-09-05', 'type' => 'invoice', 'state' => 'cancelled', 'customerId' => 7, 'totalGross' => 50.0, 'outstandingAmount' => 0.0],
            ]),
            self::EXPENSES => self::listResponse([
                ['id' => 9001, 'date' => '2026-09-06T00:00:00.000Z', 'payDate' => '2026-09-07T00:00:00.000Z', 'payKind' => 'bank', 'payee' => 'Werft Möwengrund', 'description' => 'Liegeplatz', 'price' => 100.0, 'priceTotal' => 119.0, 'receiptNumber' => 'ER-2026-11', 'supplierId' => '7', 'outstandingAmount' => 0.0],
                ['id' => 9002, 'date' => '2026-09-08T00:00:00.000Z', 'payKind' => 'open', 'payee' => 'Werft Möwengrund', 'price' => 42.02, 'priceTotal' => 50.0, 'receiptNumber' => 'ER-2026-12', 'outstandingAmount' => 50.0],
            ]),
        ];
    }

    private function puller(): LineAwareVoucherPuller {
        $puller = app(VoucherPullerRegistry::class)->find(OrgaMaxPlugin::ID);
        $this->assertInstanceOf(LineAwareVoucherPuller::class, $puller);

        return $puller;
    }

    public function test_invoices_and_expenses_land_in_both_mirrors_with_the_right_party(): void {
        FakePluginHttp::fake($this->fakes());

        $result = $this->puller()->pull((int) $this->organization->id);

        $this->assertSame(6, $result['created']);
        $invoice = AccountingVoucher::query()->where('plugin_id', OrgaMaxPlugin::ID)->where('external_id', '501')->firstOrFail();
        $this->assertSame([DocumentDirection::Outgoing->value, DocumentKind::Invoice->value, AccountingVoucherState::Open, '588.34', '588.34', '2026-09-17'], [$invoice->direction, $invoice->document_kind, $invoice->voucher_state, (string) $invoice->total_amount, (string) $invoice->open_amount, $invoice->due_date?->toDateString()]);
        $this->assertSame([$this->customer->id, null], [$invoice->customer_id, $invoice->supplier_id], 'Kundennummer 7 trifft den Kunden, nicht den Lieferanten mit derselben ID.');
        $deposit = AccountingVoucher::query()->where('external_id', '502')->firstOrFail();
        $this->assertSame([DocumentKind::DownPayment->value, AccountingVoucherState::Open, '100.00'], [$deposit->document_kind, $deposit->voucher_state, (string) $deposit->open_amount]);
        $this->assertSame(AccountingVoucherState::Cancelled, AccountingVoucher::query()->where('external_id', '504')->firstOrFail()->voucher_state);

        $expense = AccountingVoucher::query()->where('external_id', 'expense:9001')->firstOrFail();
        $this->assertSame([DocumentDirection::Incoming->value, DocumentKind::Expense->value, AccountingVoucherState::Paid, 'ER-2026-11', '119.00', $this->supplier->id, null], [$expense->direction, $expense->document_kind, $expense->voucher_state, $expense->voucher_number, (string) $expense->total_amount, $expense->supplier_id, $expense->customer_id]);
        $open = AccountingVoucher::query()->where('external_id', 'expense:9002')->firstOrFail();
        $this->assertSame([AccountingVoucherState::Open, $this->supplier->id], [$open->voucher_state, $open->supplier_id], 'Ohne Lieferanten-ID zählt der Zahlungsempfänger.');
        $this->assertSame(2, AccountingVoucher::query()->purchases()->where('plugin_id', OrgaMaxPlugin::ID)->count(), 'Ausgaben sind Einkaufsbelege.');

        // Der Plugin-Spiegel (Wechsel, Belegverbund, PDF) entsteht aus derselben Zeile.
        $this->assertSame(['501', '502', '503', '504'], OrgaMaxInvoice::query()->orderBy('external_id')->pluck('external_id')->all());
        $this->assertSame($this->customer->id, OrgaMaxInvoice::query()->where('external_id', '501')->value('customer_id'));

        // Zweiter Lauf aktualisiert statt zu verdoppeln.
        $again = $this->puller()->pull((int) $this->organization->id);
        $this->assertSame([0, 6], [$again['created'], $again['updated']]);
    }

    public function test_recurring_templates_feed_the_plan_source_with_their_positions(): void {
        FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/invoice/503' => self::itemResponse([
                'id' => 503, 'number' => 'OM-SERIE-1', 'type' => 'recurringInvoiceTemplate', 'state' => 'draft',
                'positions' => [['title' => 'Hafenliegeplatz Monat', 'amount' => 1, 'unit' => 'Monat', 'vatPercent' => 19, 'priceNet' => 99.0, 'totalNet' => 99.0]],
            ]),
        ] + $this->fakes());
        $this->puller()->pull((int) $this->organization->id);
        // Serienvorlagen sind richtungsneutral; der Positionsabruf lädt sie trotzdem.
        app(VoucherMirror::class)->pullPendingLines($this->puller(), (int) $this->organization->id);

        $template = AccountingVoucher::query()->where('external_id', '503')->firstOrFail();
        $this->assertSame([DocumentKind::RecurringTemplate->value, DocumentDirection::Neutral->value, 'ACTIVE', ''], [$template->document_kind, $template->direction, data_get($template->payload, 'plan.status'), data_get($template->payload, 'plan.interval')]);

        $templates = app(AccountingRecurringPlanSource::class)->templatesFor($this->organization);
        $this->assertSame(['OM-SERIE-1'], $templates->map->title->all());
        $this->assertSame($this->customer->id, $templates->first()?->recipientCustomerId);
        $this->assertSame(['Hafenliegeplatz Monat'], array_map(static fn ($line): string => $line->name, $templates->first()->lines ?? []));
    }

    public function test_positions_come_from_the_invoice_detail_and_expenses_have_none(): void {
        $fake = FakePluginHttp::fake($this->fakes());
        $this->puller()->pull((int) $this->organization->id);

        $lines = $this->puller()->pullLines((int) $this->organization->id, AccountingVoucher::query()->where('external_id', '501')->firstOrFail());
        $this->assertCount(1, $lines);
        $this->assertSame(['Wartungsvertrag Segelboot', 2.0, '247.2000', '494.40', '55', 'Stk.', '19.00'], [$lines[0]->name, $lines[0]->quantity, $lines[0]->unitNet, $lines[0]->totalNet, $lines[0]->externalArticleId, $lines[0]->unitName, $lines[0]->taxRate]);

        $count = count($fake->recorded());
        $this->assertSame([], $this->puller()->pullLines((int) $this->organization->id, AccountingVoucher::query()->where('external_id', 'expense:9001')->firstOrFail()));
        $this->assertCount($count, $fake->recorded(), 'Ausgaben haben keinen Positionsabruf.');
    }

    public function test_feed_shows_each_orgamax_invoice_once_with_the_original_pdf(): void {
        Invoice::query()->create([
            'organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'number' => 'OM-2026-501', 'status' => InvoiceStatus::Issued,
            'type' => Invoice::TYPE_INVOICE, 'category' => Invoice::CATEGORY_SERVICE, 'issued_on' => '2026-09-03', 'currency' => 'EUR', 'total' => '588.34',
        ]);
        FakePluginHttp::fake($this->fakes());
        $this->puller()->pull((int) $this->organization->id);

        $filters = new DocumentFeedFilters(
            organizationId: (int) $this->organization->id,
            userId: (int) $this->admin->id,
            from: CarbonImmutable::parse('2026-01-01')->startOfDay(),
            to: CarbonImmutable::parse('2026-12-31')->endOfDay(),
            sources: ['invoice' => true, 'quote' => true, 'voucher' => true, 'incoming_einvoice' => true, 'expense' => true],
        );
        $rows = collect((new DocumentFeedQuery($filters))->paginate(50, 'date', 'desc')->items());

        $this->assertCount(1, $rows->where('number', 'OM-2026-501'), 'Lokale Rechnung und Spiegel desselben Kunden: einmal.');
        $this->assertSame(DocumentOrigin::OrgaMax->value, $rows->firstWhere('number', 'OM-2026-501')?->origin);
        $this->assertNull($rows->firstWhere('number', 'OM-SERIE-1'), 'Serienvorlagen gehören nicht in den Belegfluss.');
        $this->assertSame(DocumentDirection::Incoming->value, $rows->firstWhere('number', 'ER-2026-11')?->direction);

        $mirror = OrgaMaxInvoice::query()->where('external_id', '501')->firstOrFail();
        $this->actingAs($this->admin)->withSession(['ui.daterange.preset' => 'custom', 'ui.daterange.from' => '2026-01-01', 'ui.daterange.to' => '2026-12-31'])->get(route('billing.feed', ['tab' => 'outgoing']))->assertOk()
            ->assertSee('OM-2026-501')
            ->assertSee(route('admin.orgamax.invoices.mirror-pdf', Sqid::encode(OrgaMaxInvoice::class, (int) $mirror->id)), false);
    }

    public function test_scheduled_sync_mirrors_catalog_vouchers_and_positions(): void {
        $fake = FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/article*' => self::listResponse([
                ['id' => 55, 'number' => 'A-55', 'title' => 'Wartungsvertrag Segelboot', 'unit' => 'Stk.', 'price' => 247.2, 'vatPercent' => 19, 'isStockManaged' => false],
                ['id' => 56, 'number' => 'A-56', 'title' => 'Bootslack 2,5 l', 'unit' => 'Dose', 'price' => 39.9, 'vatPercent' => 19, 'isStockManaged' => true],
            ]),
        ] + $this->fakes());

        $this->artisan('orgamax:sync', ['--org' => $this->organization->id])
            ->expectsOutputToContain('"vouchers_created":6')
            ->assertSuccessful();

        $this->assertSame(['material', 'service'], AccountingArticle::query()->where('plugin_id', OrgaMaxPlugin::ID)->orderBy('kind')->pluck('kind')->all());
        $invoice = AccountingVoucher::query()->where('external_id', '501')->firstOrFail();
        $this->assertNotNull($invoice->lines_synced_at);
        $line = $invoice->lines()->firstOrFail();
        $this->assertStringStartsWith('om:', (string) $line->article_ref, 'Die Position trägt den Katalogschlüssel.');
        $fake->assertSent(static fn (RequestInterface $request): bool => str_ends_with($request->getUri()->getPath(), '/openapi/invoice/501'));
    }

    public function test_switched_off_plugin_or_missing_capability_mirrors_nothing(): void {
        PluginSetting::query()->where('plugin_id', OrgaMaxPlugin::ID)->update(['enabled' => false]);
        $fake = FakePluginHttp::fake($this->fakes());

        $this->assertFalse($this->puller()->isConfigured((int) $this->organization->id));
        $this->assertSame(0, $this->puller()->pull((int) $this->organization->id)['read']);

        PluginSetting::query()->where('plugin_id', OrgaMaxPlugin::ID)->update(['enabled' => true]);
        OrgaMaxConnection::query()->firstOrFail()->forceFill(['capabilities' => ['customers' => ['enabled' => true, 'leader' => 'orgamax']]])->save();
        $this->assertFalse($this->puller()->isConfigured((int) $this->organization->id), 'Ohne Faktura, Zahlungen oder Ausgaben kein Belegspiegel.');
        $fake->assertNothingSent();
        $this->assertSame(0, AccountingVoucher::query()->count());
    }
}
