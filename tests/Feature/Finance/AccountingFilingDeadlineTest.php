<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingFilingDeadlineTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{FilingObligationKind, FilingObligationStatus, ProfitDetermination, VatFilingInterval};
use App\Models\Accounting\AccountingFilingObligation;
use App\Models\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, FiscalYearService, VatFilingProfileResolver};
use App\Services\Accounting\Filing\{FilingDeadlineCalculator, FilingObligationService, VatFilingPeriodService};
use App\Services\Accounting\Reports\DataQualityBuilder;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fristenkalender (Feature 125, MVP-686).
 *
 * Abnahme: § 108 Abs. 3 AO verschiebt auf den nächsten Werktag, die
 * Dauerfristverlängerung wirkt nur auf die Voranmeldung, und ein
 * Intervallwechsel verschiebt Termine ohne Nacharbeit.
 */
class AccountingFilingDeadlineTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

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
    }

    private function calculator(): FilingDeadlineCalculator {
        return app(FilingDeadlineCalculator::class);
    }

    private function periods(): VatFilingPeriodService {
        return app(VatFilingPeriodService::class);
    }

    /** Grundfrist: 10. Tag nach Periodenende. */
    public function test_the_advance_return_is_due_on_the_tenth(): void {
        $period = $this->periods()->parse('2026-Q1');

        $this->assertNotNull($period);
        // 10.04.2026 ist ein Freitag und damit ein regulärer Werktag.
        $this->assertSame('2026-04-10', $this->calculator()->vatAdvance($this->org, $period)->toDateString());
    }

    /** § 108 Abs. 3 AO: Ein Fristende am Wochenende rutscht auf Montag. */
    public function test_a_deadline_on_a_weekend_moves_to_the_next_business_day(): void {
        // 10.05.2026 ist ein Sonntag.
        $this->assertSame('2026-05-11', app(\App\Services\HolidayService::class)->nextBusinessDay(CarbonImmutable::create(2026, 5, 10) ?? CarbonImmutable::now())->toDateString());
        // 10.06.2026 ist ein Mittwoch und bleibt stehen.
        $this->assertSame('2026-06-10', app(\App\Services\HolidayService::class)->nextBusinessDay(CarbonImmutable::create(2026, 6, 10) ?? CarbonImmutable::now())->toDateString());
    }

    /** Die Dauerfristverlängerung schiebt die Voranmeldung um einen Monat. */
    public function test_the_extension_shifts_the_advance_return_by_one_month(): void {
        $period = $this->periods()->parse('2026-M04');
        $this->assertNotNull($period);

        // Ohne Verlängerung: 10.05. (Sonntag) → 11.05.
        $this->assertSame('2026-05-11', $this->calculator()->vatAdvance($this->org, $period)->toDateString());

        app(VatFilingProfileResolver::class)->recordExtension($this->org, 2026, CarbonImmutable::create(2026, 2, 8), null, $this->admin, null);

        // Mit Verlängerung: 10.06.
        $this->assertSame('2026-06-10', $this->calculator()->vatAdvance($this->org, $period)->toDateString());
    }

    /**
     * Für die Zusammenfassende Meldung gilt die Verlängerung NICHT
     * (§ 18a Abs. 1 UStG) — der häufigste Praxisfehler.
     */
    public function test_the_extension_does_not_shift_the_recapitulative_statement(): void {
        app(VatFilingProfileResolver::class)->recordExtension($this->org, 2026, CarbonImmutable::create(2026, 2, 8), null, $this->admin, null);

        $period = $this->periods()->parse('2026-M04');
        $this->assertNotNull($period);

        // 25.05.2026 ist Pfingstmontag → 26.05. Die Verlängerung würde auf den
        // 25.06. schieben; sie greift hier ausdrücklich nicht.
        $this->assertSame('2026-05-26', $this->calculator()->recapitulative($period)->toDateString());
        $this->assertFalse(FilingObligationKind::Recapitulative->extendable());
    }

    /** Die Jahreserklärung endet später, wenn eine Kanzlei mandatiert ist. */
    public function test_the_annual_return_deadline_depends_on_tax_advice(): void {
        $this->assertSame('2027-08-02', $this->calculator()->annualReturn(2026, false)->toDateString());
        $this->assertSame('2028-02-29', $this->calculator()->annualReturn(2026, true)->toDateString());
    }

    /** Der Abgleich legt die Pflichten des Jahres an — und keine doppelt. */
    public function test_syncing_creates_the_years_obligations_once(): void {
        $service = app(FilingObligationService::class);

        $first = $service->syncYear($this->org, 2026);
        $second = $service->syncYear($this->org, 2026);

        // Vier Quartale plus Jahreserklärung.
        $this->assertSame(5, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(5, AccountingFilingObligation::query()->count());
    }

    /** Ein Intervallwechsel verschiebt die Termine ohne Nacharbeit. */
    public function test_a_changed_interval_refreshes_the_deadlines(): void {
        $service = app(FilingObligationService::class);
        $service->syncYear($this->org, 2026);

        app(VatFilingProfileResolver::class)->recordExtension($this->org, 2026, CarbonImmutable::create(2026, 2, 8), null, $this->admin, null);
        $result = $service->syncYear($this->org, 2026);

        $this->assertGreaterThan(0, $result['updated']);
        $q1 = AccountingFilingObligation::query()
            ->where('kind', FilingObligationKind::VatAdvance->value)
            ->where('period_key', '2026-Q1')
            ->sole();
        // Mit Verlängerung: 10.05.2026 ist ein Sonntag → 11.05.
        $this->assertSame('2026-05-11', $q1->due_on->toDateString());
    }

    /** Die Sondervorauszahlung erscheint nur für Monatszahler mit Verlängerung. */
    public function test_the_special_prepayment_only_appears_for_monthly_filers(): void {
        $service = app(FilingObligationService::class);
        $resolver = app(VatFilingProfileResolver::class);

        $resolver->recordExtension($this->org, 2026, CarbonImmutable::create(2026, 2, 8), null, $this->admin, null);
        $service->syncYear($this->org, 2026);
        $this->assertSame(0, AccountingFilingObligation::query()->where('kind', FilingObligationKind::SpecialPrepayment->value)->count());

        $resolver->switchTo($this->org, VatFilingInterval::Monthly, $this->startsOn, $this->admin, null);
        $service->syncYear($this->org, 2026);
        $this->assertSame(1, AccountingFilingObligation::query()->where('kind', FilingObligationKind::SpecialPrepayment->value)->count());
    }

    /** Überfälliges steht im Datenqualitätsbericht. */
    public function test_overdue_obligations_show_in_the_quality_report(): void {
        app(FilingObligationService::class)->syncYear($this->org, 2026);

        $quality = app(DataQualityBuilder::class)->build(
            $this->org,
            CarbonImmutable::create(2026, 1, 1),
            CarbonImmutable::create(2026, 12, 31),
        );

        $this->assertGreaterThan(0, $quality['overdue_filings']);
    }

    /** Erledigung wird festgehalten, nicht gelöscht. */
    public function test_marking_an_obligation_keeps_it_in_the_list(): void {
        $service = app(FilingObligationService::class);
        $service->syncYear($this->org, 2026);

        $obligation = AccountingFilingObligation::query()->where('period_key', '2026-Q1')->sole();
        $service->mark($obligation, FilingObligationStatus::Submitted, $this->admin, 'über ELSTER abgegeben');

        $this->assertSame(FilingObligationStatus::Submitted, $obligation->refresh()->status);
        $this->assertNotNull($obligation->submitted_at);
        $this->assertSame(5, AccountingFilingObligation::query()->count());
    }

    /** Die Seite zeigt die Termine des Jahres. */
    public function test_the_page_lists_the_deadlines(): void {
        $this->actingAs($this->admin)
            ->get(route('finance.accounting.filings.index', ['year' => 2026]))
            ->assertOk()
            ->assertSee(__('accounting.filing.calendar.title'))
            ->assertSee('2026-Q1');
    }
}
