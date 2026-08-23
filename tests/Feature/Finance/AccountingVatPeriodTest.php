<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVatPeriodTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AccountType, PostingAccountRole, PostingSourceKind, ProfitDetermination, VatFilingInterval};
use App\Models\Accounting\{AccountingAccount, AccountingPostingRule};
use App\Models\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsService, FiscalYearService, JournalService, VatFilingProfileResolver};
use App\Services\Accounting\Filing\{VatFilingPeriodService, VatReturnService, VatSpecialPrepaymentService};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Voranmeldungszeiträume und Sondervorauszahlung (Feature 125, MVP-685).
 *
 * Abnahme: Monats- und Quartalssicht ergeben dieselbe Jahressumme, und die
 * Zahllast einer Periode hängt nicht am globalen Header-Zeitraum.
 */
class AccountingVatPeriodTest extends TestCase {
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
        $this->accounts['receivable'] = $chart->create($this->org, ['number' => '1400', 'name' => 'Forderungen', 'type' => AccountType::Asset, 'is_open_item' => true]);
        $this->accounts['revenue'] = $chart->create($this->org, ['number' => '8400', 'name' => 'Erlöse', 'type' => AccountType::Income]);
        $this->accounts['vat'] = $chart->create($this->org, ['number' => '1776', 'name' => 'Umsatzsteuer 19 %', 'type' => AccountType::Liability]);
        $this->accounts['bank'] = $chart->create($this->org, ['number' => '1200', 'name' => 'Bank', 'type' => AccountType::Asset, 'is_bank' => true]);
        $this->accounts['prepayment'] = $chart->create($this->org, ['number' => '1781', 'name' => 'USt-Vorauszahlungen 1/11', 'type' => AccountType::Asset]);

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

    private function sale(CarbonImmutable $day, string $net, string $tax): void {
        app(JournalService::class)->postDirect($this->org, [
            'booked_on' => $day,
            'memo' => 'Erlös ' . $day->toDateString(),
            'source_key' => 'period-test:' . $day->toDateString() . ':' . uniqid('', true),
            'lines' => [
                ['accounting_account_id' => $this->accounts['receivable']->id, 'debit' => number_format((float) $net + (float) $tax, 2, '.', ''), 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['revenue']->id, 'debit' => '0.00', 'credit' => $net],
                ['accounting_account_id' => $this->accounts['vat']->id, 'debit' => '0.00', 'credit' => $tax],
            ],
        ], $this->admin);
    }

    private function periods(): VatFilingPeriodService {
        return app(VatFilingPeriodService::class);
    }

    /** Ohne Festlegung entstehen vier Quartale. */
    public function test_the_default_year_has_four_quarters(): void {
        $periods = $this->periods()->periodsFor($this->org, 2026);

        $this->assertCount(4, $periods);
        $this->assertSame('2026-Q1', $periods[0]->key);
        $this->assertSame('2026-01-01', $periods[0]->from->toDateString());
        $this->assertSame('2026-03-31', $periods[0]->to->toDateString());
        $this->assertTrue($periods[3]->isLastOfYear());
    }

    /** Monatlich ergibt zwölf Perioden mit exakten Monatsgrenzen. */
    public function test_monthly_filing_produces_twelve_periods(): void {
        app(VatFilingProfileResolver::class)->switchTo($this->org, VatFilingInterval::Monthly, $this->startsOn, $this->admin, null);

        $periods = $this->periods()->periodsFor($this->org, 2026);

        $this->assertCount(12, $periods);
        $this->assertSame('2026-M02', $periods[1]->key);
        $this->assertSame('2026-02-28', $periods[1]->to->toDateString());
        $this->assertSame('2026-M12', $periods[11]->key);
    }

    /** Ein unterjähriger Wechsel erzeugt gemischte Perioden ohne Lücke. */
    public function test_a_mid_year_switch_produces_mixed_periods(): void {
        $resolver = app(VatFilingProfileResolver::class);
        $resolver->switchTo($this->org, VatFilingInterval::Quarterly, $this->startsOn, $this->admin, null);
        $resolver->switchTo($this->org, VatFilingInterval::Monthly, CarbonImmutable::create(2026, 7, 1), $this->admin, null);

        $periods = $this->periods()->periodsFor($this->org, 2026);
        $keys = array_map(static fn ($period): string => $period->key, $periods);

        $this->assertSame(['2026-Q1', '2026-Q2', '2026-M07', '2026-M08', '2026-M09', '2026-M10', '2026-M11', '2026-M12'], $keys);
        // Lückenlos: Das Ende jeder Periode grenzt an den Beginn der nächsten.
        for ($i = 1; $i < count($periods); $i++) {
            $this->assertSame($periods[$i - 1]->to->addDay()->toDateString(), $periods[$i]->from->toDateString());
        }
    }

