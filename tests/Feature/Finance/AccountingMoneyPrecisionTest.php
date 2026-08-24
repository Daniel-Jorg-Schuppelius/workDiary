<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingMoneyPrecisionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AccountType, PostingAccountRole, PostingSourceKind, ProfitDetermination};
use App\Models\Accounting\{AccountingAccount, AccountingPostingRule};
use App\Models\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsService, FiscalYearService, JournalService, OpeningBalanceImportService};
use App\Services\Accounting\Filing\VatSpecialPrepaymentService;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Geldbeträge rechnen dezimal-exakt statt mit floats (Vollscan 2026-08-23, C1).
 *
 * Die beiden Fälle sind gezielt so gewählt, dass die frühere Float-Rechnung
 * nachweislich falsch lag: ein Tie-Fall x.xx5, dessen Float-Darstellung knapp
 * unter der Hälfte liegt (1100,01 € × 12 ÷ 8 = 1650,015 → float rundet ab,
 * kaufmännisch korrekt ist 1650,02), und eine Akkumulation vieler
 * Cent-Beträge im 0,1+0,2-Muster, die als Float-Summe driften würde.
 */
class AccountingMoneyPrecisionTest extends TestCase {
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

        // Start im Mai: Das Vorjahr 2026 zählt 8 aktive Monate — die
        // Hochrechnung nach § 47 Abs. 3 UStDV multipliziert also mit 12/8.
        $this->startsOn = CarbonImmutable::create(2026, 5, 1);
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
        $this->accounts['equity'] = $chart->create($this->org, ['number' => '9000', 'name' => 'Saldenvorträge', 'type' => AccountType::Equity]);
        $this->accounts['receivable'] = $chart->create($this->org, ['number' => '1400', 'name' => 'Forderungen', 'type' => AccountType::Asset, 'is_open_item' => true]);
        $this->accounts['revenue'] = $chart->create($this->org, ['number' => '8400', 'name' => 'Erlöse', 'type' => AccountType::Income]);
        $this->accounts['vat'] = $chart->create($this->org, ['number' => '1776', 'name' => 'Umsatzsteuer 19 %', 'type' => AccountType::Liability]);

        AccountingPostingRule::query()->create([
            'organization_id' => $this->org->id,
            'source_kind' => PostingSourceKind::SalesInvoice,
            'role' => PostingAccountRole::TaxOutput,
            'accounting_account_id' => $this->accounts['vat']->id,
            'priority' => 100,
            'version' => 1,
            'valid_from' => $this->startsOn->toDateString(),
            'is_active' => true,
        ]);
    }

    private function csv(string $content): string {
        $path = tempnam(sys_get_temp_dir(), 'precision') . '.csv';
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Tie-Fall und Cent-Akkumulation im Startsalden-Probelauf: 1.0050 rundet
     * kaufmännisch auf 1.01 (float: 1.00), und 100 × 0.01 summiert exakt.
     * (Vier Nachkommastellen, weil der CSV-Parser x.xx5 als Tausenderpunkt
     * deuten würde — das ist Format-, nicht Rundungssache.)
     */
    public function test_opening_balance_dry_run_rounds_ties_half_up_and_sums_cents_exactly(): void {
        $rows = "account;debit;credit\r\n1200;1.0050;0\r\n1200;0.10;0\r\n1200;0.20;0\r\n";
        for ($i = 0; $i < 100; $i++) {
            $rows .= "1200;0.01;0\r\n";
        }
        $rows .= "9000;0;2.31\r\n";

        $result = app(OpeningBalanceImportService::class)->dryRun($this->org, $this->csv($rows));

        $this->assertSame([], $result['errors']);
        $this->assertSame('1.01', $result['lines'][0]['debit']);
        $this->assertSame('2.31', $result['debit']);
        $this->assertSame('2.31', $result['credit']);
        $this->assertTrue($result['balanced']);
    }

    /**
     * 1100,01 € Vorjahressteuer × 12 ÷ 8 Monate = exakt 1650,015 € — der
     * Tie rundet kaufmännisch auf 1650,02 (die Float-Darstellung liegt knapp
     * unter der Hälfte und ergab 1650,01). Das Elftel davon: 150,00 €.
     */
    public function test_the_special_prepayment_annualisation_rounds_the_tie_half_up(): void {
        app(JournalService::class)->postDirect($this->org, [
            'booked_on' => CarbonImmutable::create(2026, 6, 15),
            'memo' => 'Erlös Juni',
            'source_key' => 'precision-test:sale',
            'lines' => [
                ['accounting_account_id' => $this->accounts['receivable']->id, 'debit' => '6889.54', 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['revenue']->id, 'debit' => '0.00', 'credit' => '5789.53'],
                ['accounting_account_id' => $this->accounts['vat']->id, 'debit' => '0.00', 'credit' => '1100.01'],
            ],
        ], $this->admin);

        $calculation = app(VatSpecialPrepaymentService::class)->calculate($this->org, 2027);

        $this->assertSame('1100.01', $calculation['prior_year_tax']);
        $this->assertSame(8, $calculation['months_active']);
        $this->assertSame('1650.02', $calculation['annualised']);
        $this->assertSame('150.00', $calculation['amount']);
    }
}
