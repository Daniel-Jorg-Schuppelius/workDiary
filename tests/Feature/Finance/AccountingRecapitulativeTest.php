<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingRecapitulativeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AccountType, ProfitDetermination, TaxCodeDirection, VatFilingInterval};
use App\Models\Accounting\{AccountingAccount, AccountingFilingObligation, AccountingTaxCode};
use App\Models\{Customer, Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsService, FiscalYearService, JournalService};
use App\Services\Accounting\Filing\{FilingObligationService, RecapitulativeStatementService, VatFieldBreakdownService};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Zusammenfassende Meldung und Kennziffern (Feature 125, MVP-687/688).
 *
 * Abnahme: Ohne i.g. Lieferungen keine Pflicht; ein Beleg ohne USt-IdNr. ist
 * ein Klärungsfall; die Dauerfristverlängerung verschiebt die ZM nicht.
 */
class AccountingRecapitulativeTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private CarbonImmutable $startsOn;

    /** @var array<string, AccountingAccount> */
    private array $accounts = [];

    private AccountingTaxCode $iglCode;

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
        $this->accounts['receivable'] = $chart->create($this->org, ['number' => '1400', 'name' => 'Forderungen', 'type' => AccountType::Asset, 'is_open_item' => true]);
        $this->accounts['igl'] = $chart->create($this->org, ['number' => '8125', 'name' => 'Innergemeinschaftliche Lieferungen', 'type' => AccountType::Income]);
        $this->accounts['revenue'] = $chart->create($this->org, ['number' => '8400', 'name' => 'Erlöse 19 %', 'type' => AccountType::Income]);
        $this->accounts['vat'] = $chart->create($this->org, ['number' => '1776', 'name' => 'Umsatzsteuer 19 %', 'type' => AccountType::Liability]);

        $this->iglCode = AccountingTaxCode::query()->create([
            'organization_id' => $this->org->id,
            'code' => 'IGL',
            'name' => 'Innergemeinschaftliche Lieferung',
            'direction' => TaxCodeDirection::None,
            'rate' => '0.00',
            'ustva_base_field' => '41',
            'valid_from' => $this->startsOn->toDateString(),
            'is_active' => true,
        ]);
    }

    private function igSupply(CarbonImmutable $day, string $amount, ?Customer $customer): void {
        app(JournalService::class)->postDirect($this->org, [
            'booked_on' => $day,
            'memo' => 'i.g. Lieferung',
            'source_key' => 'zm-test:' . uniqid('', true),
            'lines' => [
                ['accounting_account_id' => $this->accounts['receivable']->id, 'debit' => $amount, 'credit' => '0.00'],
                [
                    'accounting_account_id' => $this->accounts['igl']->id,
                    'debit' => '0.00',
                    'credit' => $amount,
                    'accounting_tax_code_id' => $this->iglCode->id,
                    'counterparty_type' => $customer !== null ? Customer::class : null,
                    'counterparty_id' => $customer?->id,
                ],
            ],
        ], $this->admin);
    }

    private function customer(?string $vatId): Customer {
        return Customer::factory()->create(['organization_id' => $this->org->id, 'vat_id' => $vatId]);
    }

    private function service(): RecapitulativeStatementService {
        return app(RecapitulativeStatementService::class);
    }

    /** Umsätze werden je USt-IdNr. zusammengefasst. */
    public function test_supplies_are_grouped_by_vat_id(): void {
        $one = $this->customer('ATU12345678');
        $two = $this->customer('FR12345678901');

        $this->igSupply(CarbonImmutable::create(2026, 2, 10), '1000.00', $one);
        $this->igSupply(CarbonImmutable::create(2026, 2, 20), '500.00', $one);
        $this->igSupply(CarbonImmutable::create(2026, 3, 5), '250.00', $two);

        $report = $this->service()->report($this->org, CarbonImmutable::create(2026, 1, 1), CarbonImmutable::create(2026, 3, 31));

        $this->assertCount(2, $report['rows']);
        $this->assertSame('1750.00', $report['total']);
        $this->assertSame('1500.00', collect($report['rows'])->firstWhere('vat_id', 'ATU12345678')['amount']);
        $this->assertSame([], $report['unclear']);
    }

    /** Ohne USt-IdNr. ist die Steuerfreiheit nicht nachweisbar — Klärungsfall. */
    public function test_a_supply_without_a_vat_id_is_a_clarification_case(): void {
        $this->igSupply(CarbonImmutable::create(2026, 2, 10), '800.00', $this->customer(null));

        $report = $this->service()->report($this->org, CarbonImmutable::create(2026, 1, 1), CarbonImmutable::create(2026, 3, 31));

        $this->assertSame([], $report['rows']);
        $this->assertNotEmpty($report['unclear']);
        // Die Summe zeigt den Umsatz trotzdem — er verschwindet nicht.
        $this->assertSame('800.00', $report['total']);
    }

    /** Über 50.000 € im Quartal wird monatlich gemeldet (§ 18a Abs. 1 UStG). */
    public function test_the_threshold_switches_the_period_to_monthly(): void {
        $customer = $this->customer('ATU12345678');

        $this->assertSame(
            VatFilingInterval::Quarterly,
            $this->service()->intervalFor($this->org, CarbonImmutable::create(2026, 3, 31)),
        );

        $this->igSupply(CarbonImmutable::create(2026, 2, 10), '60000.00', $customer);

        $this->assertSame(
            VatFilingInterval::Monthly,
            $this->service()->intervalFor($this->org, CarbonImmutable::create(2026, 3, 31)),
        );
        // Auch im Folgequartal, weil eines der letzten vier Quartale zählt.
        $this->assertSame(
            VatFilingInterval::Monthly,
            $this->service()->intervalFor($this->org, CarbonImmutable::create(2026, 6, 30)),
        );
    }

    /** Ohne i.g. Lieferungen entsteht keine ZM-Pflicht. */
    public function test_without_supplies_there_is_no_obligation(): void {
        app(FilingObligationService::class)->syncYear($this->org, 2026);

        $this->assertSame(0, AccountingFilingObligation::query()
            ->where('kind', \App\Enums\Finance\FilingObligationKind::Recapitulative->value)
            ->count());
    }

    /** Mit Lieferungen entsteht ein Termin — mit eigener Frist. */
    public function test_supplies_create_an_obligation_with_its_own_deadline(): void {
        $this->igSupply(CarbonImmutable::create(2026, 2, 10), '1000.00', $this->customer('ATU12345678'));

        app(FilingObligationService::class)->syncYear($this->org, 2026);

        $obligation = AccountingFilingObligation::query()
            ->where('kind', \App\Enums\Finance\FilingObligationKind::Recapitulative->value)
            ->sole();

        $this->assertSame('ZM-2026-Q1', $obligation->period_key);
        // 25.04.2026 ist ein Samstag → 27.04.
        $this->assertSame('2026-04-27', $obligation->due_on->toDateString());
    }

    /** Die Kennziffern-Aufteilung folgt dem Steuerkennzeichen. */
    public function test_the_field_breakdown_follows_the_tax_code(): void {
        $this->igSupply(CarbonImmutable::create(2026, 2, 10), '1000.00', $this->customer('ATU12345678'));

        $breakdown = app(VatFieldBreakdownService::class)->forRange(
            $this->org,
            CarbonImmutable::create(2026, 1, 1),
            CarbonImmutable::create(2026, 3, 31),
        );

        $field = collect($breakdown['fields'])->firstWhere('field', '41');
        $this->assertNotNull($field);
        $this->assertSame('1000.00', $field['base']);
        $this->assertSame([], $breakdown['unclear']);
    }

    /** Ein Kennzeichen ohne Kennziffer steht sichtbar in den Klärungsfällen. */
    public function test_a_tax_code_without_a_field_number_is_listed(): void {
        $this->iglCode->update(['ustva_base_field' => null]);
        $this->igSupply(CarbonImmutable::create(2026, 2, 10), '1000.00', $this->customer('ATU12345678'));

        $breakdown = app(VatFieldBreakdownService::class)->forRange(
            $this->org,
            CarbonImmutable::create(2026, 1, 1),
            CarbonImmutable::create(2026, 3, 31),
        );

        $this->assertSame([], $breakdown['fields']);
        $this->assertNotEmpty($breakdown['unclear']);
    }

    /** Der Bericht ist erreichbar und zeigt die Frist. */
    public function test_the_report_page_shows_the_deadline(): void {
        $this->igSupply(CarbonImmutable::create(2026, 2, 10), '1000.00', $this->customer('ATU12345678'));

        $this->actingAs($this->admin)
            ->get(route('reports.accounting.recapitulative', ['period' => '2026-Q1']))
            ->assertOk()
            ->assertSee(__('accounting.recapitulative.title'))
            ->assertSee('ATU12345678');
    }
}
