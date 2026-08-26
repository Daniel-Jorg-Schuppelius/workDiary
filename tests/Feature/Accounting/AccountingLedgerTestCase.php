<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingLedgerTestCase.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Accounting;

use App\Enums\Finance\{AccountType, EuerCategory, ProfitDetermination};
use App\Models\Accounting\{AccountingAccount, AccountingEntry};
use App\Models\{CostCenter, Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsService, FiscalYearService, JournalService};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gemeinsamer Aufbau der Tests zu Feature 142 (MVP-709): lokale Buchhaltung
 * ab 2025 mit zwei Geschäftsjahren und einem SKR03-nahen Kontenplan.
 *
 * Beträge sind bewusst „glatt" — die Erwartungen sind Strings mit zwei
 * Nachkommastellen, nie float.
 */
abstract class AccountingLedgerTestCase extends TestCase {
    use RefreshDatabase;

    protected Organization $org;

    protected User $admin;

    protected CarbonImmutable $startsOn;

    /** @var array<string, AccountingAccount> */
    protected array $accounts = [];

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);

        $this->startsOn = CarbonImmutable::create(2025, 1, 1);
        app(AccountingProfileService::class)->configure($this->org, [
            'profit_determination' => ProfitDetermination::DoubleEntry,
            'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1,
            'starts_on' => $this->startsOn,
            'note' => null,
        ]);
        app(FiscalYearService::class)->create($this->org, $this->startsOn);
        app(FiscalYearService::class)->create($this->org, $this->startsOn->addYear());
        app(AccountingProfileService::class)->activateLocal($this->org, $this->admin);

        $chart = app(ChartOfAccountsService::class);
        foreach ([
            'bank' => ['1200', 'Bank', AccountType::Asset, []],
            'vat' => ['1776', 'Umsatzsteuer 19 %', AccountType::Liability, []],
            'material' => ['3200', 'Wareneingang', AccountType::Expense, []],
            'wages' => ['4100', 'Löhne und Gehälter', AccountType::Expense, []],
            'rent' => ['4210', 'Miete', AccountType::Expense, []],
            'vehicle' => ['4500', 'Fahrzeugkosten', AccountType::Expense, []],
            'depreciation' => ['4830', 'Abschreibungen auf Sachanlagen', AccountType::Expense, ['euer_category' => EuerCategory::Depreciation]],
            'revenue' => ['8400', 'Erlöse 19 % USt', AccountType::Income, []],
            // Eigene Nummer außerhalb jedes SKR-Kreises: bleibt ohne BWA-Zeile.
            'custom' => ['U100', 'Eigener Aufwand', AccountType::Expense, []],
        ] as $key => [$number, $name, $type, $extra]) {
            $this->accounts[$key] = $chart->create($this->org, ['number' => $number, 'name' => $name, 'type' => $type] + $extra);
        }
    }

    protected function costCenter(string $code, ?Organization $organization = null): CostCenter {
        return CostCenter::query()->create([
            'organization_id' => ($organization ?? $this->org)->id,
            'code' => $code,
            'label' => 'Kostenstelle ' . $code,
            'active' => true,
        ]);
    }

    /**
     * Festbuchung „Konto gegen Bank": Erlöse im Haben, Aufwand im Soll.
     *
     * @param  numeric-string  $amount
     */
    protected function book(string $accountKey, string $amount, CarbonImmutable $bookedOn, ?int $costCenterId = null, ?string $memo = null): AccountingEntry {
        $account = $this->accounts[$accountKey];
        $isIncome = $account->type === AccountType::Income;

        return app(JournalService::class)->postDirect($this->org, [
            'booked_on' => $bookedOn,
            'memo' => $memo ?? ($account->name . ' ' . $bookedOn->toDateString()),
            'source_key' => 'mvp709:' . uniqid('', true),
            'lines' => [
                [
                    'accounting_account_id' => $account->id,
                    'debit' => $isIncome ? '0.00' : $amount,
                    'credit' => $isIncome ? $amount : '0.00',
                    'cost_center_id' => $costCenterId,
                ],
                [
                    'accounting_account_id' => $this->accounts['bank']->id,
                    'debit' => $isIncome ? $amount : '0.00',
                    'credit' => $isIncome ? '0.00' : $amount,
                    'cost_center_id' => $costCenterId,
                ],
            ],
        ], $this->admin);
    }
}
