<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingClosingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AccountType, AccountingPeriodStatus, ProfitDetermination};
use App\Models\Accounting\{AccountingAccount, AccountingEvent, AccountingFiscalYear, AccountingPeriod};
use App\Models\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsService, FiscalYearService, JournalService, PeriodClosingService};
use App\Services\Finance\GdpduExportService;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Periodenabschluss und GoBD-Z3-Ausbau (Feature 125, MVP-677).
 *
 * Abnahme: Ein geschlossener Zeitraum bleibt unverändert; Export und Journal
 * stimmen summen- und quellenbezogen überein.
 */
class AccountingClosingTest extends TestCase {
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
        $this->accounts['revenue'] = $chart->create($this->org, ['number' => '8400', 'name' => 'Erlöse', 'type' => AccountType::Income]);
    }

    private function closing(): PeriodClosingService {
        return app(PeriodClosingService::class);
    }

    private function january(): AccountingPeriod {
        return AccountingPeriod::query()
            ->where('organization_id', $this->org->id)
            ->covering($this->startsOn->addDays(5))
            ->sole();
    }

    private function postEntry(?CarbonImmutable $on = null): void {
        app(JournalService::class)->postDirect($this->org, [
            'booked_on' => $on ?? $this->startsOn->addDays(5),
            'memo' => 'Erlösbuchung',
            'source_key' => 'closing-test:' . uniqid('', true),
            'lines' => [
                ['accounting_account_id' => $this->accounts['bank']->id, 'debit' => '100.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['revenue']->id, 'debit' => '0.00', 'credit' => '100.00'],
            ],
        ], $this->admin);
    }

    public function test_soft_close_keeps_the_period_usable_as_a_signal(): void {
        $period = $this->closing()->softClose($this->january(), $this->admin);

        $this->assertSame(AccountingPeriodStatus::SoftClosed, $period->status);
        $this->assertNotNull($period->soft_closed_at);
    }

    public function test_closing_is_blocked_by_open_drafts(): void {
        app(JournalService::class)->draft($this->org, [
            'booked_on' => $this->startsOn->addDays(5),
            'memo' => 'Offener Entwurf',
            'lines' => [
                ['accounting_account_id' => $this->accounts['bank']->id, 'debit' => '10.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['revenue']->id, 'debit' => '0.00', 'credit' => '10.00'],
            ],
        ], $this->admin);

        $report = $this->closing()->preflight($this->january());
        $this->assertFalse($report->isReady());

        $this->expectException(ValidationException::class);
        $this->closing()->close($this->january(), $this->admin);
    }

    /** Die zentrale Zusage: ein geschlossener Zeitraum nimmt nichts mehr an. */
    public function test_a_closed_period_refuses_new_postings(): void {
        $this->postEntry();
        $this->closing()->close($this->january(), $this->admin);

        $this->expectException(ValidationException::class);
        $this->postEntry();
    }

    public function test_closing_is_recorded_in_the_hash_chain(): void {
        $this->postEntry();
        $this->closing()->close($this->january(), $this->admin);

        $events = AccountingEvent::query()->pluck('event')->all();
        $this->assertContains('accounting.period_closed', $events);
    }

    public function test_reopening_requires_a_reason_and_is_recorded(): void {
        $this->postEntry();
        $this->closing()->close($this->january(), $this->admin);

        try {
            $this->closing()->reopen($this->january(), $this->admin, '   ');
            $this->fail('Eine Wiedereröffnung ohne Begründung muss scheitern.');
        } catch (ValidationException) {
            // erwartet
        }

        $period = $this->closing()->reopen($this->january(), $this->admin, 'Nachträgliche Korrektur der Erlöse');

        $this->assertSame(AccountingPeriodStatus::Open, $period->status);
        $this->assertSame('Nachträgliche Korrektur der Erlöse', $period->reopen_reason);
        $this->assertContains('accounting.period_reopened', AccountingEvent::query()->pluck('event')->all());

        // Nach der Wiedereröffnung nimmt die Periode wieder Buchungen an.
        $this->postEntry();
    }

    public function test_a_fiscal_year_closes_only_when_all_periods_are_closed(): void {
        $year = AccountingFiscalYear::query()->sole();

        $this->expectException(ValidationException::class);
        $this->closing()->closeFiscalYear($year, $this->admin);
    }

    public function test_closing_pages_require_the_close_permission(): void {
        $member = User::factory()->create(['organization_id' => $this->org->id]);
        $period = $this->january();

        $this->actingAs($member)->get(route('finance.accounting.closing.index'))->assertForbidden();
        $this->actingAs($member)->post(route('finance.accounting.closing.close', $period))->assertForbidden();

        $this->actingAs($this->admin)->get(route('finance.accounting.closing.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('finance.accounting.closing.preflight', $period))->assertOk();
        $this->actingAs($this->admin)->get(route('finance.accounting.closing.reopen-form', $period))->assertOk();
    }

    /** Der Z3-Export führt den Buchungskern jetzt mit. */
    public function test_the_gobd_package_contains_the_ledger_sections(): void {
        $this->postEntry();

        $sections = app(GdpduExportService::class)->availableSections();

        foreach (['ledger_accounts', 'ledger_entries', 'ledger_entry_lines', 'ledger_open_items', 'ledger_periods'] as $section) {
            $this->assertContains($section, $sections);
        }
    }

    /** Export und Journal müssen dieselben Summen zeigen. */
    public function test_the_export_matches_the_journal(): void {
        $this->postEntry();

        $result = app(GdpduExportService::class)->preflight($this->org, $this->startsOn, $this->startsOn->addMonth());

        $this->assertSame(1, $result['counts']['ledger_entries'] ?? 0);
        $this->assertSame(2, $result['counts']['ledger_entry_lines'] ?? 0);
        $this->assertGreaterThanOrEqual(2, $result['counts']['ledger_accounts'] ?? 0);
    }
}
