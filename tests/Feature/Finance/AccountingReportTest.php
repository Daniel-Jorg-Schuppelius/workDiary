<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AccountType, ProfitDetermination};
use App\Models\Accounting\AccountingAccount;
use App\Models\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsService, FiscalYearService, JournalService};
use App\Services\Accounting\Reports\{AccountLedgerBuilder, DataQualityBuilder, EuerPreviewBuilder, ExportContextBuilder, LiquidityBuilder, ProfitAndLossBuilder, TrialBalanceBuilder, VatPreviewBuilder};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Finanzberichte (Feature 125, MVP-676).
 *
 * Abnahme: Jede Kennzahl ist bis zum Original erklärbar; Liste, Kennzahl und
 * Export liefern bei identischen Filtern dieselbe Grundgesamtheit.
 */
class AccountingReportTest extends TestCase {
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
        $this->accounts['expense'] = $chart->create($this->org, ['number' => '6300', 'name' => 'Aufwand', 'type' => AccountType::Expense]);
        $this->accounts['vat'] = $chart->create($this->org, ['number' => '1776', 'name' => 'Umsatzsteuer', 'type' => AccountType::Liability]);

        // Das Umsatzsteuerkonto ist als solches benannt — sonst wäre es für
        // die Auswertung ein Passivkonto wie jedes andere.
        \App\Models\Accounting\AccountingPostingRule::query()->create([
            'organization_id' => $this->org->id,
            'source_kind' => \App\Enums\Finance\PostingSourceKind::SalesInvoice,
            'role' => \App\Enums\Finance\PostingAccountRole::TaxOutput,
            'accounting_account_id' => $this->accounts['vat']->id,
            'priority' => 100,
            'version' => 1,
            'valid_from' => $this->startsOn->toDateString(),
            'is_active' => true,
        ]);

        // Zwei Festbuchungen im Januar: Erlös mit Steuer, Aufwand.
        $this->postEntry([
            ['accounting_account_id' => $this->accounts['bank']->id, 'debit' => '119.00', 'credit' => '0.00'],
            ['accounting_account_id' => $this->accounts['revenue']->id, 'debit' => '0.00', 'credit' => '100.00'],
            ['accounting_account_id' => $this->accounts['vat']->id, 'debit' => '0.00', 'credit' => '19.00'],
        ], 'Erlösbuchung');

