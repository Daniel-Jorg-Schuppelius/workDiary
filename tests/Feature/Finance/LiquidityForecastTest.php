<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LiquidityForecastTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AccountType, MandateKind, MandateStatus, PaymentRunStatus, PostingAccountRole, PostingSourceKind, ProfitDetermination, SettlementKind};
use App\Enums\Invoicing\{IncomingEInvoiceStatus, InvoiceStatus};
use App\Models\Accounting\{AccountingAccount, AccountingPostingRule};
use App\Models\Customer\Customer;
use App\Models\Document\Document;
use App\Models\Finance\{BankAccount, PaymentRun, SepaMandate};
use App\Models\Invoicing\{IncomingEInvoice, Invoice};
use App\Models\Platform\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsService, FiscalYearService, JournalService};
use App\Services\Accounting\Posting\{PostingInboxService, PostingSourceRegistry};
use App\Services\Accounting\Reports\LiquidityForecastBuilder;
use App\Support\MorphMap;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 13-Wochen-Liquiditätsvorschau (Feature 136, MVP-701): deterministische
 * Wochen-Buckets, Zahlungsverhalten (Ø-Verzug je Kunde), Skontotermin bei
 * Kreditoren, kumulierter Saldo, Mandantengrenze und Recht.
 */
class LiquidityForecastTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    /** @var array<string, AccountingAccount> */
    private array $accounts = [];

    private CarbonImmutable $startsOn;

    /** Mittwoch, 4. März 2026 — ISO-Woche 10 beginnt am Montag, 2. März. */
    private CarbonImmutable $today;

    protected function setUp(): void {
        parent::setUp();
        $this->today = CarbonImmutable::create(2026, 3, 4, 9, 0, 0, 'UTC');
        $this->travelTo($this->today);

        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);

        $this->startsOn = CarbonImmutable::create(2026, 1, 1);
        app(AccountingProfileService::class)->configure($this->org, [
            'profit_determination' => ProfitDetermination::DoubleEntry,
            'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1,
            'starts_on' => $this->startsOn,
            'note' => null,
        ]);
        app(FiscalYearService::class)->create($this->org, $this->startsOn);
        app(AccountingProfileService::class)->activateLocal($this->org, $this->admin);

        $chart = app(ChartOfAccountsService::class);
        $this->accounts['receivable'] = $chart->create($this->org, ['number' => '1400', 'name' => 'Forderungen', 'type' => AccountType::Asset, 'is_open_item' => true]);
        $this->accounts['payable'] = $chart->create($this->org, ['number' => '1600', 'name' => 'Verbindlichkeiten', 'type' => AccountType::Liability, 'is_open_item' => true]);
        $this->accounts['revenue'] = $chart->create($this->org, ['number' => '8400', 'name' => 'Erlöse 19 %', 'type' => AccountType::Income]);
        $this->accounts['expense'] = $chart->create($this->org, ['number' => '4900', 'name' => 'Aufwand', 'type' => AccountType::Expense]);
        $this->accounts['tax'] = $chart->create($this->org, ['number' => '1776', 'name' => 'Umsatzsteuer 19 %', 'type' => AccountType::Liability]);
        $this->accounts['bank'] = $chart->create($this->org, ['number' => '1200', 'name' => 'Bank', 'type' => AccountType::Asset, 'is_bank' => true]);
        $this->accounts['equity'] = $chart->create($this->org, ['number' => '9000', 'name' => 'Saldenvorträge', 'type' => AccountType::Equity]);

        foreach ([
            [PostingSourceKind::SalesInvoice, PostingAccountRole::Receivable, 'receivable', []],
            [PostingSourceKind::SalesInvoice, PostingAccountRole::Revenue, 'revenue', ['tax_rate' => '19.00']],
            [PostingSourceKind::SalesInvoice, PostingAccountRole::TaxOutput, 'tax', ['tax_rate' => '19.00']],
            [PostingSourceKind::Payment, PostingAccountRole::Bank, 'bank', []],
            [PostingSourceKind::Payment, PostingAccountRole::Receivable, 'receivable', []],
        ] as [$kind, $role, $accountKey, $match]) {
            AccountingPostingRule::query()->create([
                'organization_id' => $this->org->id,
                'source_kind' => $kind,
                'role' => $role,
                'accounting_account_id' => $this->accounts[$accountKey]->id,
                'match_criteria' => $match === [] ? null : $match,
                'priority' => 100,
                'version' => 1,
                'valid_from' => $this->startsOn->toDateString(),
                'is_active' => true,
            ]);
        }

        // Startsaldo: 1.000,00 auf der Bank.
        app(JournalService::class)->postDirect($this->org, [
            'booked_on' => $this->startsOn,
            'memo' => 'Saldenvortrag Bank',
            'source_key' => 'forecast-test:opening',
            'lines' => [
                ['accounting_account_id' => $this->accounts['bank']->id, 'debit' => '1000.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['equity']->id, 'debit' => '0.00', 'credit' => '1000.00'],
            ],
        ], $this->admin);
    }

    private function invoice(Customer $customer, string $issuedOn, string $dueOn, string $total = '119.00'): Invoice {
        $net = number_format((float) $total / 1.19, 2, '.', '');
        $tax = number_format((float) $total - (float) $net, 2, '.', '');

        return Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => $customer->id,
            'number' => 'RE-' . fake()->unique()->numberBetween(1000, 9999),
            'status' => InvoiceStatus::Issued,
            'issued_on' => $issuedOn,
            'due_on' => $dueOn,
            'currency' => 'EUR',
            'subtotal' => $net,
            'tax_amount' => $tax,
            'total' => $total,
            'tax_breakdown' => [['rate' => '19.00', 'net' => $net, 'tax' => $tax]],
        ])->refresh();
    }

    /** Rechnung festbuchen → offener Debitorenposten mit Fälligkeit. */
    private function postInvoice(Customer $customer, string $issuedOn, string $dueOn, string $total = '119.00'): Invoice {
        $invoice = $this->invoice($customer, $issuedOn, $dueOn, $total);
        $proposal = app(PostingSourceRegistry::class)->for(PostingSourceKind::SalesInvoice)->proposalFor($this->org, $invoice);
        $entry = app(PostingInboxService::class)->prepare($this->org, $proposal, $this->admin);
        app(JournalService::class)->post($entry, $this->admin);

        return $invoice;
    }

    /** Zahlungseingang gegen den Posten der Rechnung — liefert die Verzugs-Historie. */
    private function payInvoice(Invoice $invoice, string $bookedOn, string $amount): void {
        app(JournalService::class)->postDirect($this->org, [
            'booked_on' => CarbonImmutable::parse($bookedOn),
            'memo' => 'Zahlung ' . $invoice->number,
            'source_key' => 'forecast-test:' . uniqid('', true),
            'snapshot' => [
                'settles_source_type' => MorphMap::alias(Invoice::class),
                'settles_source_id' => $invoice->id,
                'settlement_kind' => SettlementKind::Payment->value,
            ],
            'lines' => [
                ['accounting_account_id' => $this->accounts['bank']->id, 'debit' => $amount, 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['receivable']->id, 'debit' => '0.00', 'credit' => $amount],
            ],
        ], $this->admin);
    }

    /** Eingangsrechnung mit Skonto als Kreditorenposten festbuchen. */
    private function postIncomingInvoice(string $issuedOn, string $dueOn, string $gross, ?float $discountPercent = null, ?int $discountDays = null): IncomingEInvoice {
        $document = Document::factory()->create(['created_by_user_id' => $this->admin->id]);
        $incoming = IncomingEInvoice::query()->create([
            'organization_id' => $this->org->id,
            'document_id' => $document->id,
            'sha256' => hash('sha256', uniqid('', true)),
            'source' => 'upload',
            'received_at' => $this->today,
            'status' => IncomingEInvoiceStatus::Approved,
            'invoice_number' => 'ER-' . fake()->unique()->numberBetween(1000, 9999),
            'seller_name' => 'Lieferant GmbH',
            'issue_date' => $issuedOn,
            'due_date' => $dueOn,
            'currency' => 'EUR',
            'amount_gross' => $gross,
            'discount_percent' => $discountPercent,
            'discount_days' => $discountDays,
        ]);

        app(JournalService::class)->postDirect($this->org, [
            'booked_on' => CarbonImmutable::parse($issuedOn),
            'memo' => 'Eingangsrechnung ' . $incoming->invoice_number,
            'document_reference' => (string) $incoming->invoice_number,
            'source_type' => MorphMap::alias(IncomingEInvoice::class),
            'source_id' => (int) $incoming->id,
            'source_key' => 'forecast-test:' . uniqid('', true),
            'snapshot' => ['due_date' => $dueOn],
            'lines' => [
                ['accounting_account_id' => $this->accounts['expense']->id, 'debit' => $gross, 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['payable']->id, 'debit' => '0.00', 'credit' => $gross],
            ],
        ], $this->admin);

        return $incoming;
    }

    /** @return array<string, mixed> */
    private function build(int $weeks = 13): array {
        return app(LiquidityForecastBuilder::class)->build($this->org, $this->today, $weeks);
    }

    public function test_buckets_are_iso_weeks_from_today_with_the_bank_balance_as_opening(): void {
        $data = $this->build();

        $this->assertSame(13, $data['weeks']);
        $this->assertCount(13, $data['buckets']);
        $this->assertSame('1000.00', $data['opening_balance']);
        $this->assertSame('2026-03-02', $data['from']->toDateString());
        $this->assertSame('2026-05-31', $data['to']->toDateString());
        $this->assertSame('2026-W10', $data['buckets'][0]['key']);
        $this->assertSame('2026-W22', $data['buckets'][12]['key']);
        $this->assertSame('1000.00', $data['totals']['closing']);
        $this->assertSame(0, $data['totals']['items']);

        $this->assertCount(26, $this->build(26)['buckets']);
        // Unzulässiger Horizont fällt auf den Standard zurück.
        $this->assertCount(13, $this->build(9)['buckets']);
    }

    /** Ohne Historie gilt die Fälligkeit; Überfälliges landet in der laufenden Woche. */
    public function test_receivables_fall_into_the_week_of_their_due_date(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);
        $this->postInvoice($customer, '2026-03-01', '2026-03-20');   // KW 12 → Index 2
        $this->postInvoice($customer, '2026-02-01', '2026-02-15', '50.00'); // überfällig → Index 0

        $data = $this->build();

        $this->assertSame('50.00', $data['buckets'][0]['inflow']);
        $this->assertSame('119.00', $data['buckets'][2]['inflow']);
        $this->assertSame('119.00', $data['buckets'][2]['sources']['receivables']['in']);
        $this->assertNotNull($data['buckets'][0]['items'][0]['note']);
        $this->assertSame('1169.00', $data['totals']['closing']);
    }

    /** Zahlungsverhalten: Ø-Verzug des Kunden aus den letzten 12 Monaten verschiebt den Posten. */
    public function test_expected_receipt_is_shifted_by_the_customers_average_delay(): void {
        $late = Customer::factory()->create(['organization_id' => $this->org->id]);
        $prompt = Customer::factory()->create(['organization_id' => $this->org->id]);

        // Historie: 10 Tage zu spät gezahlt (Fälligkeit 20.01., Zahlung 30.01.).
        $history = $this->postInvoice($late, '2026-01-06', '2026-01-20');
        $this->payInvoice($history, '2026-01-30', '119.00');

        // Offen: beide fällig am 20.03. (KW 12, Index 2).
        $this->postInvoice($late, '2026-03-01', '2026-03-20');
        $this->postInvoice($prompt, '2026-03-01', '2026-03-20', '200.00');

        $data = $this->build();

        $this->assertSame('200.00', $data['buckets'][2]['inflow'], 'Kunde ohne Historie: Fälligkeit (KW 12)');
        $this->assertSame('0.00', $data['buckets'][3]['inflow']);
        $this->assertSame('119.00', $data['buckets'][4]['inflow'], 'Verzugs-Kunde: 20.03. + 10 Tage = 30.03. (KW 14)');
        $this->assertSame('2026-03-30', $data['buckets'][4]['items'][0]['expected_on']->toDateString());
        $this->assertStringContainsString('10', (string) $data['buckets'][4]['items'][0]['note']);
    }

    /** Kreditor mit erreichbarem Skontotermin: Zahlung zum Termin, Betrag gekürzt. */
    public function test_payables_use_the_discount_deadline_while_it_is_reachable(): void {
        // Skonto 2 % / 14 Tage ab 01.03. → 15.03. (KW 11, Index 1), Fälligkeit 31.03. (KW 14).
        $this->postIncomingInvoice('2026-03-01', '2026-03-31', '119.00', 2.0, 14);
        // Skontofrist verstrichen (01.02. + 10 = 11.02.) → Fälligkeit 10.04. (KW 15, Index 5), voller Betrag.
        $this->postIncomingInvoice('2026-02-01', '2026-04-10', '80.00', 3.0, 10);

        $data = $this->build();

        $this->assertSame('116.62', $data['buckets'][1]['outflow']);
        $this->assertSame('2026-03-15', $data['buckets'][1]['items'][0]['expected_on']->toDateString());
        $this->assertStringContainsString('2,00', (string) $data['buckets'][1]['items'][0]['note']);
        $this->assertSame('80.00', $data['buckets'][5]['outflow']);
        $this->assertNull($data['buckets'][5]['items'][0]['note']);
    }

    public function test_cumulative_balance_carries_week_by_week_and_reports_the_low(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);
        $this->postIncomingInvoice('2026-03-01', '2026-03-10', '1500.00');     // KW 11 → −500
        $this->postInvoice($customer, '2026-03-01', '2026-03-24', '900.00');   // KW 13 → +400

        $data = $this->build();

        $this->assertSame('1000.00', $data['buckets'][0]['closing']);
        $this->assertSame('-500.00', $data['buckets'][1]['closing']);
        $this->assertSame('-500.00', $data['buckets'][2]['closing']);
        $this->assertSame('400.00', $data['buckets'][3]['closing']);
        $this->assertSame('400.00', $data['totals']['closing']);
        $this->assertSame('-500.00', $data['totals']['min_closing']);
        $this->assertSame('KW 11', $data['totals']['min_week']);
    }

    public function test_the_forecast_is_scoped_to_the_organization(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);
        $this->postInvoice($customer, '2026-03-01', '2026-03-20');

        $other = Organization::factory()->create();
        $foreign = app(LiquidityForecastBuilder::class)->build($other, $this->today, 13);

        $this->assertSame('0.00', $foreign['opening_balance']);
        $this->assertSame(0, $foreign['totals']['items']);
        $this->assertSame(1, $this->build()['totals']['items']);
    }

    public function test_the_report_page_is_permission_gated_and_exports(): void {
        $member = User::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($member)->get(route('reports.accounting.liquidity-forecast'))->assertForbidden();

        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);
        $this->postInvoice($customer, '2026-03-01', '2026-03-20');

        $this->actingAs($this->admin)->get(route('reports.accounting.liquidity-forecast'))
            ->assertOk()
            ->assertSee('KW 10')
            ->assertSee((string) __('accounting.reports.forecast.hint'));

        $this->actingAs($this->admin)->get(route('reports.accounting.liquidity-forecast', ['weeks' => 26]))
            ->assertOk()
            ->assertSee('KW 35');

        $csv = (string) $this->actingAs($this->admin)
            ->get(route('reports.accounting.liquidity-forecast', ['export' => 'csv']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('#report:accounting.liquidity_forecast', $csv);
        $this->assertStringContainsString('KW 12', $csv);

        $pdf = $this->actingAs($this->admin)->get(route('reports.accounting.liquidity-forecast', ['export' => 'pdf']));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
    }

    /** MVP-954: Szenario verschiebt Forderungen, ändert Beträge und ergänzt Investitionen und Einzelposten. */
    public function test_scenario_adjusts_items_next_to_the_unchanged_base(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);
        $this->postInvoice($customer, '2026-03-01', '2026-03-20');   // KW 12 → Index 2
        \App\Models\Investments\InvestmentCase::factory()->create(['organization_id' => $this->org->id, 'title' => 'Neue Presse', 'status' => 'approved', 'starts_on' => '2026-04-15', 'estimated_amount' => '5000.00', 'currency' => 'EUR']);
        $scenario = \App\Models\Finance\LiquidityScenario::query()->create(['organization_id' => $this->org->id, 'name' => 'Engpass', 'receipt_delay_days' => 14, 'inflow_change_percent' => '-10', 'outflow_change_percent' => '0', 'is_including_investments' => true]);
        $scenario->items()->create(['organization_id' => $this->org->id, 'label' => 'Kredit', 'direction' => 'in', 'amount' => '2000.00', 'expected_on' => '2026-04-01']);

        $base = $this->build();
        $data = app(LiquidityForecastBuilder::class)->build($this->org, $this->today, 13, $scenario->load('items'));

        $this->assertSame('119.00', $base['buckets'][2]['inflow']);
        $this->assertSame('0.00', $data['buckets'][2]['inflow']);
        $this->assertSame('107.10', $data['buckets'][4]['sources']['receivables']['in']);
        $this->assertSame('2000.00', $data['buckets'][4]['sources']['scenario']['in']);
        $this->assertSame('5000.00', $data['buckets'][6]['sources']['investments']['out']);
        $this->assertContains('investments', $data['sources']);
        $this->assertNotContains('scenario', $base['sources']);
        $this->assertSame('-1892.90', $data['totals']['closing']);
    }

    public function test_scenarios_are_managed_and_shown_on_the_forecast(): void {
        $this->actingAs($this->admin)->post(route('reports.accounting.liquidity-scenarios.store'), ['name' => 'Umsatzrückgang', 'inflow_change_percent' => '-20', 'receipt_delay_days' => 30])->assertRedirect();
        $scenario = \App\Models\Finance\LiquidityScenario::query()->sole();
        $this->actingAs($this->admin)->post(route('reports.accounting.liquidity-scenarios.items.store', $scenario), ['label' => 'Steuernachzahlung', 'direction' => 'out', 'amount' => '300', 'expected_on' => '2026-03-25'])->assertSessionHas('success');

        $this->actingAs($this->admin)->get(route('reports.accounting.liquidity-scenarios.index'))->assertOk()->assertSeeText('Steuernachzahlung');
        $this->actingAs($this->admin)->get(route('reports.accounting.liquidity-forecast', ['scenario' => $scenario->sqid]))
            ->assertOk()
            ->assertSeeText('Umsatzrückgang')
            ->assertSee('700.00');

        $member = User::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($member)->get(route('reports.accounting.liquidity-scenarios.index'))->assertForbidden();
    }

    /** MVP-1011: Eine Lastschrift mit Rechnungsbezug mindert die Forderung statt sie zu verdoppeln. */
    public function test_direct_debits_with_invoice_reference_are_not_counted_twice(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);
        $full = $this->postInvoice($customer, '2026-03-01', '2026-03-20');
        $partial = $this->postInvoice($customer, '2026-03-01', '2026-03-20', '200.00');
        $account = BankAccount::factory()->create(['organization_id' => $this->org->id, 'iban' => 'DE02120300000000202051', 'bic' => 'BYLADEM1001', 'account_holder' => 'Muster GmbH']);
        $mandate = SepaMandate::query()->create([
            'organization_id' => $this->org->id, 'customer_id' => $customer->id, 'reference' => 'MND-1011', 'kind' => MandateKind::Recurring->value,
            'status' => MandateStatus::Active->value, 'signed_on' => '2025-01-01', 'iban' => 'DE89370400440532013000', 'bic' => 'COBADEFFXXX', 'account_holder' => 'Kunde AG',
        ]);

        $this->actingAs($this->admin)->get(route('invoices.show', $full))->assertOk()->assertSee(__('sepa.direct_debit_from_invoice'));
        $this->actingAs($this->admin)->get(route('finance.payment-runs.index', ['invoice' => $full->sqid]))->assertOk()
            ->assertSee('value="119.00"', false)->assertSee('value="' . $full->number . '"', false);

        foreach ([[$full, '119.00'], [$partial, '50.00']] as [$invoice, $amount]) {
            $this->actingAs($this->admin)->from(route('finance.payment-runs.index'))->post(route('finance.payment-runs.direct-debit.store'), [
                'bank_account' => $account->sqid, 'mandate' => $mandate->sqid, 'amount' => $amount, 'reference' => $invoice->number,
                'execution_date' => '2026-03-18', 'invoice' => $invoice->sqid,
            ])->assertRedirectContains('/finanzen/zahllaeufe/');
        }
        PaymentRun::query()->update(['status' => PaymentRunStatus::Released->value]);

        $data = $this->build();
        $this->assertSame('150.00', $data['buckets'][2]['sources']['receivables']['in']);
        $this->assertSame('169.00', $data['buckets'][2]['sources']['payment_runs']['in']);
        $this->assertSame('1319.00', $data['totals']['closing']);

        $foreignInvoice = $this->invoice(Customer::factory()->create(['organization_id' => $this->org->id]), '2026-03-01', '2026-03-20');
        $this->actingAs($this->admin)->from(route('finance.payment-runs.index'))->post(route('finance.payment-runs.direct-debit.store'), [
            'bank_account' => $account->sqid, 'mandate' => $mandate->sqid, 'amount' => '10.00', 'reference' => 'X', 'invoice' => $foreignInvoice->sqid,
        ])->assertRedirect(route('finance.payment-runs.index'))->assertSessionHas('error');
    }
}
