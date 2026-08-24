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
use App\Models\Accounting\{AccountingAccount, AccountingPostingRule, AccountingTransfer};
use App\Models\{Customer, Invoice, Organization, User};
use App\Models\Finance\{BankStatement, BankTransaction, PaymentAllocation};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsService, FiscalYearService, InternalTransferService};
use App\Services\Accounting\Posting\{PostingInboxService, PostingSourceRegistry};
use App\Services\Accounting\Reports\DataQualityBuilder;
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
            'status' => Invoice::STATUS_ISSUED,
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
            'allocatable_type' => Invoice::class,
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
            'status' => Invoice::STATUS_ISSUED,
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
            'allocatable_type' => Invoice::class,
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
}
