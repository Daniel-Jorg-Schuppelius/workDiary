<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingDirectBookingFourEyesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AccountType, AccountingEntryStatus, AllocationKind, DirectBookingKind, OpenItemStatus, PostingAccountRole, PostingSourceKind, ProfitDetermination, SettlementKind, VatFilingInterval};
use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Accounting\{AccountingAccount, AccountingEntry, AccountingEvent, AccountingOpenItem, AccountingPostingRule, AccountingTransfer, AccountingVatExtension};
use App\Models\Customer\Customer;
use App\Models\Finance\{BankStatement, BankTransaction, PaymentAllocation};
use App\Models\Invoicing\Invoice;
use App\Models\Platform\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsService, FiscalYearService, InternalTransferService, JournalService, OpenItemService, OpeningBalanceImportService, VatFilingProfileResolver};
use App\Services\Accounting\Filing\{VatFilingPeriodService, VatReturnService, VatSpecialPrepaymentService};
use App\Services\Accounting\Posting\{PostingInboxService, PostingSourceRegistry};
use App\Settings\SettingScope;
use App\Support\{MorphMap, Setting};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Vier-Augen-Prinzip bei Direktbuchungen (Phase 137, E9).
 *
 * Skonto/Ausbuchung, Klärungsbuchung, interne Umbuchung, Startsalden und
 * Sondervorauszahlung legen ihre Buchung selbst an. Mit Vier-Augen entsteht
 * ein Entwurf in der Buchungs-Inbox; die fachliche Folge greift erst, wenn
 * eine zweite Person festschreibt. Ohne Vier-Augen bleibt alles sofort.
 * Dazu Storno von Hand als Entwurf (E23) und Verwerfen wartender Entwürfe (E24).
 */
class AccountingDirectBookingFourEyesTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private User $second;

    private CarbonImmutable $startsOn;

    /** @var array<string, AccountingAccount> */
    private array $accounts = [];

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->second = User::factory()->admin()->create(['organization_id' => $this->org->id]);

        $this->startsOn = CarbonImmutable::parse('2026-01-01');
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
        foreach ([
            'receivable' => ['number' => '1400', 'name' => 'Forderungen aus L+L', 'type' => AccountType::Asset, 'is_open_item' => true],
            'revenue' => ['number' => '8400', 'name' => 'Erlöse 19 %', 'type' => AccountType::Income],
            'tax' => ['number' => '1776', 'name' => 'Umsatzsteuer 19 %', 'type' => AccountType::Liability],
            'bank' => ['number' => '1200', 'name' => 'Bank', 'type' => AccountType::Asset, 'is_bank' => true],
            'cash' => ['number' => '1000', 'name' => 'Kasse', 'type' => AccountType::Asset, 'is_cash' => true],
            'transit' => ['number' => '1360', 'name' => 'Geldtransit', 'type' => AccountType::Asset, 'is_clearing' => true],
            'discount' => ['number' => '8736', 'name' => 'Gewährte Skonti', 'type' => AccountType::Expense],
            'write_off' => ['number' => '2400', 'name' => 'Forderungsverluste', 'type' => AccountType::Expense],
            'prepayment' => ['number' => '1781', 'name' => 'USt-Vorauszahlungen 1/11', 'type' => AccountType::Asset],
            'equity' => ['number' => '9000', 'name' => 'Saldenvorträge', 'type' => AccountType::Equity],
        ] as $key => $attributes) {
            $this->accounts[$key] = $chart->create($this->org, $attributes);
        }

        foreach ([
            [PostingSourceKind::SalesInvoice, PostingAccountRole::Receivable, 'receivable', null],
            [PostingSourceKind::SalesInvoice, PostingAccountRole::Revenue, 'revenue', ['tax_rate' => '19.00']],
            [PostingSourceKind::SalesInvoice, PostingAccountRole::TaxOutput, 'tax', ['tax_rate' => '19.00']],
            [PostingSourceKind::Payment, PostingAccountRole::Bank, 'bank', null],
        ] as [$kind, $role, $accountKey, $match]) {
            AccountingPostingRule::query()->create([
                'organization_id' => $this->org->id,
                'source_kind' => $kind,
                'role' => $role,
                'accounting_account_id' => $this->accounts[$accountKey]->id,
                'match_criteria' => $match,
                'priority' => 100,
                'version' => 1,
                'valid_from' => $this->startsOn->toDateString(),
                'is_active' => true,
            ]);
        }
    }

    private function enableFourEyes(): void {
        Setting::set(PostingInboxService::FOUR_EYES_KEY, true, SettingScope::Organization, $this->org);
    }

    /** Festgeschriebene Rechnung über 119,00 € und ihr offener Posten. */
    private function openItem(): AccountingOpenItem {
        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => Customer::factory()->create(['organization_id' => $this->org->id])->id,
            'number' => 'RE-' . fake()->unique()->numberBetween(1000, 9999),
            'status' => InvoiceStatus::Issued,
            'issued_on' => $this->startsOn->addMonth()->toDateString(),
            'due_on' => $this->startsOn->addMonth()->addDays(14)->toDateString(),
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '19.00',
            'total' => '119.00',
            'tax_breakdown' => [['rate' => '19.00', 'net' => '100.00', 'tax' => '19.00']],
        ])->refresh();

        $proposal = app(PostingSourceRegistry::class)->for(PostingSourceKind::SalesInvoice)->proposalFor($this->org, $invoice);
        // Die Rechnung selbst schreibt die zweite Person fest — sie ist hier nur Ausgangslage.
        $entry = $this->inbox()->prepare($this->org, $proposal, $this->second);
        $entry = app(JournalService::class)->post($entry, $this->admin);

        return AccountingOpenItem::query()->where('accounting_entry_id', $entry->id)->sole();
    }

    private function transaction(string $amount = '250.00'): BankTransaction {
        return BankTransaction::factory()->create([
            'organization_id' => $this->org->id,
            'bank_statement_id' => BankStatement::factory()->create(['organization_id' => $this->org->id])->id,
            'booking_date' => $this->startsOn->addDays(15)->toDateString(),
            'amount' => $amount,
            'currency' => 'EUR',
        ]);
    }

    private function inbox(): PostingInboxService {
        return app(PostingInboxService::class);
    }

    private function settlementEntry(AccountingOpenItem $item): AccountingEntry {
        return AccountingEntry::query()
            ->where('source_type', MorphMap::alias(AccountingOpenItem::class))
            ->where('source_id', $item->id)
            ->latest('id')
            ->firstOrFail();
    }

    /** @param  callable(): mixed  $action */
    private function assertFourEyesRefusal(callable $action): void {
        try {
            $action();
            $this->fail('Die vorbereitende Person durfte selbst festschreiben.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('four_eyes', $exception->errors());
        }
    }

    // ── Skonto und Ausbuchung ───────────────────────────────────────────

    public function test_without_four_eyes_a_discount_is_posted_and_settled_at_once(): void {
        $item = $this->openItem();
        $this->actingAs($this->admin);

        $settlement = app(OpenItemService::class)->settle($item, SettlementKind::Discount, '2.38');

        $this->assertNotNull($settlement);
        $this->assertSame('116.62', $item->refresh()->open_amount?->getAmount());
        $entry = $this->settlementEntry($item);
        $this->assertSame(AccountingEntryStatus::Posted, $entry->status);
        $this->assertSame(DirectBookingKind::OpenItemSettlement, DirectBookingKind::of($entry));
    }

    public function test_with_four_eyes_a_discount_waits_as_draft_and_the_item_stays_open(): void {
        $item = $this->openItem();
        $this->enableFourEyes();
        $this->actingAs($this->admin);

        $settlement = app(OpenItemService::class)->settle($item, SettlementKind::Discount, '2.38', null, 'Skonto 2 %');

        $this->assertNull($settlement);
        $item->refresh();
        $this->assertSame('119.00', $item->open_amount?->getAmount());
        $this->assertSame(OpenItemStatus::Open, $item->status);
        $this->assertCount(0, $item->settlements);

        $draft = $this->settlementEntry($item);
        $this->assertSame(AccountingEntryStatus::Ready, $draft->status);
        $this->assertNull($draft->journal_no);
        $this->assertSame($this->admin->id, $draft->created_by);

        // In der Inbox steht der Entwurf zur Freigabe.
        $listed = $this->inbox()->items($this->org, $this->startsOn, $this->startsOn->endOfYear())
            ->first(fn (array $row): bool => $row['entry']?->id === $draft->id);
        $this->assertNotNull($listed);
        $this->assertSame(DirectBookingKind::OpenItemSettlement, $listed['kind']);
        $this->assertSame('ready', $listed['state']);
    }

    public function test_a_second_person_posts_the_discount_and_the_item_is_settled(): void {
        $item = $this->openItem();
        $this->enableFourEyes();
        $this->actingAs($this->admin);
        app(OpenItemService::class)->settle($item, SettlementKind::Discount, '2.38');
        $draft = $this->settlementEntry($item);

        $this->actingAs($this->second);
        $posted = app(JournalService::class)->post($draft, $this->second);

        $this->assertSame(AccountingEntryStatus::Posted, $posted->status);
        $item->refresh();
        $this->assertSame('116.62', $item->open_amount?->getAmount());
        $settlement = $item->settlements->sole();
        $this->assertSame(SettlementKind::Discount, $settlement->kind);
        $this->assertSame($posted->id, $settlement->accounting_entry_id);
    }

    /** Auch ohne Belegbezug trifft die Freigabe genau diesen Posten. */
    public function test_a_write_off_draft_settles_exactly_its_item_on_approval(): void {
        $item = $this->openItem();
        $item->forceFill(['source_type' => null, 'source_id' => null])->save();
        $other = $this->openItem();
        $this->enableFourEyes();
        $this->actingAs($this->admin);

        app(OpenItemService::class)->settle($item, SettlementKind::WriteOff, '19.00');
        app(JournalService::class)->post($this->settlementEntry($item), $this->second);

        $this->assertSame('100.00', $item->refresh()->open_amount?->getAmount());
        $this->assertSame(SettlementKind::WriteOff, $item->settlements->sole()->kind);
        $this->assertSame('119.00', $other->refresh()->open_amount?->getAmount());
    }

    public function test_the_preparer_cannot_post_the_own_discount_draft(): void {
        $item = $this->openItem();
        $this->enableFourEyes();
        $this->actingAs($this->admin);
        app(OpenItemService::class)->settle($item, SettlementKind::Discount, '2.38');

        $this->assertFourEyesRefusal(fn () => app(JournalService::class)->post($this->settlementEntry($item), $this->admin));
        $this->assertSame('119.00', $item->refresh()->open_amount?->getAmount());
    }

    public function test_a_second_settlement_while_a_draft_waits_is_refused(): void {
        $item = $this->openItem();
        $this->enableFourEyes();
        $this->actingAs($this->admin);
        app(OpenItemService::class)->settle($item, SettlementKind::Discount, '2.38');

        foreach ([[SettlementKind::Discount, '2.38'], [SettlementKind::WriteOff, '5.00'], [SettlementKind::Retention, '10.00']] as [$kind, $amount]) {
            try {
                app(OpenItemService::class)->settle($item, $kind, $amount);
                $this->fail('Zweiter Ausgleich trotz wartendem Entwurf: ' . $kind->value);
            } catch (ValidationException $exception) {
                $this->assertSame((string) __('accounting.open_items.error.draft_pending'), $exception->errors()['amount'][0] ?? null);
            }
        }

        $this->assertSame(1, AccountingEntry::query()->where('source_type', MorphMap::alias(AccountingOpenItem::class))->count());
        $this->assertSame('119.00', $item->refresh()->open_amount?->getAmount());
    }

    /** Wurde der Posten inzwischen bezahlt, scheitert die Freigabe statt still zu buchen. */
    public function test_approval_fails_when_the_item_was_settled_in_the_meantime(): void {
        $item = $this->openItem();
        $this->enableFourEyes();
        $this->actingAs($this->admin);
        app(OpenItemService::class)->settle($item, SettlementKind::Discount, '2.38');
        $draft = $this->settlementEntry($item);

        // Zahlung über den vollen Betrag, von der zweiten Person festgeschrieben.
        $payment = app(JournalService::class)->draft($this->org, [
            'booked_on' => $this->startsOn->addMonths(2),
            'memo' => 'Zahlung',
            'snapshot' => ['settles_source_type' => $item->source_type, 'settles_source_id' => $item->source_id],
            'lines' => [
                ['accounting_account_id' => $this->accounts['bank']->id, 'debit' => '119.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['receivable']->id, 'debit' => '0.00', 'credit' => '119.00'],
            ],
        ], $this->admin);
        app(JournalService::class)->post($payment, $this->second);
        $this->assertSame(OpenItemStatus::Settled, $item->refresh()->status);

        try {
            app(JournalService::class)->post($draft, $this->second);
            $this->fail('Ausgleich über den offenen Rest hinaus wurde festgeschrieben.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $this->assertSame(AccountingEntryStatus::Ready, $draft->refresh()->status);
        $this->assertNull($draft->journal_no);
    }

    public function test_settling_over_http_with_four_eyes_says_a_draft_was_created(): void {
        $item = $this->openItem();
        $this->enableFourEyes();

        $this->actingAs($this->admin)
            ->post(route('finance.accounting.open-items.settle', $item), ['kind' => 'discount', 'amount' => '2.38'])
            ->assertRedirect()
            ->assertSessionHas('status', (string) __('accounting.open_items.flash.awaiting_approval'));

        $this->actingAs($this->admin)
            ->get(route('finance.accounting.open-items.index'))
            ->assertOk()
            ->assertSee((string) __('accounting.open_items.awaiting_approval'))
            ->assertDontSee(route('finance.accounting.open-items.settle-form', $item), false);

        $this->actingAs($this->second)
            ->get(route('finance.accounting.inbox.index'))
            ->assertOk()
            ->assertSee(DirectBookingKind::OpenItemSettlement->label())
            ->assertSee(route('finance.accounting.inbox.post', $this->settlementEntry($item)), false);
    }

    // ── Klärungsbuchung ─────────────────────────────────────────────────

    private function clearing(BankTransaction $transaction, User $actor): AccountingEntry {
        return $this->inbox()->postBankTransactionToClearing(
            $this->org,
            $transaction,
            $this->accounts['transit'],
            'Zahlung ohne erkennbaren Absender',
            $this->startsOn->addMonth(),
            $actor,
        );
    }

    public function test_without_four_eyes_a_clearing_entry_is_posted_at_once(): void {
        $transaction = $this->transaction();

        $entry = $this->clearing($transaction, $this->admin);

        $this->assertSame(AccountingEntryStatus::Posted, $entry->status);
        $this->assertSame('posted', $this->inbox()->bankTransactionStates($this->org, [$transaction->refresh()])[$transaction->id]['state']);
    }

    public function test_with_four_eyes_a_clearing_entry_waits_for_a_second_person(): void {
        $this->enableFourEyes();
        $transaction = $this->transaction();

        $draft = $this->clearing($transaction, $this->admin);
        $again = $this->clearing($transaction, $this->admin);

        $this->assertSame(AccountingEntryStatus::Ready, $draft->status);
        $this->assertSame($draft->id, $again->id, 'Kein zweiter Entwurf für denselben Umsatz.');
        $this->assertSame('ready', $this->inbox()->bankTransactionStates($this->org, [$transaction->refresh()])[$transaction->id]['state']);
        $this->assertSame(DirectBookingKind::Clearing, DirectBookingKind::of($draft));

        $this->assertFourEyesRefusal(fn () => app(JournalService::class)->post($draft, $this->admin));

        app(JournalService::class)->post($draft, $this->second);
        $this->assertSame('posted', $this->inbox()->bankTransactionStates($this->org, [$transaction->refresh()])[$transaction->id]['state']);
    }

    public function test_clearing_over_http_with_four_eyes_says_a_draft_was_created(): void {
        $this->enableFourEyes();
        $transaction = $this->transaction();

        $this->actingAs($this->admin)
            ->post(route('finance.accounting.inbox.clearing.store', $transaction->sqid), [
                'clearing_account' => $this->accounts['transit']->sqid,
                'note' => 'Zahlung ohne erkennbaren Absender',
                'follow_up_on' => $this->startsOn->addMonth()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHas('status', (string) __('accounting.inbox.flash.awaiting_approval'));
    }

    // ── Interne Umbuchung ───────────────────────────────────────────────

    /** @return array{booked_on: CarbonImmutable, amount: string, from_account: AccountingAccount, to_account: AccountingAccount, note: string, from_source: ?BankTransaction} */
    private function transferData(?BankTransaction $source = null): array {
        return [
            'booked_on' => $this->startsOn->addDays(20),
            'amount' => '500.00',
            'from_account' => $this->accounts['bank'],
            'to_account' => $this->accounts['cash'],
            'note' => 'Bankabhebung für die Kasse',
            'from_source' => $source,
        ];
    }

    public function test_without_four_eyes_a_transfer_is_posted_at_once(): void {
        $transfer = app(InternalTransferService::class)->record($this->org, $this->transferData(), $this->admin);

        $this->assertSame(AccountingEntryStatus::Posted, $transfer->entry?->status);
    }

    public function test_with_four_eyes_a_transfer_waits_for_a_second_person(): void {
        $this->enableFourEyes();
        $transaction = $this->transaction('-500.00');
        $service = app(InternalTransferService::class);

        $transfer = $service->record($this->org, $this->transferData($transaction), $this->admin);
        $again = $service->record($this->org, $this->transferData($transaction), $this->admin);

        $this->assertSame($transfer->id, $again->id);
        $this->assertSame(1, AccountingTransfer::query()->count());
        $draft = $transfer->entry;
        $this->assertSame(AccountingEntryStatus::Ready, $draft?->status);
        $this->assertSame('ready', $this->inbox()->bankTransactionStates($this->org, [$transaction->refresh()])[$transaction->id]['state']);

        $this->assertFourEyesRefusal(fn () => app(JournalService::class)->post($draft, $this->admin));

        app(JournalService::class)->post($draft, $this->second);
        $this->assertSame('posted', $this->inbox()->bankTransactionStates($this->org, [$transaction->refresh()])[$transaction->id]['state']);
    }

    public function test_transfer_over_http_with_four_eyes_says_a_draft_was_created(): void {
        $this->enableFourEyes();

        $this->actingAs($this->admin)
            ->post(route('finance.accounting.inbox.transfer.store'), [
                'from_account' => $this->accounts['bank']->sqid,
                'to_account' => $this->accounts['cash']->sqid,
                'amount' => '500.00',
                'booked_on' => $this->startsOn->addDays(20)->toDateString(),
                'note' => 'Bankabhebung für die Kasse',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', (string) __('accounting.inbox.flash.awaiting_approval'));
    }

    // ── Startsalden ─────────────────────────────────────────────────────

    private function openingCsv(): string {
        $path = tempnam(sys_get_temp_dir(), 'opening') . '.csv';
        file_put_contents($path, "account;debit;credit\r\n1200;5000,00;0\r\n9000;0;5000,00\r\n");

        return $path;
    }

    public function test_without_four_eyes_opening_balances_are_posted_at_once(): void {
        $path = $this->openingCsv();
        $entry = app(OpeningBalanceImportService::class)->import($this->org, $path, $this->admin);
        unlink($path);

        $this->assertSame(AccountingEntryStatus::Posted, $entry->status);
        $this->assertNotNull($entry->journal_no);
    }

    public function test_with_four_eyes_opening_balances_wait_for_a_second_person(): void {
        $this->enableFourEyes();
        $path = $this->openingCsv();

        $draft = app(OpeningBalanceImportService::class)->import($this->org, $path, $this->admin);
        $again = app(OpeningBalanceImportService::class)->import($this->org, $path, $this->admin);
        unlink($path);

        $this->assertSame(AccountingEntryStatus::Ready, $draft->status);
        $this->assertNull($draft->journal_no);
        $this->assertSame($draft->id, $again->id);
        $this->assertSame(1, AccountingEntry::query()->where('source_key', 'opening_balance')->count());

        $this->assertFourEyesRefusal(fn () => app(JournalService::class)->post($draft, $this->admin));

        $posted = app(JournalService::class)->post($draft, $this->second);
        $this->assertSame(AccountingEntryStatus::Posted, $posted->status);
        $this->assertNotNull($posted->journal_no);
    }

    public function test_opening_import_over_http_with_four_eyes_says_a_draft_was_created(): void {
        $this->enableFourEyes();
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('salden.csv', "account;debit;credit\r\n1200;1,00;0\r\n9000;0;1,00\r\n");

        $this->actingAs($this->admin)
            ->post(route('finance.accounting.closing.opening-balances'), ['file' => $file, 'dry_run' => '0'])
            ->assertRedirect()
            ->assertSessionHas('status', (string) __('accounting.inbox.flash.awaiting_approval'));
    }

    // ── Sondervorauszahlung ─────────────────────────────────────────────

    private function prepayment(User $actor): AccountingEntry {
        return app(VatSpecialPrepaymentService::class)->post(
            $this->org,
            2026,
            $this->accounts['prepayment'],
            $this->accounts['bank'],
            '100.00',
            CarbonImmutable::parse('2026-02-10'),
            $actor,
        );
    }

    private function decemberPrepayment(): string {
        $december = app(VatFilingPeriodService::class)->parse('2026-M12');
        $this->assertNotNull($december);

        return (string) app(VatReturnService::class)->preview($this->org, $december)['special_prepayment'];
    }

    private function extension(): AccountingVatExtension {
        app(VatFilingProfileResolver::class)->switchTo($this->org, VatFilingInterval::Monthly, $this->startsOn, $this->admin, null);

        return app(VatFilingProfileResolver::class)->recordExtension($this->org, 2026, CarbonImmutable::parse('2026-02-08'), '100.00', $this->admin, null);
    }

    public function test_without_four_eyes_a_special_prepayment_is_credited_at_once(): void {
        $extension = $this->extension();

        $entry = $this->prepayment($this->admin);

        $this->assertSame(AccountingEntryStatus::Posted, $entry->status);
        $this->assertSame($entry->id, $extension->refresh()->special_prepayment_entry_id);
        $this->assertSame('100.00', $this->decemberPrepayment());
    }

    public function test_with_four_eyes_a_special_prepayment_is_credited_only_after_approval(): void {
        $extension = $this->extension();
        $this->enableFourEyes();

        $draft = $this->prepayment($this->admin);
        $again = $this->prepayment($this->admin);

        $this->assertSame(AccountingEntryStatus::Ready, $draft->status);
        $this->assertSame($draft->id, $again->id);
        $this->assertNull($extension->refresh()->special_prepayment_entry_id);
        $this->assertSame('0.00', $this->decemberPrepayment());

        $this->assertFourEyesRefusal(fn () => app(JournalService::class)->post($draft, $this->admin));
        $this->assertNull($extension->refresh()->special_prepayment_entry_id);

        app(JournalService::class)->post($draft, $this->second);

        $this->assertSame($draft->id, $extension->refresh()->special_prepayment_entry_id);
        $this->assertSame('100.00', $extension->special_prepayment_amount?->getAmount());
        $this->assertSame('100.00', $this->decemberPrepayment());
    }

    public function test_prepayment_over_http_with_four_eyes_says_a_draft_was_created(): void {
        $this->extension();
        $this->enableFourEyes();

        $this->actingAs($this->admin)
            ->post(route('finance.accounting.prepayment'), [
                'year' => 2026,
                'amount' => '100.00',
                'prepayment_account' => $this->accounts['prepayment']->sqid,
                'money_account' => $this->accounts['bank']->sqid,
                'booked_on' => '2026-02-10',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', (string) __('accounting.inbox.flash.awaiting_approval'));
    }

    // ── Inbox und Journal ───────────────────────────────────────────────

    public function test_the_batch_of_a_second_person_posts_waiting_direct_bookings(): void {
        $this->enableFourEyes();
        $draft = $this->clearing($this->transaction(), $this->admin);

        $this->actingAs($this->second)
            ->post(route('finance.accounting.inbox.batch'), ['post' => '1'])
            ->assertRedirect();

        $this->assertSame(AccountingEntryStatus::Posted, $draft->refresh()->status);
        $this->assertSame($this->second->id, $draft->posted_by);
    }

    public function test_a_kind_filter_hides_waiting_direct_bookings(): void {
        $this->enableFourEyes();
        $draft = $this->clearing($this->transaction(), $this->admin);

        $filtered = $this->inbox()->items($this->org, $this->startsOn, $this->startsOn->endOfYear(), PostingSourceKind::SalesInvoice);

        $this->assertNull($filtered->first(fn (array $row): bool => $row['entry']?->id === $draft->id));
    }

    /** Das Journal „anlegen und festschreiben" bleibt gesperrt (S-30). */
    public function test_journal_create_and_post_stays_refused_under_four_eyes(): void {
        $this->enableFourEyes();

        $this->actingAs($this->admin)
            ->post(route('finance.accounting.journal.store'), [
                'booked_on' => $this->startsOn->addMonth()->toDateString(),
                'memo' => 'Direkt festschreiben',
                'debit_account' => $this->accounts['bank']->sqid,
                'credit_account' => $this->accounts['revenue']->sqid,
                'amount' => '10.00',
                'post' => '1',
            ])
            ->assertSessionHasErrors('four_eyes');

        $this->assertSame(0, AccountingEntry::query()->where('memo', 'Direkt festschreiben')->count());
    }

    // ── Storno (E23) ────────────────────────────────────────────────────

    private function invoiceEntry(AccountingOpenItem $item): AccountingEntry {
        return AccountingEntry::query()->findOrFail($item->accounting_entry_id);
    }

    public function test_without_four_eyes_a_reversal_is_posted_at_once(): void {
        $item = $this->openItem();
        $original = $this->invoiceEntry($item);

        $reversal = app(JournalService::class)->reverse($original, 'Falsch erfasst', $this->admin);

        $this->assertSame(AccountingEntryStatus::Posted, $reversal->status);
        $this->assertSame(AccountingEntryStatus::Reversed, $original->status);
        $this->assertSame(OpenItemStatus::Settled, $item->refresh()->status);
    }

    public function test_with_four_eyes_a_reversal_waits_and_the_original_stays_posted(): void {
        $item = $this->openItem();
        $original = $this->invoiceEntry($item);
        $this->enableFourEyes();

        $draft = app(JournalService::class)->reverse($original, 'Falsch erfasst', $this->admin);

        $this->assertSame(AccountingEntryStatus::Ready, $draft->status);
        $this->assertNull($draft->journal_no);
        $this->assertSame($original->id, $draft->reverses_entry_id);
        $this->assertSame(DirectBookingKind::Reversal, DirectBookingKind::of($draft));
        $original->refresh();
        $this->assertSame(AccountingEntryStatus::Posted, $original->status);
        $this->assertNull($original->reversed_by_entry_id);
        $this->assertSame('119.00', $item->refresh()->open_amount?->getAmount());

        $listed = $this->inbox()->items($this->org, $this->startsOn, $this->startsOn->endOfYear())
            ->first(fn (array $row): bool => $row['entry']?->id === $draft->id);
        $this->assertSame(DirectBookingKind::Reversal, $listed['kind'] ?? null);

        try {
            app(JournalService::class)->reverse($original, 'Noch einmal', $this->second);
            $this->fail('Zweiter Storno-Entwurf für dieselbe Buchung.');
        } catch (ValidationException $exception) {
            $this->assertSame((string) __('accounting.ledger.error.reversal_pending'), $exception->errors()['status'][0] ?? null);
        }
        $this->assertSame(1, AccountingEntry::query()->where('reverses_entry_id', $original->id)->count());
    }

    public function test_a_second_person_posts_the_reversal_and_its_effect_follows(): void {
        $item = $this->openItem();
        $original = $this->invoiceEntry($item);
        $this->enableFourEyes();
        $draft = app(JournalService::class)->reverse($original, 'Falsch erfasst', $this->admin);

        $this->assertFourEyesRefusal(fn () => app(JournalService::class)->post($draft, $this->admin));
        $this->assertSame(AccountingEntryStatus::Posted, $original->refresh()->status);

        $posted = app(JournalService::class)->post($draft, $this->second);

        $this->assertSame(AccountingEntryStatus::Posted, $posted->status);
        $original->refresh();
        $this->assertSame(AccountingEntryStatus::Reversed, $original->status);
        $this->assertSame($posted->id, $original->reversed_by_entry_id);
        $this->assertSame('Falsch erfasst', $original->reversal_reason);
        $item->refresh();
        $this->assertSame(OpenItemStatus::Settled, $item->status);
        $this->assertSame(SettlementKind::Reversal, $item->settlements->sole()->kind);

        $event = AccountingEvent::query()->where('event', 'accounting.entry_reversed')->sole();
        $this->assertSame($this->second->id, $event->actor_user_id);
        $this->assertSame($original->id, $event->accounting_entry_id);
    }

    public function test_reversing_over_http_with_four_eyes_says_a_draft_was_created(): void {
        $original = $this->invoiceEntry($this->openItem());
        $this->enableFourEyes();

        $response = $this->actingAs($this->admin)
            ->post(route('finance.accounting.journal.reverse', $original), ['reversal_reason' => 'Falsch erfasst'])
            ->assertSessionHas('status', (string) __('accounting.ledger.flash.reversal_awaiting_approval'));
        $draft = AccountingEntry::query()->where('reverses_entry_id', $original->id)->sole();
        $response->assertRedirect(route('finance.accounting.journal.show', $draft));

        $this->actingAs($this->admin)
            ->get(route('finance.accounting.journal.show', $original))
            ->assertOk()
            ->assertSee((string) __('accounting.ledger.entry.reversal_pending'))
            ->assertDontSee(route('finance.accounting.journal.reverse-form', $original), false);

        // Festschreiben bietet nur die Seite der zweiten Person an; Verwerfen bleibt.
        $this->actingAs($this->admin)
            ->get(route('finance.accounting.journal.show', $draft))
            ->assertOk()
            ->assertDontSee(route('finance.accounting.journal.post', $draft), false)
            ->assertSee(route('finance.accounting.journal.discard', $draft), false);
        $this->actingAs($this->admin)
            ->get(route('finance.accounting.inbox.index'))
            ->assertOk()
            ->assertDontSee(route('finance.accounting.inbox.post', $draft), false)
            ->assertSee(route('finance.accounting.journal.discard', $draft), false);
        $this->actingAs($this->second)
            ->get(route('finance.accounting.journal.show', $draft))
            ->assertOk()
            ->assertSee(route('finance.accounting.journal.post', $draft), false);
        $this->actingAs($this->second)
            ->get(route('finance.accounting.inbox.index'))
            ->assertOk()
            ->assertSee(route('finance.accounting.inbox.post', $draft), false);
    }

    /** @return array{0: PaymentAllocation, 1: AccountingEntry} */
    private function postedPayment(): array {
        $item = $this->openItem();
        $allocation = PaymentAllocation::query()->create([
            'organization_id' => $this->org->id,
            'bank_transaction_id' => $this->transaction('119.00')->id,
            'allocatable_type' => MorphMap::alias(Invoice::class),
            'allocatable_id' => $item->source_id,
            'amount' => '119.00',
            'kind' => AllocationKind::Payment,
            'confirmed_by_user_id' => $this->admin->id,
            'confirmed_at' => now(),
        ]);

        $entry = app(JournalService::class)->draft($this->org, [
            'booked_on' => $this->startsOn->addDays(15),
            'memo' => 'Zahlung',
            'source_key' => PostingSourceKind::Payment->keyPrefix() . ':' . $allocation->id,
            'lines' => [
                ['accounting_account_id' => $this->accounts['bank']->id, 'debit' => '119.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['revenue']->id, 'debit' => '0.00', 'credit' => '119.00'],
            ],
        ], $this->second);

        return [$allocation, app(JournalService::class)->post($entry, $this->admin)];
    }

    /** Das automatische Storno beim Aufheben einer Zuordnung bleibt sofort (E23). */
    public function test_the_automatic_reversal_on_unmatch_stays_immediate_under_four_eyes(): void {
        [$allocation, $entry] = $this->postedPayment();
        $this->enableFourEyes();
        $this->actingAs($this->admin);

        $allocation->delete();

        $entry->refresh();
        $this->assertSame(AccountingEntryStatus::Reversed, $entry->status);
        $this->assertSame(AccountingEntryStatus::Posted, AccountingEntry::query()->findOrFail($entry->reversed_by_entry_id)->status);
    }

    /** Ein wartendes Storno von Hand wird vom automatischen überholt und verworfen. */
    public function test_the_automatic_reversal_discards_a_waiting_manual_reversal(): void {
        [$allocation, $entry] = $this->postedPayment();
        $this->enableFourEyes();
        $this->actingAs($this->admin);
        $manual = app(JournalService::class)->reverse($entry, 'Von Hand', $this->admin);

        $allocation->delete();

        $this->assertNull(AccountingEntry::query()->find($manual->id));
        $entry->refresh();
        $this->assertSame(AccountingEntryStatus::Reversed, $entry->status);
        $this->assertNotSame($manual->id, $entry->reversed_by_entry_id);
        $this->assertSame(1, AccountingEvent::query()->where('event', 'accounting.entry_discarded')->count());
    }

    // ── Entwurf verwerfen (E24) ─────────────────────────────────────────

    public function test_discarding_a_discount_draft_makes_the_item_settleable_again(): void {
        $item = $this->openItem();
        $this->enableFourEyes();
        $this->actingAs($this->admin);
        app(OpenItemService::class)->settle($item, SettlementKind::Discount, '2.38');
        $draft = $this->settlementEntry($item);

        app(JournalService::class)->discard($draft, $this->admin);

        $this->assertNull(AccountingEntry::query()->find($draft->id));
        $this->assertSame([], app(OpenItemService::class)->pendingDrafts([$item->refresh()]));
        $event = AccountingEvent::query()->where('event', 'accounting.entry_discarded')->sole();
        $this->assertSame($this->admin->id, $event->actor_user_id);
        $this->assertSame(DirectBookingKind::OpenItemSettlement->value, $event->payload['kind'] ?? null);

        $this->assertNull(app(OpenItemService::class)->settle($item, SettlementKind::WriteOff, '5.00'));
    }

    public function test_discarding_a_transfer_draft_removes_the_transfer(): void {
        $this->enableFourEyes();
        $transaction = $this->transaction('-500.00');
        $transfer = app(InternalTransferService::class)->record($this->org, $this->transferData($transaction), $this->admin);

        app(JournalService::class)->discard(AccountingEntry::query()->findOrFail($transfer->accounting_entry_id), $this->second);

        $this->assertSame(0, AccountingTransfer::query()->count());
        $this->assertSame('open', $this->inbox()->bankTransactionStates($this->org, [$transaction->refresh()])[$transaction->id]['state']);
        $again = app(InternalTransferService::class)->record($this->org, $this->transferData($transaction), $this->admin);
        $this->assertNotSame($transfer->id, $again->id);
    }

    public function test_discarding_clearing_opening_and_prepayment_drafts_lets_them_start_again(): void {
        $extension = $this->extension();
        $this->enableFourEyes();
        $journal = app(JournalService::class);

        $transaction = $this->transaction();
        $clearing = $this->clearing($transaction, $this->admin);
        $journal->discard($clearing, $this->admin);
        $this->assertSame('open', $this->inbox()->bankTransactionStates($this->org, [$transaction->refresh()])[$transaction->id]['state']);
        $this->assertNotSame($clearing->id, $this->clearing($transaction, $this->admin)->id);

        $path = $this->openingCsv();
        $opening = app(OpeningBalanceImportService::class)->import($this->org, $path, $this->admin);
        $journal->discard($opening, $this->admin);
        $reimported = app(OpeningBalanceImportService::class)->import($this->org, $path, $this->admin);
        unlink($path);
        $this->assertNotSame($opening->id, $reimported->id);
        $this->assertSame('opening_balance', $reimported->source_key);

        $prepayment = $this->prepayment($this->admin);
        $journal->discard($prepayment, $this->admin);
        $this->assertNull($extension->refresh()->special_prepayment_entry_id);
        $repost = $this->prepayment($this->admin);
        $this->assertNotSame($prepayment->id, $repost->id);
        $this->assertSame($extension->prepaymentSourceKey(), $repost->source_key);
    }

    public function test_discarding_a_reversal_draft_lets_the_entry_be_reversed_again(): void {
        $original = $this->invoiceEntry($this->openItem());
        $this->enableFourEyes();
        $draft = app(JournalService::class)->reverse($original, 'Falsch erfasst', $this->admin);

        app(JournalService::class)->discard($draft, $this->second);

        $this->assertSame(AccountingEntryStatus::Posted, $original->refresh()->status);
        $this->assertNull(app(JournalService::class)->pendingReversalOf($original));
        $again = app(JournalService::class)->reverse($original, 'Neu begründet', $this->admin);
        $this->assertSame(AccountingEntryStatus::Ready, $again->status);
    }

    public function test_only_waiting_direct_bookings_can_be_discarded(): void {
        $item = $this->openItem();
        $posted = $this->invoiceEntry($item);
        $proposalDraft = app(JournalService::class)->draft($this->org, [
            'booked_on' => $this->startsOn->addMonth(),
            'memo' => 'Handbuchung',
            'lines' => [
                ['accounting_account_id' => $this->accounts['bank']->id, 'debit' => '10.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['revenue']->id, 'debit' => '0.00', 'credit' => '10.00'],
            ],
        ], $this->admin);

        foreach ([$posted, $proposalDraft] as $entry) {
            try {
                app(JournalService::class)->discard($entry, $this->admin);
                $this->fail('Verworfen, obwohl keine wartende Direktbuchung: #' . $entry->id);
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('status', $exception->errors());
            }
            $this->assertNotNull(AccountingEntry::query()->find($entry->id));
        }
    }

    public function test_discarding_over_http_needs_the_post_permission_and_returns_to_the_open_items(): void {
        $item = $this->openItem();
        $this->enableFourEyes();
        $this->actingAs($this->admin);
        app(OpenItemService::class)->settle($item, SettlementKind::Discount, '2.38');
        $draft = $this->settlementEntry($item);
        $member = User::factory()->create(['organization_id' => $this->org->id]);

        $this->actingAs($member)
            ->post(route('finance.accounting.journal.discard', $draft))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('finance.accounting.journal.show', $draft))
            ->assertOk()
            ->assertSee(route('finance.accounting.journal.discard', $draft), false);
        $this->actingAs($this->second)
            ->get(route('finance.accounting.inbox.index'))
            ->assertOk()
            ->assertSee(route('finance.accounting.journal.discard', $draft), false);

        $this->actingAs($this->admin)
            ->post(route('finance.accounting.journal.discard', $draft))
            ->assertRedirect(route('finance.accounting.open-items.index'))
            ->assertSessionHas('status', (string) __('accounting.ledger.flash.entry_discarded'));

        $this->assertNull(AccountingEntry::query()->find($draft->id));
        $this->actingAs($this->admin)
            ->get(route('finance.accounting.open-items.index'))
            ->assertSee(route('finance.accounting.open-items.settle-form', $item), false);
    }
}