        $this->postEntry([
            ['accounting_account_id' => $this->accounts['expense']->id, 'debit' => '40.00', 'credit' => '0.00'],
            ['accounting_account_id' => $this->accounts['bank']->id, 'debit' => '0.00', 'credit' => '40.00'],
        ], 'Aufwandsbuchung');
    }

    /** @param list<array<string, mixed>> $lines */
    private function postEntry(array $lines, string $memo): void {
        app(JournalService::class)->postDirect($this->org, [
            'booked_on' => $this->startsOn->addDays(10),
            'memo' => $memo,
            'source_key' => 'report-test:' . uniqid('', true),
            'lines' => $lines,
        ], $this->admin);
    }

    private function range(): array {
        return [$this->startsOn, $this->startsOn->addMonth()];
    }

    /** Die Grundinvariante der SuSa: beide Seiten sind gleich. */
    public function test_trial_balance_totals_are_balanced(): void {
        [$from, $to] = $this->range();

        $data = app(TrialBalanceBuilder::class)->build($this->org, $from, $to);

        $this->assertSame('159.00', $data['totals']['debit']);
        $this->assertSame('159.00', $data['totals']['credit']);
        $this->assertSame('0.00', $data['totals']['balance']);
    }

    public function test_account_ledger_shows_movements_and_closing_balance(): void {
        [$from, $to] = $this->range();

        $data = app(AccountLedgerBuilder::class)->build($this->org, $this->accounts['bank'], $from, $to);

        $this->assertSame('0.00', $data['opening']);
        $this->assertCount(2, $data['lines']);
        $this->assertSame('79.00', $data['closing']);
    }

    public function test_vat_preview_separates_output_and_input(): void {
        [$from, $to] = $this->range();

        $data = app(VatPreviewBuilder::class)->build($this->org, $from, $to);

        $this->assertSame('19.00', $data['output']);
        $this->assertSame('0.00', $data['input']);
        $this->assertSame('19.00', $data['payable']);
    }

    public function test_profit_and_loss_groups_income_and_expense(): void {
        [$from, $to] = $this->range();

        $data = app(ProfitAndLossBuilder::class)->build($this->org, $from, $to);

        $this->assertSame('100.00', $data['income_total']);
        $this->assertSame('40.00', $data['expense_total']);
        $this->assertSame('60.00', $data['result']);
    }

    /** Ist-Salden und Vorschau bleiben getrennt. */
    public function test_liquidity_separates_actuals_from_forecast(): void {
        [, $to] = $this->range();

        $data = app(LiquidityBuilder::class)->build($this->org, $to);

        $this->assertSame('79.00', $data['cash_total']);
        $this->assertSame('0.00', $data['receivable']);
        $this->assertSame('79.00', $data['forecast']);
    }

    /** Ein Entwurf ist eine Absicht, keine Zahl — er darf nicht mitzählen. */
    public function test_drafts_do_not_appear_in_the_reports(): void {
        [$from, $to] = $this->range();
        app(JournalService::class)->draft($this->org, [
            'booked_on' => $this->startsOn->addDays(12),
            'memo' => 'Nur Entwurf',
            'lines' => [
                ['accounting_account_id' => $this->accounts['expense']->id, 'debit' => '999.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['bank']->id, 'debit' => '0.00', 'credit' => '999.00'],
            ],
        ], $this->admin);

        $data = app(ProfitAndLossBuilder::class)->build($this->org, $from, $to);
        $quality = app(DataQualityBuilder::class)->build($this->org, $from, $to);

        $this->assertSame('40.00', $data['expense_total']);
        $this->assertSame(1, $quality['drafts']);
        $this->assertNotEmpty($quality['findings']);
    }

    public function test_reports_are_reachable_and_permission_gated(): void {
        $member = User::factory()->create(['organization_id' => $this->org->id]);

        $this->actingAs($member)->get(route('reports.accounting.index'))->assertForbidden();

        foreach ([
            'reports.accounting.index',
            'reports.accounting.trial-balance',
            'reports.accounting.account-ledger',
            'reports.accounting.vat',
            'reports.accounting.euer',
            'reports.accounting.profit-and-loss',
            'reports.accounting.liquidity',
            'reports.accounting.liquidity-forecast',
            'reports.accounting.quality',
        ] as $route) {
            $this->actingAs($this->admin)->get(route($route))->assertOk();
        }
    }

    /** Export und Liste stammen aus derselben Quelle. */
    public function test_csv_export_matches_the_report_rows(): void {
        $response = $this->actingAs($this->admin)
            ->get(route('reports.accounting.trial-balance', ['export' => 'csv']))
            ->assertOk();

        $csv = (string) $response->getContent();
        // Der Export trägt seinen Report-Code und dieselben Konten wie die
        // Liste — der Zeitraum kommt in beiden Fällen aus dem globalen Header.
        $this->assertStringContainsString('#report:accounting.trial_balance', $csv);
        $this->assertStringContainsString('1200', $csv);
        $this->assertStringContainsString('8400', $csv);
    }

    /**
     * Alle sieben Berichte liefern PDF (MVP-682) — mit denselben Kopfangaben
     * wie CSV und XLSX.
     */
    public function test_every_report_delivers_a_pdf(): void {
        foreach ([
            'reports.accounting.trial-balance',
            'reports.accounting.account-ledger',
            'reports.accounting.vat',
            'reports.accounting.euer',
            'reports.accounting.profit-and-loss',
            'reports.accounting.liquidity',
            'reports.accounting.liquidity-forecast',
            'reports.accounting.quality',
        ] as $route) {
            $response = $this->actingAs($this->admin)->get(route($route, ['export' => 'pdf']));

            $response->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('content-type'), $route);
            $this->assertStringContainsString('.pdf', (string) $response->headers->get('content-disposition'), $route);
        }
    }

    /** Der Vorbehalt steht auf dem Blatt, nicht nur auf dem Bildschirm. */
    public function test_the_preview_notice_is_printed_on_the_pdf_view(): void {
        [$from, $to] = $this->range();
        $context = app(ExportContextBuilder::class)->build($this->org, $from, $to);

        $html = view('reports.pdf.accounting', [
            'title' => (string) __('accounting.reports.card.vat.title'),
            'context' => $context,
            'rows' => [[(string) __('accounting.ledger.column.account')]],
            'notice' => (string) __('accounting.reports.vat_preview_hint'),
        ])->render();

        $this->assertStringContainsString((string) __('accounting.reports.vat_preview_hint'), $html);
        // Kopfangaben: Versteuerungsart und Gewinnermittlung gehören dazu.
        $this->assertStringContainsString((string) $context['taxation_label'], $html);
        $this->assertStringContainsString((string) $context['currency'], $html);
    }

    public function test_euer_preview_reports_unclear_cases(): void {
        [$from, $to] = $this->range();

        $data = app(EuerPreviewBuilder::class)->build($this->org, $from, $to);

        $this->assertSame('0.00', $data['income']);
        $this->assertSame('0.00', $data['expense']);
        $this->assertIsArray($data['unclear']);
    }
}
