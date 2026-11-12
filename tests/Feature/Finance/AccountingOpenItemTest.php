<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingOpenItemTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AccountType, OpenItemDirection, OpenItemStatus, PostingAccountRole, PostingSourceKind, ProfitDetermination, SettlementKind};
use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Accounting\{AccountingAccount, AccountingOpenItem, AccountingOpenItemSettlement, AccountingPostingRule};
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsService, FiscalYearService, JournalService, OpenItemService};
use App\Services\Accounting\Posting\{PostingInboxService, PostingSourceRegistry};
use App\Support\MorphMap;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

/**
 * Offene Posten, Bankbuchung und Zahlungsfälle (Feature 125, MVP-674).
 *
 * Abnahme: Belegrest, OPOS und Zahlungsstatus stimmen nach Zahlung,
 * Teilzahlung und Rückläufer centgenau überein — und ein aufgehobenes
 * Matching erzeugt eine Gegenbewegung statt Datenverlust.
 */
class AccountingOpenItemTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private CarbonImmutable $startsOn;

    /** @var array<string, AccountingAccount> */
    private array $accounts = [];

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

        $chart = app(ChartOfAccountsService::class);
        $this->accounts['receivable'] = $chart->create($this->org, [
            'number' => '1400', 'name' => 'Forderungen aus L+L', 'type' => AccountType::Asset, 'is_open_item' => true,
        ]);
        $this->accounts['revenue'] = $chart->create($this->org, ['number' => '8400', 'name' => 'Erlöse 19 %', 'type' => AccountType::Income]);
        $this->accounts['tax'] = $chart->create($this->org, ['number' => '1776', 'name' => 'Umsatzsteuer 19 %', 'type' => AccountType::Liability]);
        $this->accounts['bank'] = $chart->create($this->org, ['number' => '1200', 'name' => 'Bank', 'type' => AccountType::Asset, 'is_bank' => true]);
        $this->accounts['discount'] = $chart->create($this->org, ['number' => '8736', 'name' => 'Gewährte Skonti', 'type' => AccountType::Expense]);

        foreach ([
            [PostingSourceKind::SalesInvoice, PostingAccountRole::Receivable, 'receivable', []],
            [PostingSourceKind::SalesInvoice, PostingAccountRole::Revenue, 'revenue', ['tax_rate' => '19.00']],
            [PostingSourceKind::SalesInvoice, PostingAccountRole::TaxOutput, 'tax', ['tax_rate' => '19.00']],
            [PostingSourceKind::Payment, PostingAccountRole::Bank, 'bank', []],
            [PostingSourceKind::Payment, PostingAccountRole::Receivable, 'receivable', []],
            [PostingSourceKind::Payment, PostingAccountRole::Discount, 'discount', []],
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
    }

    private function invoice(string $total = '119.00'): Invoice {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);
        $net = number_format((float) $total / 1.19, 2, '.', '');
        $tax = number_format((float) $total - (float) $net, 2, '.', '');

        return Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => $customer->id,
            'number' => 'RE-' . fake()->unique()->numberBetween(1000, 9999),
            'status' => InvoiceStatus::Issued,
            'issued_on' => $this->startsOn->addMonth()->toDateString(),
            'due_on' => $this->startsOn->addMonth()->addDays(14)->toDateString(),
            'currency' => 'EUR',
            'subtotal' => $net,
            'tax_amount' => $tax,
            'total' => $total,
            'tax_breakdown' => [['rate' => '19.00', 'net' => $net, 'tax' => $tax]],
        ])->refresh();
    }

    /** Bucht die Rechnung fest und liefert den entstandenen offenen Posten. */
    private function postInvoice(string $total = '119.00'): array {
        $invoice = $this->invoice($total);
        $proposal = app(PostingSourceRegistry::class)->for(PostingSourceKind::SalesInvoice)->proposalFor($this->org, $invoice);
        $entry = app(PostingInboxService::class)->prepare($this->org, $proposal, $this->admin);
        $entry = app(JournalService::class)->post($entry, $this->admin);

        return [$invoice, $entry, AccountingOpenItem::query()->where('accounting_entry_id', $entry->id)->sole()];
    }

    /** Erzeugt eine Zahlungsbuchung gegen den offenen Posten der Rechnung. */
    private function payInvoice(Invoice $invoice, string $amount, SettlementKind $kind = SettlementKind::Payment, bool $refund = false): void {
        $journal = app(JournalService::class);
        $bankOnDebit = ! $refund;

        $entry = $journal->postDirect($this->org, [
            'booked_on' => $this->startsOn->addMonths(2),
            'memo' => 'Zahlung ' . $invoice->number,
            'source_key' => 'payment-test:' . uniqid('', true),
            'snapshot' => [
                'settles_source_type' => MorphMap::alias(Invoice::class),
                'settles_source_id' => $invoice->id,
                'settlement_kind' => $kind->value,
            ],
            'lines' => [
                [
                    'accounting_account_id' => $this->accounts[$kind === SettlementKind::Discount ? 'discount' : 'bank']->id,
                    'debit' => $bankOnDebit ? $amount : '0.00',
                    'credit' => $bankOnDebit ? '0.00' : $amount,
                ],
                [
                    'accounting_account_id' => $this->accounts['receivable']->id,
                    'debit' => $bankOnDebit ? '0.00' : $amount,
                    'credit' => $bankOnDebit ? $amount : '0.00',
                ],
            ],
        ], $this->admin);
    }

    public function test_posting_an_invoice_creates_the_open_item(): void {
        [$invoice, $entry, $item] = $this->postInvoice();

        $this->assertSame(OpenItemDirection::Receivable, $item->direction);
        $this->assertSame(OpenItemStatus::Open, $item->status);
        $this->assertSame('119.00', $item->original_amount?->getAmount());
        $this->assertSame('119.00', $item->open_amount?->getAmount());
        $this->assertSame(MorphMap::alias(Invoice::class), $item->source_type);
        $this->assertSame($invoice->id, $item->source_id);
        $this->assertSame($entry->id, $item->accounting_entry_id);
    }

    /** Nur OPOS-Konten erzeugen Posten — Erlös und Steuer nicht. */
    public function test_only_open_item_accounts_produce_open_items(): void {
        $this->postInvoice();

        $this->assertSame(1, AccountingOpenItem::query()->count());
    }

    public function test_a_full_payment_settles_the_open_item(): void {
        [$invoice, , $item] = $this->postInvoice();

        $this->payInvoice($invoice, '119.00');

        $item->refresh();
        $this->assertSame(OpenItemStatus::Settled, $item->status);
        $this->assertSame('0.00', $item->open_amount?->getAmount());
        $this->assertNotNull($item->settled_at);
    }

    public function test_a_partial_payment_leaves_the_remainder_open(): void {
        [$invoice, , $item] = $this->postInvoice();

        $this->payInvoice($invoice, '50.00');

        $item->refresh();
        $this->assertSame(OpenItemStatus::PartiallySettled, $item->status);
        $this->assertSame('69.00', $item->open_amount?->getAmount());
    }

    /** Skonto mindert den Posten ohne Geldfluss — und bleibt unterscheidbar. */
    public function test_payment_plus_discount_settles_the_item_exactly(): void {
        [$invoice, , $item] = $this->postInvoice();

        $this->payInvoice($invoice, '116.62');
        $this->payInvoice($invoice, '2.38', SettlementKind::Discount);

        $item->refresh();
        $this->assertSame(OpenItemStatus::Settled, $item->status);
        $this->assertSame('0.00', $item->open_amount?->getAmount());

        $kinds = $item->settlements->pluck('kind')->map(fn ($kind): string => $kind->value)->all();
        $this->assertSame(['payment', 'discount'], $kinds);
    }

    /** Der Rückläufer öffnet den Posten wieder, statt die Zahlung zu löschen. */
    public function test_a_chargeback_reopens_the_open_item(): void {
        [$invoice, , $item] = $this->postInvoice();

        $this->payInvoice($invoice, '119.00');
        $this->assertSame(OpenItemStatus::Settled, $item->refresh()->status);

        $this->payInvoice($invoice, '119.00', SettlementKind::Reversal, refund: true);

        $item->refresh();
        $this->assertSame(OpenItemStatus::Open, $item->status);
        $this->assertSame('119.00', $item->open_amount?->getAmount());
        // Beide Bewegungen bleiben sichtbar: geflossen UND zurückgegangen.
        $this->assertCount(2, $item->settlements);
    }

    public function test_settlements_are_append_only(): void {
        [$invoice, , $item] = $this->postInvoice();
        $this->payInvoice($invoice, '119.00');

        $settlement = AccountingOpenItemSettlement::query()->firstOrFail();

        $this->expectException(RuntimeException::class);
        $settlement->update(['amount' => '1.00']);
    }

    /** Ein Storno der Rechnungsbuchung bucht den offenen Posten aus. */
    public function test_reversing_the_invoice_entry_clears_the_open_item(): void {
        [, $entry, $item] = $this->postInvoice();

        app(JournalService::class)->reverse($entry, 'Falsch erfasst', $this->admin);

        $item->refresh();
        $this->assertSame(OpenItemStatus::Settled, $item->status);
        $this->assertSame('0.00', $item->open_amount?->getAmount());
        $this->assertSame(SettlementKind::Reversal, $item->settlements->first()?->kind);
    }

    /** Storniert man die Zahlung, lebt der Posten wieder auf. */
    public function test_reversing_a_payment_entry_reopens_the_item(): void {
        [$invoice, , $item] = $this->postInvoice();
        $this->payInvoice($invoice, '119.00');
        $this->assertSame(OpenItemStatus::Settled, $item->refresh()->status);

        $paymentEntry = \App\Models\Accounting\AccountingEntry::query()
            ->where('memo', 'like', 'Zahlung%')
            ->latest('id')
            ->firstOrFail();

        app(JournalService::class)->reverse($paymentEntry, 'Zahlung falsch zugeordnet', $this->admin);

        $item->refresh();
        $this->assertSame(OpenItemStatus::Open, $item->status);
        $this->assertSame('119.00', $item->open_amount?->getAmount());
    }

    public function test_manual_settlement_records_kind_and_note(): void {
        [, , $item] = $this->postInvoice();

        app(OpenItemService::class)->settle($item, SettlementKind::Retention, '10.00', null, 'Sicherheitseinbehalt 5 %');

        $item->refresh();
        $this->assertSame('109.00', $item->open_amount?->getAmount());
        $this->assertSame(SettlementKind::Retention, $item->settlements->first()?->kind);
        $this->assertSame('Sicherheitseinbehalt 5 %', $item->settlements->first()?->note);
    }

    /**
     * Skonto und Ausbuchung erzeugen die Gegenbuchung im Journal
     * (Sicherheitsscan 2026-08-23, S-38) — und zwar genau eine, mit genau einem
     * Ausgleich: der Ausgleich entsteht beim Festbuchen, nicht zusätzlich.
     */
    public function test_discount_settlement_books_against_the_discount_account(): void {
        [, , $item] = $this->postInvoice();
        $this->actingAs($this->admin);

        app(OpenItemService::class)->settle($item, SettlementKind::Discount, '2.38', null, 'Skonto 2 %');

        $item->refresh();
        $this->assertSame('116.62', $item->open_amount?->getAmount());
        $this->assertCount(1, $item->settlements);
        $this->assertSame(SettlementKind::Discount, $item->settlements->first()?->kind);

        $entry = $item->settlements->first()?->entry;
        $this->assertNotNull($entry, 'Der Ausgleich muss eine Journalbuchung tragen.');
        $lines = $entry->lines->keyBy(fn ($line) => (string) $line->account?->number);
        $this->assertSame('2.38', $lines['8736']?->debit?->getAmount());
        $this->assertSame('2.38', $lines['1400']?->credit?->getAmount());
    }

    /** Ohne Gegenkonto im Kontenplan wird der Ausgleich abgewiesen, der Posten bleibt offen (MVP-1555). */
    public function test_settlement_without_counter_account_is_rejected(): void {
        [, , $item] = $this->postInvoice();
        $this->actingAs($this->admin);

        // 2400 (Ausbuchung, SKR03) ist im Kontenplan dieser Organisation nicht angelegt.
        try {
            app(OpenItemService::class)->settle($item, SettlementKind::WriteOff, '19.00');
            $this->fail('Der Ausgleich ohne Gegenkonto wurde angenommen.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('2400', $e->getMessage());
        }

        $item->refresh();
        $this->assertSame('119.00', $item->open_amount?->getAmount());
        $this->assertCount(0, $item->settlements);
        $this->assertSame(['write_off' => '2400'], app(OpenItemService::class)->missingCounterAccounts($this->org));
    }

    /** Mit dem Vorlagenkonto bucht die Ausbuchung Forderungsverlust an Forderung. */
    public function test_write_off_books_against_the_configured_account(): void {
        [, , $item] = $this->postInvoice();
        $this->actingAs($this->admin);
        $settings = (array) $this->org->settings;
        $settings['datev']['write_off_account'] = '2405';
        $this->org->forceFill(['settings' => $settings])->save();
        $writeOff = app(ChartOfAccountsService::class)->create($this->org, ['number' => '2405', 'name' => 'Forderungsverluste', 'type' => AccountType::Expense]);

        app(OpenItemService::class)->settle($item->refresh(), SettlementKind::WriteOff, '19.00');

        $settlement = $item->refresh()->settlements->first();
        $this->assertSame('100.00', $item->open_amount?->getAmount());
        $this->assertNotNull($settlement?->accounting_entry_id);
        $this->assertSame('19.00', $settlement?->entry?->lines->firstWhere('accounting_account_id', $writeOff->id)?->debit?->getAmount());
        $this->assertSame([], app(OpenItemService::class)->missingCounterAccounts($this->org));
    }

    /** Altbestand: Ausgleiche ohne Buchung stehen im Qualitätsbericht, zurückgenommene nicht. */
    public function test_unbooked_settlements_are_listed_read_only(): void {
        [, , $item] = $this->postInvoice();
        $legacy = AccountingOpenItemSettlement::query()->create([
            'organization_id' => $this->org->id,
            'accounting_open_item_id' => $item->id,
            'kind' => SettlementKind::WriteOff,
            'amount' => '19.00',
            'currency' => $item->currency,
            'booked_on' => '2026-03-01',
            'created_at' => now(),
        ]);

        $found = app(OpenItemService::class)->settlementsWithoutEntry($this->org);
        $this->assertSame([$legacy->id], $found->pluck('id')->all());

        $quality = app(\App\Services\Accounting\Reports\DataQualityBuilder::class)->build($this->org, CarbonImmutable::create(2026, 1, 1), CarbonImmutable::create(2026, 12, 31));
        $this->assertSame(1, $quality['unbooked_settlements']);
        $this->assertStringContainsString((string) $item->document_reference, implode(' ', $quality['findings']));

        AccountingOpenItemSettlement::query()->create([
            'organization_id' => $this->org->id,
            'accounting_open_item_id' => $item->id,
            'kind' => SettlementKind::Reversal,
            'amount' => '19.00',
            'currency' => $item->currency,
            'booked_on' => '2026-03-02',
            'reverses_settlement_id' => $legacy->id,
            'created_at' => now(),
        ]);
        $this->assertCount(0, app(OpenItemService::class)->settlementsWithoutEntry($this->org));
    }

    public function test_aging_groups_by_due_date(): void {
        $this->postInvoice();

        $aging = app(OpenItemService::class)->aging($this->org, OpenItemDirection::Receivable);

        $this->assertCount(1, $aging['items']);
        // Fälligkeit liegt in der Vergangenheit (Testdatum 2026) → überfälliges Band.
        $this->assertSame('119.00', $aging['buckets']['d90plus']);
    }

    public function test_open_item_pages_require_permissions(): void {
        [, , $item] = $this->postInvoice();
        $member = User::factory()->create(['organization_id' => $this->org->id]);

        $this->actingAs($member)->get(route('finance.accounting.open-items.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('finance.accounting.open-items.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('finance.accounting.open-items.settle-form', $item))->assertOk();
    }

    public function test_manual_settlement_over_http(): void {
        [, , $item] = $this->postInvoice();

        $this->actingAs($this->admin)->post(route('finance.accounting.open-items.settle', $item), [
            'kind' => SettlementKind::Retention->value,
            'amount' => '19.00',
            'note' => 'Sicherheitseinbehalt',
        ])->assertRedirect();

        $this->assertSame('100.00', $item->refresh()->open_amount?->getAmount());
    }

    /** Die Ausbuchung ohne Gegenkonto scheitert auch über HTTP mit Meldung am Feld. */
    public function test_write_off_without_counter_account_fails_over_http(): void {
        [, , $item] = $this->postInvoice();

        $this->actingAs($this->admin)->post(route('finance.accounting.open-items.settle', $item), [
            'kind' => SettlementKind::WriteOff->value,
            'amount' => '19.00',
        ])->assertSessionHasErrors('kind');

        $this->assertSame('119.00', $item->refresh()->open_amount?->getAmount());
    }

    /** MVP-1559: Strittig setzen sperrt die Mahnung, bleibt bei Teilausgleich stehen und lässt sich zurücknehmen. */
    public function test_an_open_item_can_be_marked_disputed_and_withdrawn(): void {
        [$invoice, , $item] = $this->postInvoice();
        $liquidity = fn (): string => app(\App\Services\Accounting\Reports\LiquidityBuilder::class)->build($this->org, CarbonImmutable::create(2026, 12, 31))['receivable'];
        $this->assertSame('119.00', $liquidity());

        $this->actingAs($this->admin)->get(route('finance.accounting.open-items.dispute-form', $item))->assertOk();
        $this->actingAs($this->admin)->post(route('finance.accounting.open-items.dispute', $item), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->admin)->post(route('finance.accounting.open-items.dispute', $item), ['reason' => 'Mangel gerügt'])
            ->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame(OpenItemStatus::Disputed, $item->status);
        $this->assertSame('Mangel gerügt', $item->dispute_note);
        $this->assertNotNull($item->disputed_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'accounting.open_item_disputed', 'auditable_id' => $item->id]);
        $this->assertTrue($invoice->refresh()->isDunningBlocked());
        $this->assertStringContainsString('Mangel gerügt', (string) $invoice->dunning_block_reason);
        // Strittig ist kein erwarteter Eingang.
        $this->assertSame('0.00', $liquidity());

        // Filter der Liste und Rücknahme-Knopf.
        $this->actingAs($this->admin)->get(route('finance.accounting.open-items.index', ['status' => 'disputed']))
            ->assertOk()
            ->assertSee((string) $item->document_reference)
            ->assertSee(route('finance.accounting.open-items.dispute.withdraw', $item), false);
        $this->actingAs($this->admin)->get(route('finance.accounting.open-items.index', ['status' => 'partially_settled']))
            ->assertOk()
            ->assertDontSee(route('finance.accounting.open-items.dispute.withdraw', $item), false);

        // Ein Teilausgleich lässt den Rest strittig; ein zweites Kennzeichnen wird abgewiesen.
        app(OpenItemService::class)->settle($item, SettlementKind::Retention, '19.00');
        $this->assertSame(OpenItemStatus::Disputed, $item->refresh()->status);
        $this->actingAs($this->admin)->post(route('finance.accounting.open-items.dispute', $item), ['reason' => 'Nochmal'])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->admin)->post(route('finance.accounting.open-items.dispute.withdraw', $item))->assertSessionHasNoErrors();
        $item->refresh();
        $this->assertSame(OpenItemStatus::PartiallySettled, $item->status);
        $this->assertNull($item->disputed_at);
        $this->assertNull($item->dispute_note);
        $this->assertFalse($invoice->refresh()->isDunningBlocked());
        $this->assertDatabaseHas('audit_logs', ['event' => 'accounting.open_item_dispute_withdrawn', 'auditable_id' => $item->id]);
        $this->assertSame('100.00', $liquidity());

        $this->actingAs($this->admin)->post(route('finance.accounting.open-items.dispute.withdraw', $item))->assertSessionHasErrors('status');
    }

    /** MVP-1559: Eine von Hand gesetzte Mahnsperre gehört nicht dem Kennzeichen — sie bleibt bei der Rücknahme. */
    public function test_withdrawing_a_dispute_keeps_a_manual_dunning_block(): void {
        [$invoice, , $item] = $this->postInvoice();
        app(\App\Services\Invoicing\DunningService::class)->block($invoice, 'Ratenzahlung vereinbart', $this->admin);

        $service = app(OpenItemService::class);
        $service->markDisputed($item, 'Leistung bestritten', $this->admin);
        $this->assertSame('Ratenzahlung vereinbart', $invoice->refresh()->dunning_block_reason);

        $service->withdrawDispute($item->refresh(), $this->admin);
        $this->assertTrue($invoice->refresh()->isDunningBlocked());
        $this->assertSame('Ratenzahlung vereinbart', $invoice->dunning_block_reason);
    }

    /** MVP-1559: Der Vollausgleich schließt auch einen strittigen Posten und räumt das Kennzeichen ab. */
    public function test_a_full_settlement_closes_a_disputed_item(): void {
        [$invoice, , $item] = $this->postInvoice();
        app(OpenItemService::class)->markDisputed($item, 'Betrag bestritten', $this->admin);

        $this->payInvoice($invoice, '119.00');

        $item->refresh();
        $this->assertSame(OpenItemStatus::Settled, $item->status);
        $this->assertNull($item->disputed_at);
    }
}
