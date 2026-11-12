<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingBankCashLinkTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AccountType, AllocationKind, PostingAccountRole, PostingSourceKind, ProfitDetermination};
use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Accounting\{AccountingAccount, AccountingPostingRule, AccountingTransfer};
use App\Models\Customer\Customer;
use App\Models\Finance\{BankStatement, BankTransaction, PaymentAllocation};
use App\Models\Invoicing\Invoice;
use App\Models\Platform\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsService, FiscalYearService, InternalTransferService};
use App\Services\Accounting\Posting\{PostingInboxService, PostingSourceRegistry};
use App\Services\Accounting\Reports\DataQualityBuilder;
use App\Support\MorphMap;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Bank- und Kassenanschluss (Feature 125, MVP-681).
 *
 * Abnahme: Der Buchungsstand wird gelesen, nicht gespeichert; eine
 * Klärungsbuchung braucht eine Notiz; eine interne Umbuchung erzeugt genau
 * eine Buchung und beide Seiten gelten danach als erledigt.
 */
class AccountingBankCashLinkTest extends TestCase {
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
        $this->accounts['bank'] = $chart->create($this->org, ['number' => '1200', 'name' => 'Bank', 'type' => AccountType::Asset, 'is_bank' => true]);
        $this->accounts['cash'] = $chart->create($this->org, ['number' => '1000', 'name' => 'Kasse', 'type' => AccountType::Asset, 'is_cash' => true]);
        $this->accounts['transit'] = $chart->create($this->org, ['number' => '1360', 'name' => 'Geldtransit', 'type' => AccountType::Asset, 'is_clearing' => true]);
        $this->accounts['revenue'] = $chart->create($this->org, ['number' => '8400', 'name' => 'Erlöse', 'type' => AccountType::Income]);

