<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingDiscountTaxTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AllocationKind, OpenItemStatus, PostingSourceKind, ProfitDetermination, SettlementKind};
use App\Enums\Invoicing\{IncomingEInvoiceStatus, InvoiceStatus};
use App\Models\Accounting\{AccountingAccount, AccountingEntry, AccountingOpenItem, AccountingTaxCode};
use App\Models\Customer\Customer;
use App\Models\Document\Document;
use App\Models\Finance\{BankStatement, BankTransaction, PaymentAllocation};
use App\Models\Invoicing\{IncomingEInvoice, Invoice};
use App\Models\Platform\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsTemplateService, DiscountTaxSplit, FiscalYearService, JournalService, OpenItemService};
use App\Services\Accounting\Filing\VatFieldBreakdownService;
use App\Services\Accounting\Posting\{PostingInboxService, PostingSourceRegistry};
use App\Services\Accounting\Reports\{DataQualityBuilder, VatPreviewBuilder};
use App\Support\MorphMap;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Skonto mit Steuerberichtigung (§ 17 UStG) an Forderung und Verbindlichkeit:
 * ein Satz, gemischte Sätze, ohne Steuerbezug, Storno, Bankabgleich und der
 * Bestand ohne Berichtigung im Bericht „Buchungsqualität“.
 */