    /** Dieselben Buchungen ergeben in Monats- und Quartalssicht dieselbe Summe. */
    public function test_monthly_and_quarterly_views_add_up_to_the_same_year(): void {
        $this->sale(CarbonImmutable::create(2026, 1, 15), '100.00', '19.00');
        $this->sale(CarbonImmutable::create(2026, 2, 15), '200.00', '38.00');
        $this->sale(CarbonImmutable::create(2026, 5, 15), '300.00', '57.00');

        $returns = app(VatReturnService::class);

        $quarterly = '0.00';
        foreach ($this->periods()->periodsFor($this->org, 2026) as $period) {
            $quarterly = number_format((float) $quarterly + (float) $returns->preview($this->org, $period)['payable'], 2, '.', '');
        }

        app(VatFilingProfileResolver::class)->switchTo($this->org, VatFilingInterval::Monthly, $this->startsOn, $this->admin, null);

        $monthly = '0.00';
        foreach ($this->periods()->periodsFor($this->org, 2026) as $period) {
            $monthly = number_format((float) $monthly + (float) $returns->preview($this->org, $period)['payable'], 2, '.', '');
        }

        $this->assertSame('114.00', $quarterly);
        $this->assertSame($quarterly, $monthly);
    }

    /** Unbekannte Schlüssel liefern null statt eines geratenen Zeitraums. */
    public function test_unknown_period_keys_are_refused(): void {
        $service = $this->periods();

        $this->assertSame('2026-Q3', $service->parse('2026-Q3')?->key);
        $this->assertSame('2026-M11', $service->parse('2026-M11')?->key);
        $this->assertNull($service->parse('2026-M13'));
        $this->assertNull($service->parse('unsinn'));
    }

    /** Die Sondervorauszahlung ist ein Elftel der Vorjahressteuer. */
    public function test_the_special_prepayment_is_one_eleventh(): void {
        $this->sale(CarbonImmutable::create(2026, 3, 15), '10000.00', '1100.00');

        $calculation = app(VatSpecialPrepaymentService::class)->calculate($this->org, 2027);

        $this->assertSame('1100.00', $calculation['prior_year_tax']);
        $this->assertSame('100.00', $calculation['amount']);
        $this->assertSame('2027-02-10', $calculation['due_on']);
        // Quartalszahler schulden sie nicht (§ 46 UStDV).
        $this->assertFalse($calculation['required']);
    }

    /** Ohne erfasste Verlängerung gibt es keine Buchung. */
    public function test_posting_without_an_extension_is_refused(): void {
        $this->expectException(ValidationException::class);
        app(VatSpecialPrepaymentService::class)->post(
            $this->org,
            2026,
            $this->accounts['prepayment'],
            $this->accounts['bank'],
            '100.00',
            CarbonImmutable::create(2026, 2, 10),
            $this->admin,
        );
    }

    /** Gebucht wird sie wie jeder andere Vorgang — und dann angerechnet. */
    public function test_a_posted_prepayment_is_credited_in_the_last_period(): void {
        app(VatFilingProfileResolver::class)->switchTo($this->org, VatFilingInterval::Monthly, $this->startsOn, $this->admin, null);
        app(VatFilingProfileResolver::class)->recordExtension($this->org, 2026, CarbonImmutable::create(2026, 2, 8), '100.00', $this->admin, null);

        app(VatSpecialPrepaymentService::class)->post(
            $this->org,
            2026,
            $this->accounts['prepayment'],
            $this->accounts['bank'],
            '100.00',
            CarbonImmutable::create(2026, 2, 10),
            $this->admin,
        );

        $this->sale(CarbonImmutable::create(2026, 12, 10), '1000.00', '190.00');

        $returns = app(VatReturnService::class);
        $december = $this->periods()->parse('2026-M12');
        $november = $this->periods()->parse('2026-M11');

        $this->assertNotNull($december);
        $this->assertSame('100.00', $returns->preview($this->org, $december)['special_prepayment']);
        $this->assertSame('90.00', $returns->preview($this->org, $december)['remaining']);
        // Nur die letzte Periode rechnet an.
        $this->assertSame('0.00', $returns->preview($this->org, $november)['special_prepayment']);
    }

    /** Die Auswertung hängt an der Periode, nicht am Header-Zeitraum. */
    public function test_the_report_uses_the_period_not_the_header_range(): void {
        $this->sale(CarbonImmutable::create(2026, 2, 15), '100.00', '19.00');

        $response = $this->actingAs($this->admin)
            ->get(route('reports.accounting.vat', ['period' => '2026-Q1', 'from' => '2026-06-01', 'to' => '2026-06-30']))
            ->assertOk();

        $response->assertSee('19.00');
        $response->assertSee('Q1 2026');
    }
}
