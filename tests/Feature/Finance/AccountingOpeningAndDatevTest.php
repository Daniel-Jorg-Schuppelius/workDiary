<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingOpeningAndDatevTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AccountType, ProfitDetermination};
use App\Models\Accounting\{AccountingAccount, AccountingEvent};
use App\Models\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsService, FiscalYearService, JournalService, LedgerDatevExportService, OpeningBalanceImportService};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Startsalden-Übernahme und DATEV-Übergabe (Feature 125, MVP-677).
 */
class AccountingOpeningAndDatevTest extends TestCase {
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
        $this->accounts['bank'] = $chart->create($this->org, ['number' => '1200', 'name' => 'Bank', 'type' => AccountType::Asset, 'is_bank' => true, 'datev_account' => '1200']);
        $this->accounts['equity'] = $chart->create($this->org, ['number' => '9000', 'name' => 'Saldenvorträge', 'type' => AccountType::Equity]);
    }

    private function csv(string $content): string {
        $path = tempnam(sys_get_temp_dir(), 'opening') . '.csv';
        file_put_contents($path, $content);

        return $path;
    }

    private function service(): OpeningBalanceImportService {
        return app(OpeningBalanceImportService::class);
    }

    public function test_dry_run_reports_totals_without_posting(): void {
        $path = $this->csv("account;debit;credit\r\n1200;5000,00;0\r\n9000;0;5000,00\r\n");

        $result = $this->service()->dryRun($this->org, $path);
        unlink($path);

        $this->assertTrue($result['balanced']);
        $this->assertSame('5000.00', $result['debit']);
        $this->assertSame([], $result['errors']);
        $this->assertSame(0, \App\Models\Accounting\AccountingEntry::query()->count());
    }

    public function test_dry_run_names_unknown_accounts(): void {
        $path = $this->csv("account;debit;credit\r\n9999;100,00;0\r\n");

        $result = $this->service()->dryRun($this->org, $path);
        unlink($path);

        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('9999', $result['errors'][0]);
    }

    public function test_unbalanced_import_is_refused(): void {
        $path = $this->csv("account;debit;credit\r\n1200;5000,00;0\r\n9000;0;4000,00\r\n");

        try {
            $this->expectException(ValidationException::class);
            $this->service()->import($this->org, $path, $this->admin);
        } finally {
            unlink($path);
        }
    }

    public function test_import_creates_the_opening_entry_with_proof(): void {
        $path = $this->csv("account;debit;credit\r\n1200;5000,00;0\r\n9000;0;5000,00\r\n");

        $entry = $this->service()->import($this->org, $path, $this->admin);
        unlink($path);

        $this->assertSame('opening_balance', $entry->source_key);
        $this->assertSame($this->startsOn->toDateString(), $entry->booked_on->toDateString());
        $this->assertContains('accounting.opening_balance_imported', AccountingEvent::query()->pluck('event')->all());
    }

    /** Die Übergabe kommt aus dem Journal, nicht aus den Belegen. */
    public function test_datev_export_mirrors_the_journal(): void {
        app(JournalService::class)->postDirect($this->org, [
            'booked_on' => $this->startsOn->addDays(3),
            'memo' => 'Barverkauf',
            'document_reference' => 'BV-1',
            'source_key' => 'datev-test:1',
            'lines' => [
                ['accounting_account_id' => $this->accounts['bank']->id, 'debit' => '250.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['equity']->id, 'debit' => '0.00', 'credit' => '250.00'],
            ],
        ], $this->admin);

        $result = app(LedgerDatevExportService::class)->build($this->org, $this->startsOn, $this->startsOn->addMonth());

        $this->assertSame(2, $result['rows']);
        $this->assertSame('250.00', $result['debit']);
        $this->assertSame($result['debit'], $result['credit']);
        $this->assertStringContainsString('Barverkauf', $result['csv']);
        // Gegenkonto wird bei zwei Zeilen gesetzt, nicht erfunden.
        $this->assertStringContainsString('9000', $result['csv']);
    }

    public function test_export_is_reproducible(): void {
        app(JournalService::class)->postDirect($this->org, [
            'booked_on' => $this->startsOn->addDays(3),
            'memo' => 'Wiederholbar',
            'source_key' => 'datev-test:2',
            'lines' => [
                ['accounting_account_id' => $this->accounts['bank']->id, 'debit' => '10.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['equity']->id, 'debit' => '0.00', 'credit' => '10.00'],
            ],
        ], $this->admin);

        $first = app(LedgerDatevExportService::class)->build($this->org, $this->startsOn, $this->startsOn->addMonth());
        $second = app(LedgerDatevExportService::class)->build($this->org, $this->startsOn, $this->startsOn->addMonth());

        $this->assertSame($first['csv'], $second['csv']);
    }

    public function test_opening_import_over_http_requires_the_close_permission(): void {
        $member = User::factory()->create(['organization_id' => $this->org->id]);
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('salden.csv', "account;debit;credit\r\n1200;1,00;0\r\n9000;0;1,00\r\n");

        $this->actingAs($member)
            ->post(route('finance.accounting.closing.opening-balances'), ['file' => $file, 'dry_run' => '1'])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('finance.accounting.closing.opening-balances'), ['file' => $file, 'dry_run' => '1'])
            ->assertRedirect();
    }
}