        AccountingPostingRule::query()->create([
            'organization_id' => $this->org->id,
            'source_kind' => PostingSourceKind::Payment,
            'role' => PostingAccountRole::Bank,
            'accounting_account_id' => $this->accounts['bank']->id,
            'priority' => 100,
            'version' => 1,
            'valid_from' => $this->startsOn->toDateString(),
            'is_active' => true,
        ]);
    }

    private function transaction(float $amount = 250.00): BankTransaction {
        return BankTransaction::factory()->create([
            'organization_id' => $this->org->id,
            'bank_statement_id' => BankStatement::factory()->create(['organization_id' => $this->org->id])->id,
            'booking_date' => $this->startsOn->addDays(15)->toDateString(),
            'amount' => (string) $amount,
            'currency' => 'EUR',
        ]);
    }

    private function inbox(): PostingInboxService {
        return app(PostingInboxService::class);
    }

    /** Ohne Zuordnung ist ein Umsatz ungebucht — mit klarem Grund. */
    public function test_an_unassigned_transaction_reads_as_open(): void {
        $transaction = $this->transaction();

        $states = $this->inbox()->bankTransactionStates($this->org, [$transaction]);

        $this->assertSame('open', $states[$transaction->id]['state']);
        $this->assertNotEmpty($states[$transaction->id]['blockers']);
    }

    /** Die Klärungsbuchung braucht eine Notiz. */
    public function test_a_clearing_entry_requires_a_note(): void {
        $transaction = $this->transaction();

        $this->expectException(ValidationException::class);
        $this->inbox()->postBankTransactionToClearing(
            $this->org,
            $transaction,
            $this->accounts['transit'],
            '   ',
            $this->startsOn->addMonth(),
            $this->admin,
        );
    }

    /** Ein Konto ohne Klärungskennzeichen kommt nicht in Frage. */
    public function test_only_clearing_accounts_are_accepted(): void {
        $transaction = $this->transaction();

        $this->expectException(ValidationException::class);
        $this->inbox()->postBankTransactionToClearing(
            $this->org,
            $transaction,
            $this->accounts['revenue'],
            'Zahlung ohne erkennbaren Absender',
            $this->startsOn->addMonth(),
            $this->admin,
        );
    }

    /** Nach der Klärungsbuchung gilt der Umsatz als gebucht. */
    public function test_a_clearing_entry_marks_the_transaction_as_posted(): void {
        $transaction = $this->transaction();

        $entry = $this->inbox()->postBankTransactionToClearing(
            $this->org,
            $transaction,
            $this->accounts['transit'],
            'Zahlung ohne erkennbaren Absender',
            $this->startsOn->addMonth(),
            $this->admin,
        );

        $states = $this->inbox()->bankTransactionStates($this->org, [$transaction->fresh()]);

        $this->assertSame('posted', $states[$transaction->id]['state']);
        $this->assertSame($entry->id, $states[$transaction->id]['entry']?->id);
        $this->assertSame('250.00', $entry->debitTotal()?->getAmount());

        // Notiz und Wiedervorlage stehen im Nachweis, nicht nur im Kopf.
        $this->assertSame('Zahlung ohne erkennbaren Absender', $entry->snapshot['clearing']['note'] ?? null);
        $this->assertSame($this->startsOn->addMonth()->toDateString(), $entry->snapshot['clearing']['follow_up_on'] ?? null);
    }

    /** Ein offenes Klärungskonto steht im Datenqualitätsbericht. */
    public function test_an_open_clearing_balance_shows_in_the_quality_report(): void {
        $transaction = $this->transaction();
        $this->inbox()->postBankTransactionToClearing(
            $this->org,
            $transaction,
            $this->accounts['transit'],
            'Zahlung ohne erkennbaren Absender',
            $this->startsOn->addMonth(),
            $this->admin,
        );

        $quality = app(DataQualityBuilder::class)->build($this->org, $this->startsOn, $this->startsOn->endOfYear());

        $this->assertSame(1, $quality['open_clearing']);
    }

    /** Eine Umbuchung erzeugt genau eine Buchung. */
    public function test_an_internal_transfer_creates_exactly_one_entry(): void {
        $transfer = app(InternalTransferService::class)->record($this->org, [
            'booked_on' => $this->startsOn->addDays(20),
            'amount' => '500.00',
            'from_account' => $this->accounts['bank'],
            'to_account' => $this->accounts['cash'],
            'note' => 'Bankabhebung für die Kasse',
        ], $this->admin);

        $this->assertNotNull($transfer->accounting_entry_id);
        $this->assertSame('500.00', $transfer->entry?->debitTotal()?->getAmount());
        $this->assertSame(1, AccountingTransfer::query()->count());
    }

    /** Quell- und Zielkonto dürfen nicht dasselbe sein. */
    public function test_a_transfer_needs_two_different_accounts(): void {
        $this->expectException(ValidationException::class);
        app(InternalTransferService::class)->record($this->org, [
            'booked_on' => $this->startsOn->addDays(20),
            'amount' => '500.00',
            'from_account' => $this->accounts['bank'],
            'to_account' => $this->accounts['bank'],
            'note' => 'Unsinn',
        ], $this->admin);
    }

    /** Ein Erfolgskonto ist kein Geldkonto. */
    public function test_a_transfer_refuses_a_revenue_account(): void {
        $this->expectException(ValidationException::class);
        app(InternalTransferService::class)->record($this->org, [
            'booked_on' => $this->startsOn->addDays(20),
            'amount' => '500.00',
            'from_account' => $this->accounts['bank'],
            'to_account' => $this->accounts['revenue'],
            'note' => 'Erlös getarnt als Umbuchung',
        ], $this->admin);
    }

    /**
     * Eine Zahlung in fremder Währung wird blockiert, nicht umgerechnet.
     *
     * § 16 Abs. 6 UStG verlangt BMF-Monatskurse; ohne belegbaren Kurs wäre
     * jede Zahl geraten — und der CHF-Betrag stünde als Euro im Journal.
     */
    public function test_a_payment_in_foreign_currency_is_blocked(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);
        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => $customer->id,
            'number' => 'RE-CHF',
            'status' => InvoiceStatus::Issued,
            'issued_on' => $this->startsOn->addDays(5)->toDateString(),
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '19.00',
            'total' => '119.00',
        ]);

        $transaction = BankTransaction::factory()->create([
            'organization_id' => $this->org->id,
            'bank_statement_id' => BankStatement::factory()->create(['organization_id' => $this->org->id])->id,
            'booking_date' => $this->startsOn->addDays(15)->toDateString(),
            'amount' => '119.00',
            'currency' => 'CHF',
        ]);

        $allocation = PaymentAllocation::query()->create([
            'organization_id' => $this->org->id,
            'bank_transaction_id' => $transaction->id,
            'allocatable_type' => MorphMap::alias(Invoice::class),
            'allocatable_id' => $invoice->id,
            'amount' => '119.00',
            'kind' => AllocationKind::Payment,
            'confirmed_by_user_id' => $this->admin->id,
            'confirmed_at' => now(),
        ]);

        $proposal = app(PostingSourceRegistry::class)
            ->for(PostingSourceKind::Payment)
            ->proposalFor($this->org, $allocation);

        $this->assertFalse($proposal->isPostable());
        $this->assertStringContainsString('CHF', implode(' ', $proposal->blockers));
    }

    /** Ein gekoppelter Bankumsatz gilt über die Umbuchung als gebucht. */
    public function test_a_coupled_transaction_counts_as_posted(): void {
        $transaction = $this->transaction(-500.00);

        app(InternalTransferService::class)->record($this->org, [
            'booked_on' => $this->startsOn->addDays(20),
            'amount' => '500.00',
            'from_account' => $this->accounts['bank'],
            'to_account' => $this->accounts['cash'],
            'note' => 'Bankabhebung für die Kasse',
            'from_source' => $transaction,
        ], $this->admin);

        $states = $this->inbox()->bankTransactionStates($this->org, [$transaction->fresh()]);

        $this->assertSame('posted', $states[$transaction->id]['state']);
    }

    /** Ein zweiter Versuch für dieselbe Quelle findet den Vorgang vor. */
    public function test_a_second_attempt_finds_the_existing_transfer(): void {
        $transaction = $this->transaction(-500.00);
        $service = app(InternalTransferService::class);

        $data = [
            'booked_on' => $this->startsOn->addDays(20),
            'amount' => '500.00',
            'from_account' => $this->accounts['bank'],
            'to_account' => $this->accounts['cash'],
            'note' => 'Bankabhebung für die Kasse',
            'from_source' => $transaction,
        ];

        $first = $service->record($this->org, $data, $this->admin);
        $second = $service->record($this->org, $data, $this->admin);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, AccountingTransfer::query()->count());
    }

    /** @return array{0: PaymentAllocation, 1: \App\Models\Accounting\AccountingEntry} */
    private function allocationWithEntry(bool $posted): array {
        $invoice = Invoice::create([
            'organization_id' => $this->org->id,
            'customer_id' => Customer::create(['organization_id' => $this->org->id, 'name' => 'Storno GmbH', 'currency' => 'EUR', 'created_by' => $this->admin->id])->id,
            'number' => 'RE-D3-' . ($posted ? 'P' : 'D'),
            'type' => Invoice::TYPE_INVOICE,
            'category' => Invoice::CATEGORY_SERVICE,
            'status' => InvoiceStatus::Issued,
            'issued_on' => $this->startsOn->addDays(5)->toDateString(),
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '19.00',
            'total' => '119.00',
            'created_by' => $this->admin->id,
        ]);
        $transaction = BankTransaction::factory()->create([
            'organization_id' => $this->org->id,
            'bank_statement_id' => BankStatement::factory()->create(['organization_id' => $this->org->id])->id,
            'booking_date' => $this->startsOn->addDays(15)->toDateString(),
            'amount' => '119.00',
            'currency' => 'EUR',
        ]);
        $allocation = PaymentAllocation::query()->create([
            'organization_id' => $this->org->id,
            'bank_transaction_id' => $transaction->id,
            'allocatable_type' => MorphMap::alias(Invoice::class),
            'allocatable_id' => $invoice->id,
            'amount' => '119.00',
            'kind' => AllocationKind::Payment,
            'confirmed_by_user_id' => $this->admin->id,
            'confirmed_at' => now(),
        ]);

        $journal = app(\App\Services\Accounting\JournalService::class);
        $data = [
            'booked_on' => $this->startsOn->addDays(15),
            'memo' => 'Zahlung RE-D3',
            'source_key' => PostingSourceKind::Payment->keyPrefix() . ':' . $allocation->id,
            'lines' => [
                ['accounting_account_id' => $this->accounts['bank']->id, 'debit' => '119.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['revenue']->id, 'debit' => '0.00', 'credit' => '119.00'],
            ],
        ];
        $entry = $posted
            ? $journal->postDirect($this->org, $data, $this->admin)
            : $journal->draft($this->org, $data, $this->admin);

        return [$allocation, $entry];
    }

    /**
     * D3 (Vollscan 2026-08-23): Aufheben einer Zuordnung OHNE Auth-User
     * (Job/Command) darf die Festbuchung nicht storno-los stehen lassen —
     * der Festschreiber der Ursprungsbuchung steht als Akteur ein.
     */
    public function test_unmatch_without_auth_user_still_reverses_the_posted_entry(): void {
        [$allocation, $entry] = $this->allocationWithEntry(posted: true);

        \Illuminate\Support\Facades\Auth::logout();
        $allocation->delete();

        $entry->refresh();
        $this->assertNotNull($entry->reversed_by_entry_id);
        $reversal = \App\Models\Accounting\AccountingEntry::query()->findOrFail($entry->reversed_by_entry_id);
        $this->assertSame($this->admin->id, $reversal->created_by);
    }

    /** D3: Ein Entwurf verliert beim Aufheben schlicht seine Grundlage — auch ohne Auth-User. */
    public function test_unmatch_without_auth_user_deletes_the_draft_entry(): void {
        [$allocation, $entry] = $this->allocationWithEntry(posted: false);

        \Illuminate\Support\Facades\Auth::logout();
        $allocation->delete();

        $this->assertNull(\App\Models\Accounting\AccountingEntry::query()->find($entry->id));
    }

    /** MVP-1557: Bankgebühr ohne Beleg — Sachkonto und Vorsteuer an Bank, der Umsatz verlässt die offene Liste. */
    public function test_a_transaction_without_document_posts_directly_to_a_ledger_account(): void {
        $chart = app(ChartOfAccountsService::class);
        $fees = $chart->create($this->org, ['number' => '4970', 'name' => 'Nebenkosten des Geldverkehrs', 'type' => AccountType::Expense]);
        $inputTax = $chart->create($this->org, ['number' => '1576', 'name' => 'Vorsteuer 19 %', 'type' => AccountType::Asset]);
        $taxCode = \App\Models\Accounting\AccountingTaxCode::query()->create([
            'organization_id' => $this->org->id,
            'code' => 'VSt19',
            'name' => 'Vorsteuer 19 %',
            'direction' => \App\Enums\Finance\TaxCodeDirection::Input,
            'rate' => '19.00',
            'tax_account_id' => $inputTax->id,
            'valid_from' => $this->startsOn->toDateString(),
            'is_active' => true,
        ]);
        $transaction = $this->transaction(11.90);
        $transaction->forceFill(['direction' => \App\Enums\Finance\TransactionDirection::Debit])->save();

        // Geldkonten gehören zur Umbuchung, nicht hierher.
        try {
            $this->inbox()->postBankTransactionToAccount($this->org, $transaction, $this->accounts['cash'], null, null, 'Falsch', $this->admin);
            $this->fail('Kassenkonto als Sachkonto angenommen.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('account', $exception->errors());
        }

        $this->actingAs($this->admin)->get(route('finance.accounting.inbox.bank-direct.create', $transaction->sqid))
            ->assertOk()->assertSee('4970')->assertDontSee('1000 ');
        $this->actingAs($this->admin)->post(route('finance.accounting.inbox.bank-direct.store', $transaction->sqid), [
            'account' => $fees->sqid,
            'tax_code' => $taxCode->sqid,
            'note' => 'Kontoführung Januar',
        ])->assertSessionHasNoErrors()->assertSessionHas('status', (string) __('accounting.bank_direct.flash.posted'));

        $entry = \App\Models\Accounting\AccountingEntry::query()->where('source_key', $this->inbox()->directKey($transaction))->sole()->load('lines.account');
        $this->assertTrue($entry->status->isPosted());
        $this->assertSame(
            [['1200', '0.00', '11.90'], ['4970', '10.00', '0.00'], ['1576', '1.90', '0.00']],
            $entry->lines->map(fn ($line): array => [$line->account->number, $line->debit->getAmount(), $line->credit->getAmount()])->all(),
        );
        $this->assertSame('1.90', $entry->lines[1]->tax_amount?->getAmount());
        $this->assertSame(\App\Enums\Finance\DirectBookingKind::BankDirect, \App\Enums\Finance\DirectBookingKind::of($entry));

        $transaction->refresh();
        $this->assertSame(\App\Enums\Finance\MatchStatus::Ignored, $transaction->match_status);
        $this->assertSame('posted', $this->inbox()->bankTransactionStates($this->org, [$transaction])[$transaction->id]['state']);

        // Ein zweiter Versuch — auch als Klärungsbuchung gedacht — findet die Buchung vor.
        $again = $this->inbox()->postBankTransactionToAccount($this->org, $transaction, $fees, null, null, 'Nochmal', $this->admin);
        $this->assertSame($entry->id, $again->id);
    }

    /** MVP-1557: Ein Zinsertrag (Geldeingang) bucht Bank an Sachkonto. */
    public function test_an_incoming_transaction_without_document_credits_the_ledger_account(): void {
        $interest = app(ChartOfAccountsService::class)->create($this->org, ['number' => '2650', 'name' => 'Zinserträge', 'type' => AccountType::Income]);
        $transaction = $this->transaction(3.20);

        $entry = $this->inbox()->postBankTransactionToAccount($this->org, $transaction, $interest, null, null, 'Habenzinsen', $this->admin)->load('lines.account');

        $this->assertSame(
            [['1200', '3.20', '0.00'], ['2650', '0.00', '3.20']],
            $entry->lines->map(fn ($line): array => [$line->account->number, $line->debit->getAmount(), $line->credit->getAmount()])->all(),
        );
    }

    /** MVP-1557: Freie Belegsuche — jede offene Rechnung ist wählbar, Teilzahlung, Art und Notiz kommen an. */
    public function test_a_single_transaction_can_be_assigned_to_any_open_invoice(): void {
        $invoice = Invoice::create([
            'organization_id' => $this->org->id,
            'customer_id' => Customer::create(['organization_id' => $this->org->id, 'name' => 'Suchkunde GmbH', 'currency' => 'EUR', 'created_by' => $this->admin->id])->id,
            'number' => 'RE-FREI-1',
            'type' => Invoice::TYPE_INVOICE,
            'category' => Invoice::CATEGORY_SERVICE,
            'status' => InvoiceStatus::Issued,
            'issued_on' => $this->startsOn->addDays(5)->toDateString(),
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '19.00',
            'total' => '119.00',
            'created_by' => $this->admin->id,
        ]);
        $transaction = $this->transaction(50.00);

        $this->actingAs($this->admin)->get(route('finance.reconciliation.assign-form', $transaction->sqid))
            ->assertOk()
            ->assertSee('RE-FREI-1')
            ->assertSee('Suchkunde GmbH');

        $this->actingAs($this->admin)->post(route('finance.reconciliation.confirm', $transaction->sqid), [
            'allocations' => [['target' => 'invoice:' . $invoice->sqid, 'amount' => '50.00', 'note' => 'Abschlag ohne Rechnungsnummer']],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $allocation = PaymentAllocation::query()->where('bank_transaction_id', $transaction->id)->sole();
        $this->assertSame(AllocationKind::Partial, $allocation->kind);
        $this->assertSame('Abschlag ohne Rechnungsnummer', $allocation->note);
        $this->assertSame($invoice->id, $allocation->allocatable_id);

        // Ausdrücklich gewählte Art gewinnt; Rückläufer und Unsinn werden abgewiesen.
        $second = $this->transaction(69.00);
        $this->actingAs($this->admin)->post(route('finance.reconciliation.confirm', $second->sqid), [
            'allocations' => [['target' => 'invoice:' . $invoice->sqid, 'amount' => '69.00', 'kind' => AllocationKind::Chargeback->value]],
        ])->assertSessionHasErrors('allocations.0.kind');
        $this->actingAs($this->admin)->post(route('finance.reconciliation.confirm', $second->sqid), [
            'allocations' => [['target' => 'unsinn', 'amount' => '69.00']],
        ])->assertSessionHasErrors('allocations.0.target');
        $this->actingAs($this->admin)->post(route('finance.reconciliation.confirm', $second->sqid), [
            'allocations' => [['target' => 'invoice:' . $invoice->sqid, 'amount' => '69.00', 'kind' => AllocationKind::Overpayment->value]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(AllocationKind::Overpayment, PaymentAllocation::query()->where('bank_transaction_id', $second->id)->sole()->kind);
    }

    /** Nachzug MVP-1557: Wird der Vier-Augen-Entwurf verworfen, steht der beiseitegelegte Umsatz wieder offen. */
    public function test_discarding_a_bank_direct_draft_reopens_the_transaction(): void {
        \App\Support\Setting::set(PostingInboxService::FOUR_EYES_KEY, true, \App\Settings\SettingScope::Organization, $this->org);
        $fees = app(ChartOfAccountsService::class)->create($this->org, ['number' => '4970', 'name' => 'Nebenkosten des Geldverkehrs', 'type' => AccountType::Expense]);
        $transaction = $this->transaction(4.50);
        $transaction->forceFill(['direction' => \App\Enums\Finance\TransactionDirection::Debit])->save();

        $draft = $this->inbox()->postBankTransactionToAccount($this->org, $transaction, $fees, null, null, 'Kontoführung', $this->admin);
        $this->assertFalse($draft->status->isPosted());
        $this->assertSame(\App\Enums\Finance\MatchStatus::Ignored, $transaction->refresh()->match_status);

        app(\App\Services\Accounting\JournalService::class)->discard($draft, $this->admin);

        $transaction->refresh();
        $this->assertSame(\App\Enums\Finance\MatchStatus::Unmatched, $transaction->match_status);
        $this->assertSame('open', $this->inbox()->bankTransactionStates($this->org, [$transaction])[$transaction->id]['state']);
    }

    /** Ein Umsatz, der schon vorher von Hand beiseitegelegt war, bleibt es auch nach dem Verwerfen. */
    public function test_discarding_keeps_a_transaction_that_was_set_aside_by_hand(): void {
        \App\Support\Setting::set(PostingInboxService::FOUR_EYES_KEY, true, \App\Settings\SettingScope::Organization, $this->org);
        $fees = app(ChartOfAccountsService::class)->create($this->org, ['number' => '4970', 'name' => 'Nebenkosten des Geldverkehrs', 'type' => AccountType::Expense]);
        $transaction = $this->transaction(4.50);
        app(\App\Services\Finance\ReconciliationService::class)->ignore($transaction, $this->admin);

        $draft = $this->inbox()->postBankTransactionToAccount($this->org, $transaction->refresh(), $fees, null, null, 'Kontoführung', $this->admin);
        app(\App\Services\Accounting\JournalService::class)->discard($draft, $this->admin);

        $this->assertSame(\App\Enums\Finance\MatchStatus::Ignored, $transaction->refresh()->match_status);
    }
}
