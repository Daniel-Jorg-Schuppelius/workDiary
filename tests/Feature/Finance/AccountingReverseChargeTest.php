<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingReverseChargeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Finance\{PostingSourceKind, ProfitDetermination, SettlementKind};
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceTaxCase, InvoiceStatus};
use App\Models\Accounting\{AccountingAccount, AccountingEntry, AccountingOpenItem, AccountingPostingRule, AccountingTaxCode};
use App\Models\Customer\Customer;
use App\Models\Document\Document;
use App\Models\Invoicing\{IncomingEInvoice, Invoice};
use App\Models\Platform\{Organization, User};
use App\Models\Supplier\Supplier;
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsTemplateService, FiscalYearService, JournalService, LedgerDatevExportService, OpenItemService};
use App\Services\Accounting\Filing\VatFieldBreakdownService;
use App\Services\Accounting\Posting\{PostingInboxService, PostingProposal, PostingSourceRegistry};
use App\Services\Accounting\Reports\{DataQualityBuilder, VatPreviewBuilder};
use App\Services\Invoicing\TaxResolver;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Umsatzsteuer der Empfängerseite (MVP-1621/1622) mit der SKR03-Vorlage:
 * Steuerschuld des Empfängers (§ 13b UStG, innergemeinschaftlicher Erwerb),
 * mehrere Sätze an der Eingangsrechnung, § 13b als Leistender (Kz 60) und der
 * DATEV-Stapel aus dem Journal.
 */
class AccountingReverseChargeTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private CarbonImmutable $startsOn;

    protected function setUp(): void {
        parent::setUp();
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
        app(ChartOfAccountsTemplateService::class)->apply($this->org, 'skr03', $this->startsOn);
        $this->actingAs($this->admin);
        $this->travelTo(CarbonImmutable::create(2026, 3, 2, 10));
    }

    /** @param array<string, mixed> $attributes */
    private function incoming(array $attributes = []): IncomingEInvoice {
        return IncomingEInvoice::query()->create($attributes + [
            'organization_id' => $this->org->id,
            'document_id' => Document::factory()->create(['organization_id' => $this->org->id])->id,
            'sha256' => hash('sha256', uniqid('reverse-charge', true)),
            'source' => 'upload',
            'received_at' => now(),
            'status' => IncomingEInvoiceStatus::Approved,
            'invoice_number' => 'ER-' . fake()->unique()->numberBetween(1000, 9999),
            'seller_name' => 'Beispiel Lieferant',
            'issue_date' => '2026-02-12',
            'currency' => 'EUR',
            'amount_net' => '1000.00',
            'amount_tax' => '0.00',
            'amount_gross' => '1000.00',
        ]);
    }

    /** E-Rechnung eines Lieferanten aus dem übrigen Gemeinschaftsgebiet mit Steueraufschlüsselung (BG-23). */
    private function euInvoice(string $category, string $net = '1000.00'): IncomingEInvoice {
        return $this->incoming([
            'seller_vat_id' => 'ATU12345678',
            'amount_net' => $net,
            'amount_gross' => $net,
            'summary' => [
                'seller_country' => 'AT',
                'tax_breakdown' => [['category' => $category, 'percent' => 0.0, 'net' => $net, 'tax' => '0.00']],
            ],
        ]);
    }

    private function proposal(IncomingEInvoice|Invoice $source): PostingProposal {
        $kind = $source instanceof Invoice ? PostingSourceKind::SalesInvoice : PostingSourceKind::IncomingInvoice;

        return app(PostingSourceRegistry::class)->for($kind)->proposalFor($this->org, $source);
    }

    private function book(IncomingEInvoice|Invoice $source): AccountingEntry {
        $proposal = $this->proposal($source);
        $this->assertSame([], $proposal->blockers);

        return app(JournalService::class)->post(app(PostingInboxService::class)->prepare($this->org, $proposal, $this->admin), $this->admin)->load(['lines.account', 'lines.taxCode']);
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: ?string, 4: ?string}> */
    private function lines(AccountingEntry $entry): array {
        return $entry->lines->load(['account', 'taxCode'])->map(fn ($line): array => [
            $line->account->number,
            $line->debit->getAmount(),
            $line->credit->getAmount(),
            $line->taxCode?->code,
            $line->tax_amount?->getAmount(),
        ])->values()->all();
    }

    /** @return array<string, array{base: string, tax: string}> */
    private function fields(): array {
        $breakdown = app(VatFieldBreakdownService::class)->forRange($this->org, $this->startsOn, $this->startsOn->endOfYear());
        $this->assertSame([], $breakdown['unclear']);

        return collect($breakdown['fields'])->mapWithKeys(fn (array $field): array => [(string) $field['field'] => ['base' => $field['base'], 'tax' => $field['tax']]])->all();
    }

    /** @return array{0: string, 1: string, 2: string} Umsatzsteuer, Vorsteuer, Zahllast der kontenbasierten Vorschau */
    private function preview(): array {
        $preview = app(VatPreviewBuilder::class)->build($this->org, $this->startsOn, $this->startsOn->endOfYear());

        return [$preview['output'], $preview['input'], $preview['payable']];
    }

    private function account(string $number): AccountingAccount {
        return AccountingAccount::query()->where('organization_id', $this->org->id)->where('number', $number)->sole();
    }

    /** MVP-1621: sonstige Leistung aus dem EU-Ausland (`AE`) — Umsatzsteuer und Vorsteuer nach § 13b Abs. 1, Kz 46/47 und 67. */
    public function test_an_eu_service_with_category_ae_books_output_and_input_tax(): void {
        $entry = $this->book($this->euInvoice('AE'));

        $this->assertSame([
            ['4900', '1000.00', '0.00', 'RC13BEU', '190.00'],
            ['1577', '190.00', '0.00', null, null],
            ['1787', '0.00', '190.00', null, null],
            ['1600', '0.00', '1000.00', null, null],
        ], $this->lines($entry));
        $this->assertSame(['46' => ['base' => '1000.00', 'tax' => '0.00'], '47' => ['base' => '0.00', 'tax' => '190.00'], '67' => ['base' => '0.00', 'tax' => '190.00']], $this->fields());
        // Gegenprobe über die Konten: Umsatz- und Vorsteuer heben sich auf.
        $this->assertSame(['190.00', '190.00', '0.00'], $this->preview());
        // Der offene Posten trägt nur das Netto.
        $this->assertSame('1000.00', AccountingOpenItem::query()->where('accounting_entry_id', $entry->id)->sole()->original_amount?->getAmount());
    }

    /** MVP-1621: innergemeinschaftlicher Erwerb (`K`) — Kz 89 mit Steuer, Vorsteuer Kz 61. */
    public function test_an_intra_community_acquisition_books_output_and_input_tax(): void {
        $entry = $this->book($this->euInvoice('K', '500.00'));

        $this->assertSame([
            ['4900', '500.00', '0.00', 'IGE19', '95.00'],
            ['1574', '95.00', '0.00', null, null],
            ['1774', '0.00', '95.00', null, null],
            ['1600', '0.00', '500.00', null, null],
        ], $this->lines($entry));
        $this->assertSame(['61' => ['base' => '0.00', 'tax' => '95.00'], '89' => ['base' => '500.00', 'tax' => '95.00']], $this->fields());
        $this->assertSame(['95.00', '95.00', '0.00'], $this->preview());
    }

    /** MVP-1621: ohne E-Rechnung gibt der Lieferant den Steuerfall vor — Bauleistung nach § 13b Abs. 2, Kz 84/85. */
    public function test_a_supplier_preset_reverse_charge_reaches_fields_84_and_85(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->org->id, 'incoming_tax_case' => IncomingInvoiceTaxCase::ReverseCharge]);
        $this->book($this->incoming(['supplier_id' => $supplier->id]));

        $this->assertSame(['67', '84', '85'], array_map(strval(...), array_keys($this->fields())));
        $this->assertSame(['1000.00', '190.00', '190.00'], [$this->fields()['84']['base'], $this->fields()['85']['tax'], $this->fields()['67']['tax']]);
    }

    /** Weist die Rechnung Steuer aus, gilt die Vorgabe des Lieferanten nicht — sie ist dann eine gewöhnliche Rechnung. */
    public function test_a_supplier_preset_does_not_override_a_taxed_invoice(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->org->id, 'incoming_tax_case' => IncomingInvoiceTaxCase::ReverseCharge]);
        $entry = $this->book($this->incoming(['supplier_id' => $supplier->id, 'amount_tax' => '190.00', 'amount_gross' => '1190.00']));

        $this->assertSame(['4900', '1000.00', '0.00', 'VSt19', '190.00'], $this->lines($entry)[0]);
    }

    /** Von Hand gesetzter Steuerfall mit Satz aus dem Werte-Dialog: innergemeinschaftlicher Erwerb zu 7 %, Kz 93. */
    public function test_a_manual_tax_case_with_seven_percent_uses_field_93(): void {
        $entry = $this->book($this->incoming([
            'amount_net' => '200.00', 'amount_gross' => '200.00',
            'tax_case' => IncomingInvoiceTaxCase::IntraCommunityAcquisition,
            'summary' => ['manual' => ['tax_breakdown' => [['percent' => '7.00', 'net' => '200.00', 'tax' => '0.00']]]],
        ]));

        $this->assertSame(['4900', '200.00', '0.00', 'IGE7', '14.00'], $this->lines($entry)[0]);
        $this->assertSame(['61' => ['base' => '0.00', 'tax' => '14.00'], '93' => ['base' => '200.00', 'tax' => '14.00']], $this->fields());
    }

    /** Das Storno hebt Grundlage und beide Steuern auf. */
    public function test_a_reversal_cancels_both_taxes(): void {
        $entry = $this->book($this->euInvoice('AE'));

        app(JournalService::class)->reverse($entry, 'Falscher Steuerfall', $this->admin);

        foreach ($this->fields() as $field) {
            $this->assertSame(['0.00', '0.00'], [$field['base'], $field['tax']]);
        }
        $this->assertSame(['0.00', '0.00', '0.00'], $this->preview());
    }

    /**
     * Skonto auf § 13b-Posten: Das Skonto ist netto und mindert die Grundlage; Umsatzsteuer und
     * Vorsteuer sinken um denselben Betrag. Das Skontokonto 3736 ist DATEV-Automatikkonto, deshalb
     * mindert das Skonto das Aufwandskonto des Belegs.
     */
    public function test_a_discount_on_a_reverse_charge_payable_corrects_both_taxes(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->org->id, 'incoming_tax_case' => IncomingInvoiceTaxCase::ReverseCharge]);
        $entry = $this->book($this->incoming(['supplier_id' => $supplier->id]));
        $item = AccountingOpenItem::query()->where('accounting_entry_id', $entry->id)->sole();

        app(OpenItemService::class)->settle($item, SettlementKind::Discount, '20.00');

        $discount = $item->refresh()->settlements->firstWhere('kind', SettlementKind::Discount)->entry;
        $this->assertSame([
            ['1600', '20.00', '0.00', null, null],
            ['4900', '0.00', '20.00', 'RC13B', '-3.80'],
            ['1577', '0.00', '3.80', null, null],
            ['1787', '3.80', '0.00', null, null],
        ], $this->lines($discount));
        $this->assertSame(['980.00', '186.20', '186.20'], [$this->fields()['84']['base'], $this->fields()['85']['tax'], $this->fields()['67']['tax']]);
        $this->assertSame(['186.20', '186.20', '0.00'], $this->preview());
    }

    /** Ist-Versteuerung: Die Steuer nach § 13b entsteht mit der Rechnung, nicht mit der Zahlung (§ 13b Abs. 1 und 2 UStG). */
    public function test_cash_basis_still_reports_reverse_charge_tax_in_the_invoice_month(): void {
        app(\App\Services\Accounting\TaxationMethodResolver::class)->switchTo($this->org, \App\Enums\Finance\TaxationMethod::Credit, $this->startsOn, $this->admin, 'Genehmigung des Finanzamts');
        $this->book($this->euInvoice('AE'));

        $preview = app(VatPreviewBuilder::class)->build($this->org, CarbonImmutable::create(2026, 2, 1), CarbonImmutable::create(2026, 2, 28));

        $this->assertSame(\App\Enums\Finance\TaxationMethod::Credit, $preview['method']);
        $this->assertSame(['190.00', '190.00', '0.00'], [$preview['output'], $preview['input'], $preview['payable']]);
    }

    public function test_tax_on_a_reverse_charge_invoice_blocks_the_proposal(): void {
        $proposal = $this->proposal($this->incoming(['tax_case' => IncomingInvoiceTaxCase::ReverseCharge, 'amount_tax' => '190.00', 'amount_gross' => '1190.00']));

        $this->assertContains((string) __('accounting.inbox.blocker.self_assessed_with_tax', ['case' => IncomingInvoiceTaxCase::ReverseCharge->label()]), $proposal->blockers);
    }

    /** Kleinunternehmer schulden die Steuer nach § 13b ohne Vorsteuerabzug — kein Vorschlag, der Vorsteuer bucht. */
    public function test_a_small_business_recipient_is_blocked(): void {
        $settings = (array) $this->org->settings;
        $settings['einvoice']['small_business'] = '1';
        $this->org->forceFill(['settings' => $settings])->save();

        $proposal = $this->proposal($this->euInvoice('AE'));

        $this->assertContains((string) __('accounting.inbox.blocker.self_assessed_small_business', ['case' => IncomingInvoiceTaxCase::ReverseChargeEu->label()]), $proposal->blockers);
    }

    public function test_without_a_self_assessed_tax_code_the_invoice_is_blocked(): void {
        AccountingTaxCode::query()->where('organization_id', $this->org->id)->where('code', 'RC13BEU')->update(['is_active' => false]);

        $proposal = $this->proposal($this->euInvoice('AE'));

        $this->assertContains((string) __('accounting.inbox.blocker.missing_self_assessed_code', ['case' => IncomingInvoiceTaxCase::ReverseChargeEu->label(), 'rate' => '19.00']), $proposal->blockers);
    }

    /** Festbuchung aus der Zeit davor: Beleg mit `AE`, Buchung ohne Steuer — der Qualitätsbericht nennt sie, gebucht wird nichts. */
    public function test_posted_invoices_without_self_assessed_tax_are_reported(): void {
        $invoice = $this->euInvoice('AE');
        $invoice->forceFill(['tax_case' => IncomingInvoiceTaxCase::Regular])->save();
        $this->book($invoice);
        $invoice->forceFill(['tax_case' => null])->save();

        $quality = app(DataQualityBuilder::class)->build($this->org, $this->startsOn, $this->startsOn->endOfYear());

        $this->assertSame(1, $quality['self_assessed_without_tax']);
        $this->assertStringContainsString((string) $invoice->invoice_number, implode(' ', $quality['findings']));
    }

    /** MVP-1622: mehrere Sätze — Aufwand und Vorsteuer je Satz, Kz 66 als Summe, Skonto je Satz. */
    public function test_an_incoming_invoice_with_two_rates_books_input_tax_per_rate(): void {
        $entry = $this->book($this->incoming([
            'amount_net' => '150.00', 'amount_tax' => '22.50', 'amount_gross' => '172.50',
            'summary' => ['tax_breakdown' => [
                ['category' => 'S', 'percent' => 19.0, 'net' => '100.00', 'tax' => '19.00'],
                ['category' => 'S', 'percent' => 7.0, 'net' => '50.00', 'tax' => '3.50'],
            ]],
        ]));

        $this->assertSame([
            ['4900', '100.00', '0.00', 'VSt19', '19.00'],
            ['1576', '19.00', '0.00', null, null],
            ['4900', '50.00', '0.00', 'VSt7', '3.50'],
            ['1571', '3.50', '0.00', null, null],
            ['1600', '0.00', '172.50', null, null],
        ], $this->lines($entry));
        $this->assertSame('22.50', $this->fields()['66']['tax']);

        $item = AccountingOpenItem::query()->where('accounting_entry_id', $entry->id)->sole();
        app(OpenItemService::class)->settle($item, SettlementKind::Discount, '17.25');
        $discount = $item->refresh()->settlements->firstWhere('kind', SettlementKind::Discount)->entry;
        $this->assertSame([
            ['1600', '17.25', '0.00', null, null],
            ['3736', '0.00', '10.00', 'VSt19', '-1.90'],
            ['1576', '0.00', '1.90', null, null],
            ['3731', '0.00', '5.00', 'VSt7', '-0.35'],
            ['1571', '0.00', '0.35', null, null],
        ], $this->lines($discount));
        $this->assertSame('20.25', $this->fields()['66']['tax']);
    }

    /** Ohne Regel für 7 % greift das Vorsteuerkennzeichen des Satzes, nicht das der allgemeinen Regel. */
    public function test_a_rate_without_its_own_rule_uses_the_input_tax_code_of_that_rate(): void {
        AccountingPostingRule::query()->where('organization_id', $this->org->id)->where('source_kind', 'incoming_invoice')->where('role', 'tax_input')->get()
            ->filter(fn (AccountingPostingRule $rule): bool => $rule->match_criteria !== null)->each->delete();

        $entry = $this->book($this->incoming([
            'amount_net' => '50.00', 'amount_tax' => '3.50', 'amount_gross' => '53.50',
            'summary' => ['tax_breakdown' => [['category' => 'S', 'percent' => 7.0, 'net' => '50.00', 'tax' => '3.50']]],
        ]));

        $this->assertSame([['4900', '50.00', '0.00', 'VSt7', '3.50'], ['1571', '3.50', '0.00', null, null]], array_slice($this->lines($entry), 0, 2));
    }

    /** MVP-1622: Kunde im Inland schuldet die Steuer (§ 13b Abs. 5) — 0 %, Pflichthinweis, Erlöskonto 8337 und Kz 60. */
    public function test_a_domestic_reverse_charge_sale_reaches_field_60(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id, 'country' => 'DE', 'is_reverse_charge_recipient' => true]);

        $resolution = app(TaxResolver::class)->resolve($this->org, $customer);
        $this->assertSame(['0.00', true, 'AE'], [$resolution['rate'], $resolution['reverse_charge'], $resolution['category']]);
        $this->assertSame(config('taxation.notes.reverse_charge_domestic'), $resolution['note']);

        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => $customer->id,
            'number' => 'RE-13B',
            'status' => InvoiceStatus::Issued,
            'type' => Invoice::TYPE_INVOICE,
            'category' => Invoice::CATEGORY_SERVICE,
            'issued_on' => '2026-02-20',
            'currency' => 'EUR',
            'subtotal' => '2000.00',
            'tax_amount' => '0.00',
            'total' => '2000.00',
            'is_reverse_charge' => true,
            'tax_breakdown' => [['rate' => '0.00', 'net' => '2000.00', 'tax' => '0.00']],
            'tax_context' => ['category' => 'AE'],
        ])->refresh();

        $entry = $this->book($invoice);

        $this->assertSame(['8337', '0.00', '2000.00', 'RC60', null], $this->lines($entry)[1]);
        $this->assertSame(['60' => ['base' => '2000.00', 'tax' => '0.00']], $this->fields());
        $this->assertSame(['0.00', '0.00', '0.00'], $this->preview());
    }

    /** MVP-1623: Sonstige Leistung ins EU-Ausland (Kz 21), Ausfuhr (Kz 43) und nicht steuerbarer Umsatz (Kz 45) buchen auf ihr eigenes Automatikkonto, DATEV ohne Schlüssel. */
    public function test_zero_rated_sales_reach_their_own_revenue_accounts(): void {
        $journal = app(LedgerDatevExportService::class);
        $cases = [
            'RC21' => [['country' => 'AT', 'vat_id' => 'ATU12345678'], Invoice::CATEGORY_SERVICE, 'AE', '8336'],
            'AUSF' => [['country' => 'CH'], Invoice::CATEGORY_MATERIAL, 'G', '8120'],
            'NSTB' => [['country' => 'CH'], Invoice::CATEGORY_SERVICE, 'G', '8338'],
        ];
        foreach ($cases as $code => [$customer, $category, $frozen, $account]) {
            $entry = $this->book(Invoice::query()->create([
                'organization_id' => $this->org->id,
                'customer_id' => Customer::factory()->create(['organization_id' => $this->org->id] + $customer)->id,
                'number' => 'RE-' . $code,
                'status' => InvoiceStatus::Issued,
                'type' => Invoice::TYPE_INVOICE,
                'category' => $category,
                'issued_on' => '2026-02-20',
                'currency' => 'EUR',
                'subtotal' => '500.00',
                'tax_amount' => '0.00',
                'total' => '500.00',
                'is_reverse_charge' => $frozen === 'AE',
                'tax_breakdown' => [['rate' => '0.00', 'net' => '500.00', 'tax' => '0.00']],
                'tax_context' => ['category' => $frozen],
            ])->refresh());

            $this->assertSame([$account, '0.00', '500.00', $code, null], $this->lines($entry)[1], $code);
            $batch = $journal->batchRows($entry->load(['lines.account.defaultTaxCode', 'lines.taxCode']));
            $this->assertSame([$account, null], [$batch[0]['account'], $batch[0]['tax_key']], $code);
        }
    }

    /**
     * MVP-1623: Auslagen buchen die Vorsteuer zum Satz des Belegs. Ein Satz ohne
     * Vorsteuerkennzeichen ist meist ausländische Umsatzsteuer — keine Vorsteuer;
     * der Vorschlag hält an, statt sie als deutsche Vorsteuer zu buchen.
     */
    public function test_expense_input_tax_follows_the_receipt_rate(): void {
        $proposal = fn (string $rate, string $net, string $tax): PostingProposal => app(PostingSourceRegistry::class)->for(PostingSourceKind::Expense)->proposalFor($this->org, \App\Models\Travel\Expense::query()->create([
            'organization_id' => $this->org->id,
            'user_id' => $this->admin->id,
            'date' => '2026-02-12',
            'description' => 'Beleg ' . $rate,
            'currency' => 'EUR',
            'amount_net' => $net,
            'tax_rate' => $rate,
            'tax_amount' => $tax,
            'amount_gross' => \CommonToolkit\Helper\Data\NumberHelper::addPrecise($net, $tax, 2),
            'status' => \App\Enums\Expense\ExpenseStatus::Approved->value,
        ]));
        $taxLine = static fn (PostingProposal $p): array => collect($p->lines)
            ->filter(fn ($line): bool => $line->role === \App\Enums\Finance\PostingAccountRole::TaxInput)
            ->map(fn ($line): array => [$line->account->number, $line->debit])->values()->all();

        $seven = $proposal('7.00', '100.00', '7.00');
        $this->assertSame([], $seven->blockers);
        $this->assertSame([['1571', '7.00']], $taxLine($seven));

        $this->assertSame([['1576', '19.00']], $taxLine($proposal('19.00', '100.00', '19.00')));

        $foreign = $proposal('10.00', '100.00', '10.00');
        $this->assertContains((string) __('accounting.inbox.blocker.expense_rate_without_input_tax', ['rate' => '10,00']), $foreign->blockers);
    }

    /** MVP-1622: Der DATEV-Stapel bucht je Satz gegen das Personenkonto; Steuerzeilen gehen im BU-Schlüssel bzw. in der Automatik auf. */
    public function test_the_datev_batch_carries_tax_keys_without_separate_tax_rows(): void {
        $journal = app(LedgerDatevExportService::class);
        $rc = $this->book($this->euInvoice('AE'));
        $twoRates = $this->book($this->incoming([
            'amount_net' => '150.00', 'amount_tax' => '22.50', 'amount_gross' => '172.50',
            'summary' => ['tax_breakdown' => [
                ['category' => 'S', 'percent' => 19.0, 'net' => '100.00', 'tax' => '19.00'],
                ['category' => 'S', 'percent' => 7.0, 'net' => '50.00', 'tax' => '3.50'],
            ]],
        ]));
        $sale = $this->book(Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => Customer::factory()->create(['organization_id' => $this->org->id])->id,
            'number' => 'RE-DATEV',
            'status' => InvoiceStatus::Issued,
            'issued_on' => '2026-02-10',
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '19.00',
            'total' => '119.00',
            'tax_breakdown' => [['rate' => '19.00', 'net' => '100.00', 'tax' => '19.00']],
        ])->refresh());

        $rows = static fn (AccountingEntry $entry): array => array_map(
            static fn (array $row): array => [$row['account'], $row['side'], $row['amount'], $row['contra'], $row['tax_key']],
            $journal->batchRows($entry->load(['lines.account.defaultTaxCode', 'lines.taxCode'])),
        );

        // § 13b: netto mit BU 94, DATEV bucht Vorsteuer und Umsatzsteuer selbst.
        $this->assertSame([['4900', 'S', '1000.00', '1600', '94']], $rows($rc));
        // Aufwandskonto ohne Automatik: brutto mit dem Schlüssel des Satzes.
        $this->assertSame([['4900', 'S', '119.00', '1600', '9'], ['4900', 'S', '53.50', '1600', '8']], $rows($twoRates));
        // 8400 ist Automatikkonto: brutto ohne Schlüssel.
        $this->assertSame([['8400', 'H', '119.00', '1400', null]], $rows($sale));

        // Skonto auf § 13b-Posten: Gutschrift aufs Aufwandskonto mit BU 94.
        $item = AccountingOpenItem::query()->where('accounting_entry_id', $rc->id)->sole();
        app(OpenItemService::class)->settle($item, SettlementKind::Discount, '20.00');
        $this->assertSame([['4900', 'H', '20.00', '1600', '94']], $rows($item->refresh()->settlements->firstWhere('kind', SettlementKind::Discount)->entry));

        // Skonto an einer Forderung: 8736 ist Automatikkonto, der Betrag geht brutto ohne Schlüssel.
        $saleItem = AccountingOpenItem::query()->where('accounting_entry_id', $sale->id)->sole();
        app(OpenItemService::class)->settle($saleItem, SettlementKind::Discount, '2.38');
        $this->assertSame([['8736', 'S', '2.38', '1400', null]], $rows($saleItem->refresh()->settlements->firstWhere('kind', SettlementKind::Discount)->entry));
    }

    /** Altbuchung mit Kennzeichen an der Steuerzeile: Auf dem Automatikkonto hebt BU 40 die Automatik auf, die Steuer bleibt ein eigener Satz. */
    public function test_an_old_form_entry_suspends_the_automatic_account(): void {
        $code = AccountingTaxCode::query()->where('organization_id', $this->org->id)->where('code', 'USt19')->sole();
        $entry = app(JournalService::class)->postDirect($this->org, [
            'booked_on' => CarbonImmutable::create(2026, 2, 1),
            'memo' => 'Alte Form',
            'lines' => [
                ['accounting_account_id' => $this->account('1400')->id, 'debit' => '119.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->account('8400')->id, 'debit' => '0.00', 'credit' => '100.00'],
                ['accounting_account_id' => $this->account('1776')->id, 'debit' => '0.00', 'credit' => '19.00', 'accounting_tax_code_id' => $code->id, 'tax_amount' => '19.00'],
            ],
        ], $this->admin);

        $rows = app(LedgerDatevExportService::class)->batchRows($entry->load(['lines.account.defaultTaxCode', 'lines.taxCode']));

        $this->assertSame([['8400', 'H', '100.00', '1400', '40'], ['1776', 'H', '19.00', '1400', null]], array_map(
            static fn (array $row): array => [$row['account'], $row['side'], $row['amount'], $row['contra'], $row['tax_key']],
            $rows,
        ));
    }

    /** Der Stapel bleibt importierbar: eine Zeile je Buchungssatz, Roundtrip mit dem financial-formats-Parser. */
    public function test_the_extf_batch_roundtrips(): void {
        if (! \App\Services\Billing\FinancialFormatsSupport::isAvailable()) {
            $this->markTestSkipped('php-financial-formats nicht installiert.');
        }
        $this->org->update(['settings' => array_merge((array) $this->org->settings, ['datev' => ['advisor_number' => 12345, 'client_number' => 67890]])]);
        $this->book($this->euInvoice('AE'));

        $result = app(LedgerDatevExportService::class)->buildExtf($this->org, $this->startsOn, $this->startsOn->endOfYear());

        $this->assertSame(1, $result['rows']);
        $this->assertSame(['1190.00', '1190.00'], [$result['debit'], $result['credit']]);
        $row = explode(';', explode("\n", $result['content'])[2]);
        $this->assertSame(['1000,00', 'S', '4900', '1600', '94'], [$row[0], $row[1], $row[6], $row[7], trim($row[8], '"')]);
        $roundtrip = app(\App\Services\Finance\Datev\DatevBookingAdapter::class)->validateRoundtrip($result['content'], \App\Services\Finance\Datev\DatevBookingConfig::forOrganization($this->org), 1);
        $this->assertTrue($roundtrip['ok'], json_encode($roundtrip) ?: '');
    }

    /** Steuerfall im Rechnungseingang: setzen, wieder auf automatisch, nach dem Buchen gesperrt. */
    public function test_the_tax_case_dialog_sets_the_case_until_the_invoice_is_booked(): void {
        $invoice = $this->euInvoice('AE');

        $this->get(route('finance.incoming-invoices.tax-case.form', $invoice))->assertOk()->assertSee(IncomingInvoiceTaxCase::ReverseChargeEu->label());
        $this->put(route('finance.incoming-invoices.tax-case', $invoice), ['tax_case' => 'reverse_charge'])->assertSessionHas('success');
        $this->assertSame(IncomingInvoiceTaxCase::ReverseCharge, $invoice->refresh()->tax_case);

        $this->book($invoice);
        $this->put(route('finance.incoming-invoices.tax-case', $invoice), ['tax_case' => ''])->assertSessionHas('error');
        $this->assertSame(IncomingInvoiceTaxCase::ReverseCharge, $invoice->refresh()->tax_case);
        $this->assertSame('RC13B', $this->postedEntry($invoice)->lines->first()->taxCode?->code);
    }

    private function postedEntry(IncomingEInvoice $invoice): AccountingEntry {
        return AccountingEntry::query()->where('organization_id', $this->org->id)->where('source_key', 'incoming:' . $invoice->id)->sole();
    }

    /** Pflege: Kennzeichen „Umsatz- und Vorsteuer“ mit Vorsteuerkonto, Steuerfall und BU-Schlüssel; Konto mit DATEV-Automatik. */
    public function test_tax_codes_and_accounts_carry_the_new_fields(): void {
        $this->get(route('finance.accounting.tax-codes.create'))->assertOk()->assertSee(__('accounting.tax_codes.self_assessed.legend'));
        $payload = [
            'name' => '§ 13b Abs. 2 UStG 7 %', 'direction' => 'both', 'rate' => '7',
            'tax_account' => $this->account('1787')->sqid, 'tax_case' => 'reverse_charge', 'valid_from' => '2026-01-01',
            'ustva_base_field' => '84', 'ustva_tax_field' => '85', 'ustva_input_tax_field' => '67', 'datev_tax_key' => '91',
        ];
        $this->post(route('finance.accounting.tax-codes.store'), ['code' => 'RC13B7'] + $payload)->assertSessionHasErrors('input_tax_account');
        $this->post(route('finance.accounting.tax-codes.store'), ['code' => 'RC13B7', 'input_tax_account' => $this->account('1577')->sqid] + $payload)->assertSessionHasNoErrors();

        $code = AccountingTaxCode::query()->where('organization_id', $this->org->id)->where('code', 'RC13B7')->with('inputTaxAccount')->sole();
        $this->assertSame(['1577', '67', '91', IncomingInvoiceTaxCase::ReverseCharge], [$code->inputTaxAccount?->number, $code->ustva_input_tax_field, $code->datev_tax_key, $code->tax_case]);
        $this->get(route('finance.accounting.tax-codes.edit', $code))->assertOk()->assertSee('1577');

        $vst19 = AccountingTaxCode::query()->where('organization_id', $this->org->id)->where('code', 'VSt19')->sole();
        $this->get(route('finance.accounting.accounts.edit', $this->account('4900')))->assertOk()->assertSee(__('accounting.ledger.field.datev_automatic'));
        $this->put(route('finance.accounting.accounts.update', $this->account('4900')), [
            'number' => '4900', 'name' => 'Sonstige betriebliche Aufwendungen', 'type' => 'expense', 'normal_balance' => 'debit', 'datev_automatic' => $vst19->sqid,
        ])->assertSessionHasNoErrors();
        $this->assertSame($vst19->id, $this->account('4900')->default_tax_code_id);
    }

    /** Lieferant gibt den Steuerfall vor, Kunde schuldet die Steuer — beides im Stammdatenformular. */
    public function test_supplier_and_customer_forms_store_the_tax_attributes(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->org->id, 'name' => 'Beispiel Bau GmbH', 'currency' => 'EUR']);
        $this->put(route('suppliers.update', $supplier), ['name' => 'Beispiel Bau GmbH', 'currency' => 'EUR', 'incoming_tax_case' => 'reverse_charge'])->assertSessionHasNoErrors();
        $this->assertSame(IncomingInvoiceTaxCase::ReverseCharge, $supplier->refresh()->incoming_tax_case);

        $customer = Customer::factory()->create(['organization_id' => $this->org->id, 'name' => 'Beispiel Generalunternehmer GmbH', 'currency' => 'EUR']);
        $this->put(route('customers.update', $customer), ['name' => 'Beispiel Generalunternehmer GmbH', 'currency' => 'EUR', 'is_reverse_charge_recipient' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue($customer->refresh()->is_reverse_charge_recipient);
        $this->put(route('customers.update', $customer), ['name' => 'Beispiel Generalunternehmer GmbH', 'currency' => 'EUR'])->assertSessionHasNoErrors();
        $this->assertFalse($customer->refresh()->is_reverse_charge_recipient);
    }

    /** Die geänderten Seiten und Dialoge zeigen die neuen Felder und Texte. */
    public function test_the_changed_pages_render(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->org->id, 'incoming_tax_case' => IncomingInvoiceTaxCase::ReverseCharge]);
        $invoice = $this->incoming(['supplier_id' => $supplier->id]);
        $invoice->document->forceFill(['document_type' => \App\Enums\Document\DocumentType::Invoice])->save();
        $entry = $this->book($invoice);

        $this->get(route('suppliers.edit', $supplier))->assertOk()->assertSee(__('invoicing.incoming_tax_case.supplier_field'));
        $this->get(route('customers.edit', Customer::factory()->create(['organization_id' => $this->org->id])))->assertOk()->assertSee(__('invoicing.reverse_charge_recipient.label'));
        $this->get(route('finance.accounting.accounts.index'))->assertOk()->assertSee('RC13B')->assertSee('1787 / 1577');
        $this->get(route('finance.incoming-invoices.show', $invoice->document))->assertOk()->assertSee(IncomingInvoiceTaxCase::ReverseCharge->label());
        $this->get(route('finance.accounting.open-items.settle-form', AccountingOpenItem::query()->where('accounting_entry_id', $entry->id)->sole()))
            ->assertOk()->assertSee(__('accounting.open_items.discount_self_assessed_hint', ['rates' => '19 %']));
        $this->get(route('reports.accounting.recapitulative'))->assertOk()->assertSee(__('accounting.recapitulative.column.kind'));
    }

    /** Nachzug für bestehende Kontenpläne aus der Vorlage: Konten, Kennzeichen, Schlüssel, Automatik, Regeln — idempotent. */
    public function test_the_backfill_adds_reverse_charge_codes_idempotently(): void {
        $codes = AccountingTaxCode::query()->where('organization_id', $this->org->id);
        (clone $codes)->whereIn('code', ['RC13BEU', 'RC13B', 'IGE19', 'IGE7', 'RC60'])->delete();
        (clone $codes)->update(['datev_tax_key' => null]);
        (clone $codes)->where('code', 'USt7')->update(['datev_tax_key' => '99']);
        AccountingAccount::query()->where('organization_id', $this->org->id)->update(['default_tax_code_id' => null]);
        AccountingPostingRule::query()->where('organization_id', $this->org->id)->get()
            ->filter(fn (AccountingPostingRule $rule): bool => in_array($rule->match_criteria, [['tax_rate' => '7.00'], ['tax_code' => 'IGL'], ['tax_code' => 'RC60']], true) && in_array($rule->source_kind->value, ['incoming_invoice', 'sales_invoice'], true))
            ->each->delete();
        AccountingAccount::query()->where('organization_id', $this->org->id)->whereIn('number', ['1577', '1787', '8337'])->delete();
        // Eigene Entscheidung: 8300 führt ein anderes DATEV-Konto und ist keine Standard-Automatik.
        $this->account('8300')->update(['datev_account' => '8310']);
        $foreign = Organization::factory()->create();

        $migration = require database_path('migrations/2027_04_17_250100_add_reverse_charge_template_tax_codes.php');
        $migration->up();
        $migration->up();

        $this->assertSame(1, (clone $codes)->where('code', 'RC13B')->count());
        $rc = (clone $codes)->where('code', 'RC13B')->with(['taxAccount', 'inputTaxAccount'])->sole();
        $this->assertSame(['1787', '1577', '84', '85', '67', '94', IncomingInvoiceTaxCase::ReverseCharge], [$rc->taxAccount?->number, $rc->inputTaxAccount?->number, $rc->ustva_base_field, $rc->ustva_tax_field, $rc->ustva_input_tax_field, $rc->datev_tax_key, $rc->tax_case]);
        $this->assertSame(['60', '46'], [(clone $codes)->where('code', 'RC60')->sole()->ustva_base_field, (clone $codes)->where('code', 'RC60')->sole()->datev_tax_key]);
        $this->assertSame(['3', '99', '9'], [(clone $codes)->where('code', 'USt19')->sole()->datev_tax_key, (clone $codes)->where('code', 'USt7')->sole()->datev_tax_key, (clone $codes)->where('code', 'VSt19')->sole()->datev_tax_key]);
        $this->assertSame('USt19', $this->account('8400')->defaultTaxCode?->code);
        $this->assertSame('RC60', $this->account('8337')->defaultTaxCode?->code);
        $this->assertNull($this->account('8300')->default_tax_code_id);
        $this->assertSame(1, AccountingPostingRule::query()->where('organization_id', $this->org->id)->where('role', 'revenue')->get()->filter(fn (AccountingPostingRule $rule): bool => $rule->match_criteria === ['tax_code' => 'RC60'])->count());
        $this->assertSame(0, AccountingTaxCode::query()->withoutGlobalScopes()->where('organization_id', $foreign->id)->count());
    }

    /** MVP-1623: Der Nachzug legt die Erlöskonten für RC21, AUSF und NSTB in Vorlagen-Kontenplänen an — einmal, ohne eigene Entscheidungen zu überschreiben. */
    public function test_the_backfill_adds_zero_rated_revenue_accounts_idempotently(): void {
        AccountingPostingRule::query()->where('organization_id', $this->org->id)->get()
            ->filter(fn (AccountingPostingRule $rule): bool => in_array($rule->match_criteria, [['tax_code' => 'RC21'], ['tax_code' => 'AUSF'], ['tax_code' => 'NSTB']], true))
            ->each->delete();
        AccountingAccount::query()->where('organization_id', $this->org->id)->whereIn('number', ['8336', '8338'])->delete();
        // Eigene Entscheidung: 8120 führt ein anderes DATEV-Konto und bleibt ohne Automatik.
        $this->account('8120')->update(['datev_account' => '8121', 'default_tax_code_id' => null]);

        $migration = require database_path('migrations/2027_04_17_320300_add_zero_rated_revenue_template_accounts.php');
        $migration->up();
        $migration->up();

        $this->assertSame('RC21', $this->account('8336')->defaultTaxCode?->code);
        $this->assertSame('NSTB', $this->account('8338')->defaultTaxCode?->code);
        $this->assertNull($this->account('8120')->default_tax_code_id);
        $rules = AccountingPostingRule::query()->where('organization_id', $this->org->id)->where('role', 'revenue')->with('account')->get();
        foreach (['RC21' => '8336', 'AUSF' => '8120', 'NSTB' => '8338'] as $code => $number) {
            $matching = $rules->filter(fn (AccountingPostingRule $rule): bool => $rule->match_criteria === ['tax_code' => $code]);
            $this->assertSame([$number], $matching->map(fn (AccountingPostingRule $rule): string => (string) $rule->account?->number)->values()->all(), $code);
        }
    }
}
