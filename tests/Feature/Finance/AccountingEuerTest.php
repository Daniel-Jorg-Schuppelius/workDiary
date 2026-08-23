<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingEuerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AccountType, EuerCategory, ProfitDetermination};
use App\Models\Accounting\AccountingAccount;
use App\Models\{Customer, Invoice, Organization, User};
use App\Services\Accounting\{AccountingProfileService, AccountingReportService, ChartOfAccountsService, FiscalYearService, JournalService};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EÜR-Feinschnitt (Feature 125, MVP-680).
 *
 * Abnahme: Beschränkt abziehbare Ausgaben gehen anteilig ein, Konten ohne
 * Zuordnung stehen sichtbar in den ungeklärten Fällen, und Abschreibungen
 * kommen aus dem Journal statt aus dem Zahlungsstrom.
 */
class AccountingEuerTest extends TestCase {
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
            'profit_determination' => ProfitDetermination::Euer,
            'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1,
            'starts_on' => $this->startsOn,
            'note' => null,
        ]);
        app(FiscalYearService::class)->create($this->org, $this->startsOn);
        app(AccountingProfileService::class)->activateLocal($this->org, $this->admin);

        $chart = app(ChartOfAccountsService::class);
        $this->accounts['bank'] = $chart->create($this->org, ['number' => '1200', 'name' => 'Bank', 'type' => AccountType::Asset, 'is_bank' => true]);
        $this->accounts['receivable'] = $chart->create($this->org, ['number' => '1400', 'name' => 'Forderungen', 'type' => AccountType::Asset, 'is_open_item' => true]);
        $this->accounts['revenue'] = $chart->create($this->org, [
            'number' => '8400', 'name' => 'Erlöse 19 %', 'type' => AccountType::Income,
            'euer_category' => EuerCategory::Income,
        ]);
        $this->accounts['vat'] = $chart->create($this->org, [
            'number' => '1776', 'name' => 'Umsatzsteuer 19 %', 'type' => AccountType::Liability,
            'euer_category' => EuerCategory::IncomeVat,
        ]);
        $this->accounts['hospitality'] = $chart->create($this->org, [
            'number' => '4650', 'name' => 'Bewirtungskosten', 'type' => AccountType::Expense,
            'euer_category' => EuerCategory::LimitedDeductible, 'deductible_percent' => '70',
        ]);
        $this->accounts['depreciation'] = $chart->create($this->org, [
            'number' => '4830', 'name' => 'Abschreibungen', 'type' => AccountType::Expense,
            'euer_category' => EuerCategory::Depreciation,
        ]);
        $this->accounts['asset'] = $chart->create($this->org, ['number' => '0410', 'name' => 'Betriebsausstattung', 'type' => AccountType::Asset]);
        $this->accounts['unassigned'] = $chart->create($this->org, ['number' => '4900', 'name' => 'Sonstige Aufwendungen', 'type' => AccountType::Expense]);
    }

    /** @return array<string, mixed> */
    private function preview(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array {
        return app(AccountingReportService::class)->euerPreview(
            $this->org,
            $from ?? $this->startsOn,
            $to ?? $this->startsOn->endOfYear(),
        );
    }

    /** Direkt bezahlter Beleg: Aufwand an Bank, ohne offenen Posten. */
    private function cashExpense(AccountingAccount $account, string $amount, ?CarbonImmutable $day = null): void {
        app(JournalService::class)->postDirect($this->org, [
            'booked_on' => $day ?? $this->startsOn->addDays(20),
            'memo' => 'Zahlung ' . $account->number,
            'source_key' => 'euer-test:' . $account->number . ':' . uniqid('', true),
            'lines' => [
                ['accounting_account_id' => $account->id, 'debit' => $amount, 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['bank']->id, 'debit' => '0.00', 'credit' => $amount],
            ],
        ], $this->admin);
    }

    /** @return array<string, mixed>|null */
    private function row(array $preview, EuerCategory $category): ?array {
        foreach ($preview['rows'] as $row) {
            if ($row['category'] === $category) {
                return $row;
            }
        }

        return null;
    }

    /** § 4 Abs. 5 EStG: 70 % gehen ein, der Rest steht ausgewiesen daneben. */
    public function test_limited_deductible_expenses_enter_with_their_share(): void {
        $this->cashExpense($this->accounts['hospitality'], '100.00');

        $preview = $this->preview();
        $row = $this->row($preview, EuerCategory::LimitedDeductible);

        $this->assertNotNull($row);
        $this->assertSame('100.00', $row['gross']);
        $this->assertSame('70.00', $row['deductible']);
        $this->assertSame('30.00', $row['not_deductible']);
        $this->assertSame('70.00', $preview['expense']);
        $this->assertSame('30.00', $preview['not_deductible']);
    }

    /** Ein Konto ohne Zuordnung ist ein Klärungsfall, keine stille Null. */
    public function test_an_account_without_a_category_shows_up_as_unresolved(): void {
        $this->cashExpense($this->accounts['unassigned'], '50.00');

        $preview = $this->preview();

        $this->assertSame('0.00', $preview['expense']);
        $this->assertNotEmpty($preview['unclear']);
        $this->assertStringContainsString('4900', implode(' ', $preview['unclear']));
    }

    /** Abschreibungen stammen aus dem Journal, nicht aus einer Zahlung. */
    public function test_depreciation_comes_from_the_journal_and_is_marked_manual(): void {
        app(JournalService::class)->postDirect($this->org, [
            'booked_on' => $this->startsOn->addMonths(11),
            'memo' => 'AfA 2026',
            'source_key' => 'euer-test:afa',
            'lines' => [
                ['accounting_account_id' => $this->accounts['depreciation']->id, 'debit' => '1200.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['asset']->id, 'debit' => '0.00', 'credit' => '1200.00'],
            ],
        ], $this->admin);

        $row = $this->row($this->preview(), EuerCategory::Depreciation);

        $this->assertNotNull($row);
        $this->assertSame('1200.00', $row['deductible']);
        $this->assertTrue($row['manual']);
    }

    /** Eine Teilzahlung trägt die Kategorien des Belegs nur anteilig. */
    public function test_a_partial_payment_carries_a_proportional_share(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);
        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => $customer->id,
            'number' => 'RE-9',
            'status' => Invoice::STATUS_ISSUED,
            'issued_on' => $this->startsOn->addDays(5)->toDateString(),
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '19.00',
            'total' => '119.00',
        ]);

        app(JournalService::class)->postDirect($this->org, [
            'booked_on' => $this->startsOn->addDays(5),
            'document_on' => $this->startsOn->addDays(5),
            'memo' => 'Ausgangsrechnung',
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
            'source_key' => 'euer-test:invoice',
            'lines' => [
                ['accounting_account_id' => $this->accounts['receivable']->id, 'debit' => '119.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['revenue']->id, 'debit' => '0.00', 'credit' => '100.00'],
                ['accounting_account_id' => $this->accounts['vat']->id, 'debit' => '0.00', 'credit' => '19.00'],
            ],
        ], $this->admin);

        app(JournalService::class)->postDirect($this->org, [
            'booked_on' => $this->startsOn->addMonths(3),
            'memo' => 'Teilzahlung',
            'source_key' => 'euer-test:payment',
            'snapshot' => ['settles_source_type' => Invoice::class, 'settles_source_id' => $invoice->id],
            'lines' => [
                ['accounting_account_id' => $this->accounts['bank']->id, 'debit' => '59.50', 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['receivable']->id, 'debit' => '0.00', 'credit' => '59.50'],
            ],
        ], $this->admin);

        $preview = $this->preview();

        $this->assertSame('50.00', $this->row($preview, EuerCategory::Income)['gross']);
        $this->assertSame('9.50', $this->row($preview, EuerCategory::IncomeVat)['gross']);
        $this->assertSame('59.50', $preview['income']);
    }

    /** Der offene Rest der Rechnung zählt erst, wenn er bezahlt ist. */
    public function test_an_unpaid_invoice_does_not_enter_the_preview(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);
        app(JournalService::class)->postDirect($this->org, [
            'booked_on' => $this->startsOn->addDays(5),
            'memo' => 'Ausgangsrechnung',
            'source_type' => Customer::class,
            'source_id' => $customer->id,
            'source_key' => 'euer-test:open-invoice',
            'lines' => [
                ['accounting_account_id' => $this->accounts['receivable']->id, 'debit' => '119.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['revenue']->id, 'debit' => '0.00', 'credit' => '100.00'],
                ['accounting_account_id' => $this->accounts['vat']->id, 'debit' => '0.00', 'credit' => '19.00'],
            ],
        ], $this->admin);

        $preview = $this->preview();

        $this->assertSame('0.00', $preview['income']);
        $this->assertNotEmpty($preview['unclear']);
    }

    /**
     * § 11 Abs. 1 S. 2 EStG: Zahlungen im Fenster 22.12.–10.01. mit Beleg aus
     * dem Nachbarjahr werden gemeldet — umgebucht wird bewusst nichts.
     */
    public function test_payments_around_the_turn_of_the_year_are_flagged(): void {
        app(FiscalYearService::class)->create($this->org, $this->startsOn->addYear());

        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);
        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => $customer->id,
            'number' => 'RE-12',
            'status' => Invoice::STATUS_ISSUED,
            'issued_on' => '2026-12-28',
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '19.00',
            'total' => '119.00',
        ]);

        app(JournalService::class)->postDirect($this->org, [
            'booked_on' => CarbonImmutable::create(2026, 12, 28),
            'document_on' => CarbonImmutable::create(2026, 12, 28),
            'memo' => 'Dezemberrechnung',
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
            'source_key' => 'euer-test:december',
            'lines' => [
                ['accounting_account_id' => $this->accounts['receivable']->id, 'debit' => '119.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['revenue']->id, 'debit' => '0.00', 'credit' => '100.00'],
                ['accounting_account_id' => $this->accounts['vat']->id, 'debit' => '0.00', 'credit' => '19.00'],
            ],
        ], $this->admin);

        app(JournalService::class)->postDirect($this->org, [
            'booked_on' => CarbonImmutable::create(2027, 1, 5),
            'memo' => 'Zahlung im Januar',
            'source_key' => 'euer-test:january-payment',
            'snapshot' => ['settles_source_type' => Invoice::class, 'settles_source_id' => $invoice->id],
            'lines' => [
                ['accounting_account_id' => $this->accounts['bank']->id, 'debit' => '119.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['receivable']->id, 'debit' => '0.00', 'credit' => '119.00'],
            ],
        ], $this->admin);

        $quality = app(AccountingReportService::class)->dataQuality(
            $this->org,
            CarbonImmutable::create(2027, 1, 1),
            CarbonImmutable::create(2027, 1, 31),
        );

        $this->assertSame(1, $quality['ten_day_cases']);
        $this->assertNotEmpty($quality['findings']);
    }

    /** Der abziehbare Anteil wirkt nur im Bericht, nie im Journal. */
    public function test_the_deductible_share_never_touches_the_journal(): void {
        $this->cashExpense($this->accounts['hospitality'], '100.00');

        $balance = app(AccountingReportService::class)->trialBalance($this->org, $this->startsOn, $this->startsOn->endOfYear());
        $row = collect($balance['rows'])->firstWhere('account.number', '4650');

        $this->assertNotNull($row);
        $this->assertSame('100.00', $row['debit']);
    }
}
