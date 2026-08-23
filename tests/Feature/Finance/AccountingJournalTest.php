<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingJournalTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AccountType, AccountingEntryStatus, ProfitDetermination};
use App\Models\Accounting\{AccountingAccount, AccountingEntry, AccountingEvent};
use App\Models\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, AccountingSovereigntyException, ChartOfAccountsService, FiscalYearService, JournalService};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

/**
 * Buchungskern (Feature 125, MVP-672).
 *
 * Zwei Zusagen: Keine unausgeglichene oder periodenfremde Buchung wird
 * festgeschrieben, und eine Festbuchung ist nur über eine nachgewiesene
 * Gegenbuchung korrigierbar.
 */
class AccountingJournalTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private AccountingAccount $bank;

    private AccountingAccount $revenue;

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

        $accounts = app(ChartOfAccountsService::class);
        $this->bank = $accounts->create($this->org, ['number' => '1200', 'name' => 'Bank', 'type' => AccountType::Asset, 'is_bank' => true]);
        $this->revenue = $accounts->create($this->org, ['number' => '8400', 'name' => 'Erlöse 19 %', 'type' => AccountType::Income]);
    }

    private function journal(): JournalService {
        return app(JournalService::class);
    }

    /** @param list<array<string, mixed>>|null $lines */
    private function entryData(?array $lines = null, ?CarbonImmutable $bookedOn = null, ?string $sourceKey = null): array {
        return [
            'booked_on' => $bookedOn ?? $this->startsOn->addMonth(),
            'memo' => 'Zahlungseingang Rechnung 2026-001',
            'source_key' => $sourceKey,
            'lines' => $lines ?? [
                ['accounting_account_id' => $this->bank->id, 'debit' => '119.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->revenue->id, 'debit' => '0.00', 'credit' => '119.00'],
            ],
        ];
    }

    public function test_posting_assigns_a_gapless_journal_number(): void {
        $first = $this->journal()->postDirect($this->org, $this->entryData(), $this->admin);
        $second = $this->journal()->postDirect($this->org, $this->entryData(), $this->admin);

        $this->assertSame(1, $first->journal_no);
        $this->assertSame(2, $second->journal_no);
        $this->assertSame(AccountingEntryStatus::Posted, $first->status);
        $this->assertNotNull($first->posted_at);
    }

    public function test_unbalanced_entry_cannot_be_posted(): void {
        $entry = $this->journal()->draft($this->org, $this->entryData([
            ['accounting_account_id' => $this->bank->id, 'debit' => '119.00', 'credit' => '0.00'],
            ['accounting_account_id' => $this->revenue->id, 'debit' => '0.00', 'credit' => '100.00'],
        ]), $this->admin);

        $this->expectException(ValidationException::class);
        $this->journal()->post($entry, $this->admin);
    }

    public function test_a_line_carries_either_debit_or_credit(): void {
        $entry = $this->journal()->draft($this->org, $this->entryData([
            ['accounting_account_id' => $this->bank->id, 'debit' => '119.00', 'credit' => '119.00'],
            ['accounting_account_id' => $this->revenue->id, 'debit' => '0.00', 'credit' => '119.00'],
        ]), $this->admin);

        $this->expectException(ValidationException::class);
        $this->journal()->post($entry, $this->admin);
    }

    public function test_single_line_entry_cannot_be_posted(): void {
        $entry = $this->journal()->draft($this->org, $this->entryData([
            ['accounting_account_id' => $this->bank->id, 'debit' => '119.00', 'credit' => '0.00'],
        ]), $this->admin);

        $this->expectException(ValidationException::class);
        $this->journal()->post($entry, $this->admin);
    }

    /** Ohne Periode gibt es keinen Ort für die Buchung. */
    public function test_date_outside_any_fiscal_year_is_rejected(): void {
        $this->expectException(ValidationException::class);
        $this->journal()->draft($this->org, $this->entryData(null, CarbonImmutable::create(2030, 5, 1)), $this->admin);
    }

    /** Vor dem Stichtag führt die Organisation kein lokales Hauptbuch. */
    public function test_posting_before_the_sovereignty_start_is_refused(): void {
        app(FiscalYearService::class)->create($this->org, $this->startsOn->subYear());

        $entry = $this->journal()->draft($this->org, $this->entryData(null, $this->startsOn->subMonths(2)), $this->admin);

        $this->expectException(AccountingSovereigntyException::class);
        $this->journal()->post($entry, $this->admin);
    }

    public function test_inactive_account_cannot_be_posted_on(): void {
        app(ChartOfAccountsService::class)->deactivate($this->revenue);

        $entry = $this->journal()->draft($this->org, $this->entryData(), $this->admin);

        $this->expectException(ValidationException::class);
        $this->journal()->post($entry, $this->admin);
    }

    public function test_posted_entry_is_immutable(): void {
        $entry = $this->journal()->postDirect($this->org, $this->entryData(), $this->admin);

        $this->expectException(RuntimeException::class);
        $entry->update(['memo' => 'nachträglich geändert']);
    }

    public function test_posted_entry_cannot_be_deleted(): void {
        $entry = $this->journal()->postDirect($this->org, $this->entryData(), $this->admin);

        $this->expectException(RuntimeException::class);
        $entry->delete();
    }

    public function test_lines_of_a_posted_entry_are_immutable(): void {
        $entry = $this->journal()->postDirect($this->org, $this->entryData(), $this->admin);
        $line = $entry->lines()->first();
        $this->assertNotNull($line);

        $this->expectException(RuntimeException::class);
        $line->update(['memo' => 'nachträglich']);
    }

    public function test_reversal_creates_a_mirrored_counter_entry(): void {
        $entry = $this->journal()->postDirect($this->org, $this->entryData(), $this->admin);

        $reversal = $this->journal()->reverse($entry, 'Doppelt erfasst', $this->admin);

        $this->assertSame(AccountingEntryStatus::Posted, $reversal->status);
        $this->assertSame($entry->id, $reversal->reverses_entry_id);
        $this->assertSame(AccountingEntryStatus::Reversed, $entry->refresh()->status);
        $this->assertSame($reversal->id, $entry->reversed_by_entry_id);
        $this->assertSame('Doppelt erfasst', $entry->reversal_reason);

        // Gespiegelt: aus Soll wird Haben, die Summe der beiden Buchungen ist null.
        $reversalBankLine = $reversal->lines->firstWhere('accounting_account_id', $this->bank->id);
        $this->assertNotNull($reversalBankLine);
        $this->assertSame('119.00', $reversalBankLine->credit?->getAmount());
        $this->assertSame('0.00', $reversalBankLine->debit?->getAmount());
    }

    public function test_reversal_requires_a_reason(): void {
        $entry = $this->journal()->postDirect($this->org, $this->entryData(), $this->admin);

        $this->expectException(ValidationException::class);
        $this->journal()->reverse($entry, '   ', $this->admin);
    }

    public function test_only_posted_entries_can_be_reversed(): void {
        $entry = $this->journal()->draft($this->org, $this->entryData(), $this->admin);

        $this->expectException(ValidationException::class);
        $this->journal()->reverse($entry, 'Versehen', $this->admin);
    }

    /** Der Nachweis hängt an der Kette, nicht am Statusfeld. */
    public function test_posting_and_reversal_are_recorded_in_the_hash_chain(): void {
        $entry = $this->journal()->postDirect($this->org, $this->entryData(), $this->admin);
        $this->journal()->reverse($entry, 'Falscher Betrag', $this->admin);

        $events = AccountingEvent::query()->orderBy('id')->get();
        $this->assertSame(
            ['accounting.entry_posted', 'accounting.entry_posted', 'accounting.entry_reversed'],
            $events->pluck('event')->all(),
        );
        $this->assertNull($events->first()?->prev_hash);
        $this->assertSame($events[0]->hash, $events[1]->prev_hash);
        $this->assertSame($events[1]->hash, $events[2]->prev_hash);
    }

    public function test_accounting_events_are_append_only(): void {
        $this->journal()->postDirect($this->org, $this->entryData(), $this->admin);
        $event = AccountingEvent::query()->firstOrFail();

        $this->expectException(RuntimeException::class);
        $event->update(['event' => 'manipuliert']);
    }

    /** Dieselbe Quelle darf nie zweimal im Journal landen. */
    public function test_the_same_source_key_yields_the_same_entry(): void {
        $first = $this->journal()->postDirect($this->org, $this->entryData(null, null, 'invoice:42'), $this->admin);
        $second = $this->journal()->postDirect($this->org, $this->entryData(null, null, 'invoice:42'), $this->admin);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, AccountingEntry::query()->where('source_key', 'invoice:42')->count());
    }

    /** Nach einem Storno darf die Quelle neu gebucht werden — mit eigenem Schlüssel. */
    public function test_a_source_can_be_booked_again_after_a_reversal(): void {
        $first = $this->journal()->postDirect($this->org, $this->entryData(null, null, 'invoice:43'), $this->admin);
        $this->journal()->reverse($first, 'Korrektur', $this->admin);

        $again = $this->journal()->postDirect($this->org, $this->entryData(null, null, 'invoice:43'), $this->admin);

        $this->assertNotSame($first->id, $again->id);
        $this->assertSame('invoice:43#2', $again->source_key);
    }

    public function test_opening_balance_posts_on_the_start_date(): void {
        $equity = app(ChartOfAccountsService::class)->create($this->org, [
            'number' => '9000', 'name' => 'Saldenvorträge', 'type' => AccountType::Equity,
        ]);

        $entry = $this->journal()->openingBalance($this->org, [
            ['accounting_account_id' => $this->bank->id, 'debit' => '5000.00', 'credit' => '0.00'],
            ['accounting_account_id' => $equity->id, 'debit' => '0.00', 'credit' => '5000.00'],
        ], $this->admin);

        $this->assertSame($this->startsOn->toDateString(), $entry->booked_on->toDateString());
        $this->assertSame('opening_balance', $entry->source_key);
        $this->assertSame(AccountingEntryStatus::Posted, $entry->status);
    }

    public function test_account_in_use_cannot_be_deleted_but_can_be_deactivated(): void {
        $this->journal()->postDirect($this->org, $this->entryData(), $this->admin);
        $accounts = app(ChartOfAccountsService::class);

        $this->assertTrue($accounts->isInUse($this->bank));
        $this->assertFalse($accounts->deactivate($this->bank)->is_active);

        $this->expectException(ValidationException::class);
        $accounts->delete($this->bank->refresh());
    }

    public function test_chart_of_accounts_csv_import_creates_and_updates(): void {
        $path = tempnam(sys_get_temp_dir(), 'coa') . '.csv';
        file_put_contents($path, implode("\r\n", [
            'number;name;type;normal_balance;is_open_item;datev_account',
            '1000;Kasse;asset;debit;0;1000',
            '1200;Bank (neu benannt);asset;debit;0;1200',
            ';Ohne Nummer;asset;debit;0;',
        ]) . "\r\n");

        $result = app(ChartOfAccountsService::class)->importCsv($this->org, $path);
        unlink($path);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, $result['updated']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame('Bank (neu benannt)', $this->bank->refresh()->name);
    }

    public function test_journal_pages_require_permissions(): void {
        $member = User::factory()->create(['organization_id' => $this->org->id]);
        $entry = $this->journal()->postDirect($this->org, $this->entryData(), $this->admin);

        $this->actingAs($member)->get(route('finance.accounting.journal.index'))->assertForbidden();
        $this->actingAs($member)->post(route('finance.accounting.journal.post', $entry))->assertForbidden();

        $this->actingAs($this->admin)->get(route('finance.accounting.journal.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('finance.accounting.journal.show', $entry))->assertOk();
        $this->actingAs($this->admin)->get(route('finance.accounting.accounts.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('finance.accounting.journal.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('finance.accounting.accounts.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('finance.accounting.journal.reverse-form', $entry))->assertOk();
    }

    public function test_handwritten_entry_over_http_posts_immediately(): void {
        $this->actingAs($this->admin)->post(route('finance.accounting.journal.store'), [
            'booked_on' => $this->startsOn->addMonth()->toDateString(),
            'memo' => 'Bareinzahlung',
            'debit_account' => $this->bank->sqid,
            'credit_account' => $this->revenue->sqid,
            'amount' => '250.00',
            'post' => '1',
        ])->assertRedirect();

        $entry = AccountingEntry::query()->latest('id')->firstOrFail();
        $this->assertSame(AccountingEntryStatus::Posted, $entry->status);
        $this->assertSame('250.00', $entry->debitTotal()->getAmount());
    }
}