class AccountingDiscountTaxTest extends TestCase {
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
        // Der Ausgleich bucht „heute“ — im Testjahr, damit eine Periode besteht.
        $this->travelTo(CarbonImmutable::create(2026, 3, 2, 10));
    }

    private function account(string $number): AccountingAccount {
        return AccountingAccount::query()->where('organization_id', $this->org->id)->where('number', $number)->sole();
    }

    /**
     * @param  list<array{rate: string, net: string, tax: string}>  $breakdown
     * @return array{0: Invoice, 1: AccountingOpenItem}
     */
    private function receivable(array $breakdown): array {
        $net = (string) array_sum(array_column($breakdown, 'net'));
        $tax = (string) array_sum(array_column($breakdown, 'tax'));
        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => Customer::factory()->create(['organization_id' => $this->org->id])->id,
            'number' => 'RE-' . fake()->unique()->numberBetween(1000, 9999),
            'status' => InvoiceStatus::Issued,
            'type' => Invoice::TYPE_INVOICE,
            'category' => Invoice::CATEGORY_SERVICE,
            'issued_on' => '2026-02-10',
            'due_on' => '2026-02-24',
            'currency' => 'EUR',
            'subtotal' => number_format((float) $net, 2, '.', ''),
            'tax_amount' => number_format((float) $tax, 2, '.', ''),
            'total' => number_format((float) $net + (float) $tax, 2, '.', ''),
            'tax_breakdown' => $breakdown,
        ])->refresh();

        return [$invoice, $this->openItemOf(PostingSourceKind::SalesInvoice, $invoice)];
    }

    private function payable(): AccountingOpenItem {
        $invoice = IncomingEInvoice::query()->create([
            'organization_id' => $this->org->id,
            'document_id' => Document::factory()->create(['organization_id' => $this->org->id])->id,
            'sha256' => hash('sha256', uniqid('discount', true)),
            'source' => 'upload',
            'received_at' => now(),
            'status' => IncomingEInvoiceStatus::Approved,
            'invoice_number' => 'ER-400',
            'seller_name' => 'Lieferant Beispiel',
            'issue_date' => '2026-02-12',
            'currency' => 'EUR',
            'amount_net' => '1000.00',
            'amount_tax' => '190.00',
            'amount_gross' => '1190.00',
        ]);

        return $this->openItemOf(PostingSourceKind::IncomingInvoice, $invoice);
    }

    private function openItemOf(PostingSourceKind $kind, Invoice|IncomingEInvoice $source): AccountingOpenItem {
        $proposal = app(PostingSourceRegistry::class)->for($kind)->proposalFor($this->org, $source);
        $this->assertSame([], $proposal->blockers);
        $entry = app(JournalService::class)->post(app(PostingInboxService::class)->prepare($this->org, $proposal, $this->admin), $this->admin);

        return AccountingOpenItem::query()->where('accounting_entry_id', $entry->id)->sole();
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: ?string, 4: ?string}> Konto, Soll, Haben, Kennzeichen, Steuerbetrag */
    private function discountLines(AccountingOpenItem $item): array {
        $entry = $item->refresh()->settlements->firstWhere('kind', SettlementKind::Discount)?->entry;
        $this->assertInstanceOf(AccountingEntry::class, $entry);

        return $entry->lines->load(['account', 'taxCode'])->map(fn ($line): array => [
            $line->account->number,
            $line->debit->getAmount(),
            $line->credit->getAmount(),
            $line->taxCode?->code,
            $line->tax_amount?->getAmount(),
        ])->all();
    }

    /** @return array<string, array{base: string, tax: string}> */
    private function fields(): array {
        return collect(app(VatFieldBreakdownService::class)->forRange($this->org, $this->startsOn, $this->startsOn->endOfYear())['fields'])
            ->mapWithKeys(fn (array $field): array => [(string) $field['field'] => ['base' => $field['base'], 'tax' => $field['tax']]])
            ->all();
    }

    /** @return array{0: string, 1: string} Umsatzsteuer und Vorsteuer der kontenbasierten Vorschau */
    private function preview(): array {
        $preview = app(VatPreviewBuilder::class)->build($this->org, $this->startsOn, $this->startsOn->endOfYear());

        return [$preview['output'], $preview['input']];
    }

    public function test_a_discount_on_a_receivable_reduces_revenue_and_output_tax(): void {
        [, $item] = $this->receivable([['rate' => '19.00', 'net' => '100.00', 'tax' => '19.00']]);
        $this->assertSame('100.00', $this->fields()['81']['base']);
        $this->assertSame(['19.00', '0.00'], $this->preview());

        $this->get(route('finance.accounting.open-items.settle-form', $item))->assertOk()
            ->assertSee(__('accounting.open_items.discount_tax_hint', ['rates' => '19 %']));
        app(OpenItemService::class)->settle($item, SettlementKind::Discount, '2.38', null, 'Skonto 2 %');

        $this->assertSame([
            ['8736', '2.00', '0.00', 'USt19', '-0.38'],
            ['1776', '0.38', '0.00', null, null],
            ['1400', '0.00', '2.38', null, null],
        ], $this->discountLines($item));
        $this->assertSame('116.62', $item->refresh()->open_amount?->getAmount());
        // Kennziffer 81 und die kontenbasierte Vorschau zeigen dieselbe Minderung.
        $this->assertSame('98.00', $this->fields()['81']['base']);
        $this->assertSame(['18.62', '0.00'], $this->preview());
        $this->assertSame(0, app(DataQualityBuilder::class)->build($this->org, $this->startsOn, $this->startsOn->endOfYear())['discounts_without_tax_correction']);
    }

    /** Gemischte Sätze: anteilig nach den Bruttoanteilen des Belegs, je Satz das eigene Skonto- und Steuerkonto. */
    public function test_mixed_rates_split_the_discount_by_gross_share(): void {
        [, $item] = $this->receivable([
            ['rate' => '19.00', 'net' => '200.00', 'tax' => '38.00'],
            ['rate' => '7.00', 'net' => '100.00', 'tax' => '7.00'],
        ]);

        app(OpenItemService::class)->settle($item, SettlementKind::Discount, '10.35');

        $this->assertSame([
            ['8736', '6.00', '0.00', 'USt19', '-1.14'],
            ['1776', '1.14', '0.00', null, null],
            ['8731', '3.00', '0.00', 'USt7', '-0.21'],
            ['1771', '0.21', '0.00', null, null],
            ['1400', '0.00', '10.35', null, null],
        ], $this->discountLines($item));
        $this->assertSame(['194.00', '97.00'], [$this->fields()['81']['base'], $this->fields()['86']['base']]);
        $this->assertSame(['43.65', '0.00'], $this->preview());
    }

    /** Ein eigens eingestelltes Skontokonto gilt für alle Sätze — die Kennziffer hängt am Kennzeichen. */
    public function test_a_configured_discount_account_is_used_for_every_rate(): void {
        [, $item] = $this->receivable([['rate' => '7.00', 'net' => '100.00', 'tax' => '7.00']]);
        $settings = (array) $this->org->settings;
        $settings['datev']['discount_account'] = '8100';
        $this->org->forceFill(['settings' => $settings])->save();

        app(OpenItemService::class)->settle($item->refresh(), SettlementKind::Discount, '1.07');

        $this->assertSame(['8100', '1.00', '0.00', 'USt7', '-0.07'], $this->discountLines($item)[0]);
    }

    /** Ohne Steuerbezug (Buchung ohne Kennzeichen, etwa Startsaldo oder Kleinunternehmer) bleibt das Skonto ungeteilt. */
    public function test_an_item_without_tax_reference_keeps_the_full_discount(): void {
        $entry = app(JournalService::class)->postDirect($this->org, [
            'booked_on' => CarbonImmutable::create(2026, 2, 1),
            'memo' => 'Altforderung',
            'document_reference' => 'ALT-1',
            'lines' => [
                ['accounting_account_id' => $this->account('1400')->id, 'debit' => '119.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->account('9000')->id, 'debit' => '0.00', 'credit' => '119.00'],
            ],
        ], $this->admin);
        $item = AccountingOpenItem::query()->where('accounting_entry_id', $entry->id)->sole();

        $this->assertSame([], app(DiscountTaxSplit::class)->rates($item));
        $this->get(route('finance.accounting.open-items.settle-form', $item))->assertOk()
            ->assertSee(__('accounting.open_items.discount_no_tax_hint'));
        app(OpenItemService::class)->settle($item, SettlementKind::Discount, '2.38');

        $this->assertSame([
            ['8736', '2.38', '0.00', null, null],
            ['1400', '0.00', '2.38', null, null],
        ], $this->discountLines($item));
        $this->assertSame([], $this->fields());
    }

    public function test_a_discount_on_a_payable_reduces_expense_and_input_tax(): void {
        $item = $this->payable();
        $this->assertSame('190.00', $this->fields()['66']['tax']);

        app(OpenItemService::class)->settle($item, SettlementKind::Discount, '23.80');

        $this->assertSame([
            ['1600', '23.80', '0.00', null, null],
            ['3736', '0.00', '20.00', 'VSt19', '-3.80'],
            ['1576', '0.00', '3.80', null, null],
        ], $this->discountLines($item));
        $this->assertSame('186.20', $this->fields()['66']['tax']);
        $this->assertSame(['0.00', '186.20'], $this->preview());
    }

    /** Das Storno des Skonto-Ausgleichs hebt Netto, Steuer und den Ausgleich auf. */
    public function test_reversing_the_discount_entry_restores_fields_and_item(): void {
        [, $item] = $this->receivable([['rate' => '19.00', 'net' => '100.00', 'tax' => '19.00']]);
        app(OpenItemService::class)->settle($item, SettlementKind::Discount, '2.38');
        $entry = $item->refresh()->settlements->firstWhere('kind', SettlementKind::Discount)->entry;

        app(JournalService::class)->reverse($entry, 'Skonto zu Unrecht gezogen', $this->admin);

        $this->assertSame(['100.00', '0.00'], [$this->fields()['81']['base'], $this->fields()['81']['tax']]);
        $this->assertSame(['19.00', '0.00'], $this->preview());
        $this->assertSame(['119.00', OpenItemStatus::Open], [$item->refresh()->open_amount?->getAmount(), $item->status]);
    }

    /** Skonto aus dem Bankabgleich nutzt dieselbe Teilung — keine zweite Rechnung. */
    public function test_the_reconciliation_discount_uses_the_same_split(): void {
        [$invoice] = $this->receivable([['rate' => '19.00', 'net' => '100.00', 'tax' => '19.00']]);
        $transaction = BankTransaction::factory()->create([
            'organization_id' => $this->org->id,
            'bank_statement_id' => BankStatement::factory()->create(['organization_id' => $this->org->id])->id,
            'booking_date' => '2026-02-20',
            'amount' => '116.62',
            'currency' => 'EUR',
        ]);
        $allocation = PaymentAllocation::query()->create([
            'organization_id' => $this->org->id,
            'bank_transaction_id' => $transaction->id,
            'allocatable_type' => MorphMap::alias(Invoice::class),
            'allocatable_id' => $invoice->id,
            'amount' => '2.38',
            'kind' => AllocationKind::Skonto,
            'confirmed_by_user_id' => $this->admin->id,
            'confirmed_at' => now(),
        ]);

        $proposal = app(PostingSourceRegistry::class)->for(PostingSourceKind::Payment)->proposalFor($this->org, $allocation);

        $this->assertSame([], $proposal->blockers);
        $this->assertSame([
            ['8736', '2.00', '0.00', '-0.38'],
            ['1776', '0.38', '0.00', null],
            ['1400', '0.00', '2.38', null],
        ], array_map(static fn ($line): array => [$line->account->number, $line->debit, $line->credit, $line->taxAmount], $proposal->lines));
        $this->assertSame(AccountingTaxCode::query()->where('code', 'USt19')->sole()->id, $proposal->lines[0]->taxCodeId);
    }

    /** Bestand: Posten in alter Form (Kennzeichen an der Steuerzeile) und ein Skonto ohne Berichtigung. */
    public function test_legacy_discounts_without_correction_are_reported_not_rebooked(): void {
        $journal = app(JournalService::class);
        $code = AccountingTaxCode::query()->where('code', 'USt19')->sole();
        $invoiceEntry = $journal->postDirect($this->org, [
            'booked_on' => CarbonImmutable::create(2026, 2, 1),
            'memo' => 'Rechnung alte Form',
            'document_reference' => 'RE-ALT-7',
            'lines' => [
                ['accounting_account_id' => $this->account('1400')->id, 'debit' => '119.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->account('8400')->id, 'debit' => '0.00', 'credit' => '100.00'],
                ['accounting_account_id' => $this->account('1776')->id, 'debit' => '0.00', 'credit' => '19.00', 'accounting_tax_code_id' => $code->id, 'tax_amount' => '19.00'],
            ],
        ], $this->admin);
        $item = AccountingOpenItem::query()->where('accounting_entry_id', $invoiceEntry->id)->sole();
        // Die alte Form liest die Aufteilung weiter richtig: Grundlage aus Steuer und Satz.
        $this->assertSame('100.00', $this->fields()['81']['base']);

        // Skonto, wie es vor der Berichtigung gebucht wurde: voll aufs Skontokonto.
        $journal->postDirect($this->org, [
            'booked_on' => CarbonImmutable::create(2026, 2, 20),
            'memo' => 'Skonto zu RE-ALT-7',
            'snapshot' => ['settlement_kind' => SettlementKind::Discount->value, 'settles_open_item_id' => $item->id],
            'lines' => [
                ['accounting_account_id' => $this->account('8736')->id, 'debit' => '2.38', 'credit' => '0.00'],
                ['accounting_account_id' => $this->account('1400')->id, 'debit' => '0.00', 'credit' => '2.38'],
            ],
        ], $this->admin);
        $entries = AccountingEntry::query()->count();

        $quality = app(DataQualityBuilder::class)->build($this->org, $this->startsOn, $this->startsOn->endOfYear());
        $this->assertSame(1, $quality['discounts_without_tax_correction']);
        $finding = implode(' ', $quality['findings']);
        $this->assertStringContainsString('RE-ALT-7', $finding);
        $this->assertStringContainsString('0,38', $finding);
        $this->assertSame($entries, AccountingEntry::query()->count());

        // Ein neues Skonto an demselben Altposten wird berichtigt.
        app(OpenItemService::class)->settle($item->refresh(), SettlementKind::Discount, '1.19');
        $lines = $item->refresh()->settlements->last()->entry->lines->load('account')
            ->map(fn ($line): array => [$line->account->number, $line->debit->getAmount()])->all();
        $this->assertSame([['8736', '1.00'], ['1776', '0.19'], ['1400', '0.00']], $lines);
    }
}
