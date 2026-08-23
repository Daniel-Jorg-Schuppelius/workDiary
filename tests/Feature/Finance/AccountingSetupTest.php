<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingSetupTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AccountingPeriodStatus, AccountingSovereignty, DatevBatchStatus, ProfitDetermination};
use App\Enums\Migration\{AccountingMigrationStatus, MigrationProvider};
use App\Models\Accounting\{AccountingFiscalYear, AccountingPeriod, AccountingProfile, AccountingSovereigntyPeriod};
use App\Models\Finance\DatevBookingBatch;
use App\Models\Migration\AccountingMigrationRun;
use App\Models\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, AccountingSovereigntyException, AccountingSovereigntyResolver, FiscalYearService};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Einrichtung der lokalen Buchhaltung (Feature 125, MVP-671).
 *
 * Die beiden Zusagen des Pakets: Ein Deployment verschiebt keine
 * Buchungshoheit, und lokal gebucht wird erst nach vollständigem Preflight.
 */
class AccountingSetupTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
    }

    private function profiles(): AccountingProfileService {
        return app(AccountingProfileService::class);
    }

    private function resolver(): AccountingSovereigntyResolver {
        return app(AccountingSovereigntyResolver::class);
    }

    /** Legt Profil und passendes Geschäftsjahr an; Rückgabe: Stichtag. */
    private function prepare(?CarbonImmutable $startsOn = null): CarbonImmutable {
        $startsOn ??= CarbonImmutable::create(2026, 1, 1);

        $this->profiles()->configure($this->org, [
            'profit_determination' => ProfitDetermination::DoubleEntry,
            'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1,
            'starts_on' => $startsOn,
            'note' => null,
        ]);

        app(FiscalYearService::class)->create($this->org, $startsOn);

        return $startsOn;
    }

    public function test_organizations_without_a_profile_stay_in_preaccounting(): void {
        $this->assertSame(AccountingSovereignty::Preaccounting, $this->resolver()->at($this->org));
        $this->assertSame(0, AccountingProfile::query()->count());
        $this->assertFalse($this->profiles()->profileFor($this->org)->exists);
    }

    public function test_configuring_the_profile_does_not_move_the_posting_authority(): void {
        $this->prepare();

        $profile = AccountingProfile::query()->sole();
        $this->assertSame(AccountingSovereignty::Preaccounting, $profile->sovereignty);
        $this->assertSame(AccountingSovereignty::Preaccounting, $this->resolver()->at($this->org));
        $this->assertSame(0, AccountingSovereigntyPeriod::query()->count());
    }

    public function test_fiscal_year_creates_twelve_gapless_periods(): void {
        $year = app(FiscalYearService::class)->create($this->org, CarbonImmutable::create(2026, 7, 1));

        $periods = AccountingPeriod::query()->where('accounting_fiscal_year_id', $year->id)->orderBy('sequence')->get();
        $this->assertCount(12, $periods);
        $this->assertSame('2026/2027', $year->label);
        $this->assertSame('2026-07-01', $periods->first()?->starts_on->toDateString());
        $this->assertSame('2027-06-30', $periods->last()?->ends_on->toDateString());

        // Lückenlos: jede Periode beginnt am Tag nach dem Ende der vorherigen.
        $periods->sliding(2)->each(function ($pair): void {
            [$current, $next] = [$pair->first(), $pair->last()];
            $this->assertSame($current->ends_on->addDay()->toDateString(), $next->starts_on->toDateString());
        });
    }

    public function test_overlapping_fiscal_year_is_rejected(): void {
        app(FiscalYearService::class)->create($this->org, CarbonImmutable::create(2026, 1, 1));

        $this->expectException(ValidationException::class);
        app(FiscalYearService::class)->create($this->org, CarbonImmutable::create(2026, 6, 1));
    }

    public function test_activation_is_blocked_without_a_fiscal_year(): void {
        $this->profiles()->configure($this->org, [
            'profit_determination' => ProfitDetermination::Euer,
            'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1,
            'starts_on' => CarbonImmutable::create(2026, 1, 1),
            'note' => null,
        ]);

        $report = $this->profiles()->preflight($this->org);
        $this->assertFalse($report->isReady());
        $this->assertContains('fiscal_year', array_map(fn ($c): string => $c->key, $report->blockers()));

        $this->expectException(ValidationException::class);
        $this->profiles()->activateLocal($this->org, $this->admin);
    }

    public function test_activation_opens_a_local_authority_section_from_the_start_date(): void {
        $startsOn = $this->prepare();

        $profile = $this->profiles()->activateLocal($this->org, $this->admin);

        $this->assertSame(AccountingSovereignty::Local, $profile->sovereignty);
        $this->assertNotNull($profile->activated_at);
        $this->assertTrue((bool) ($profile->preflight['ready'] ?? false));

        $section = AccountingSovereigntyPeriod::query()->sole();
        $this->assertSame(AccountingSovereignty::Local, $section->sovereignty);
        $this->assertSame($startsOn->toDateString(), $section->valid_from->toDateString());
        $this->assertNull($section->valid_to);
    }

    public function test_the_guard_refuses_postings_before_the_start_date(): void {
        $startsOn = $this->prepare();
        $this->profiles()->activateLocal($this->org, $this->admin);

        $this->assertTrue($this->resolver()->allowsLocalPosting($this->org, $startsOn));
        $this->assertFalse($this->resolver()->allowsLocalPosting($this->org, $startsOn->subDay()));

        $this->expectException(AccountingSovereigntyException::class);
        $this->resolver()->assertLocalPostingAllowed($this->org, $startsOn->subDay());
    }

    public function test_a_running_accounting_migration_blocks_the_activation(): void {
        $this->prepare();

        AccountingMigrationRun::query()->create([
            'organization_id' => $this->org->id,
            'source_plugin' => MigrationProvider::Lexoffice->value,
            'target_plugin' => MigrationProvider::OrgaMax->value,
            'status' => AccountingMigrationStatus::ParallelRun->value,
            'data_areas' => ['customers'],
        ]);

        $blockers = array_map(fn ($c): string => $c->key, $this->profiles()->preflight($this->org)->blockers());
        $this->assertContains('migration_run', $blockers);
    }

    /** Ein exportierter Stapel ist eine abgegebene Erklärung über seinen Zeitraum. */
    public function test_an_exported_datev_batch_blocks_the_same_period(): void {
        $startsOn = $this->prepare();

        DatevBookingBatch::query()->create([
            'organization_id' => $this->org->id,
            'batch_no' => 1,
            'period_from' => $startsOn->toDateString(),
            'period_to' => $startsOn->addMonth()->toDateString(),
            'status' => DatevBatchStatus::Exported->value,
            'skr' => 'skr03',
            // Berater-/Mandantennummer sind Pflicht-Snapshots des Stapels.
            'advisor_number' => 12345,
            'client_number' => 6789,
            'booking_count' => 3,
            'total_amount' => '100.00',
        ]);

        $blockers = array_map(fn ($c): string => $c->key, $this->profiles()->preflight($this->org)->blockers());
        $this->assertContains('handed_over', $blockers);
    }

    public function test_switching_authority_closes_the_running_section_the_day_before(): void {
        $startsOn = $this->prepare();
        $this->profiles()->activateLocal($this->org, $this->admin);

        $switchOn = $startsOn->addMonths(6);
        $this->profiles()->switchSovereignty($this->org, AccountingSovereignty::External, $switchOn, $this->admin, 'lexoffice', 'Steuerbüro übernimmt');

        $sections = AccountingSovereigntyPeriod::query()->orderBy('valid_from')->get();
        $this->assertCount(2, $sections);
        $this->assertSame($switchOn->subDay()->toDateString(), $sections->first()?->valid_to->toDateString());
        $this->assertSame(AccountingSovereignty::External, $sections->last()?->sovereignty);

        // Die Vergangenheit bleibt lokal geführt, die Zukunft nicht mehr.
        $this->assertTrue($this->resolver()->allowsLocalPosting($this->org, $switchOn->subDay()));
        $this->assertFalse($this->resolver()->allowsLocalPosting($this->org, $switchOn));
    }

    public function test_external_authority_requires_a_named_system(): void {
        $this->prepare();

        $this->expectException(ValidationException::class);
        $this->profiles()->switchSovereignty($this->org, AccountingSovereignty::External, CarbonImmutable::create(2026, 3, 1), $this->admin, null, null);
    }

    /** Der Wechseldialog darf keine Hintertür an der Aktivierung vorbei sein. */
    public function test_switching_to_local_also_requires_a_clean_preflight(): void {
        $this->profiles()->configure($this->org, [
            'profit_determination' => ProfitDetermination::Euer,
            'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1,
            'starts_on' => CarbonImmutable::create(2026, 1, 1),
            'note' => null,
        ]);

        // Kein Geschäftsjahr -> Preflight blockiert, also auch der Wechsel.
        $this->expectException(ValidationException::class);
        $this->profiles()->switchSovereignty($this->org, AccountingSovereignty::Local, CarbonImmutable::create(2026, 1, 1), $this->admin, null, null);
    }

    public function test_the_start_date_is_locked_after_activation(): void {
        $startsOn = $this->prepare();
        $this->profiles()->activateLocal($this->org, $this->admin);

        $this->expectException(ValidationException::class);
        $this->profiles()->configure($this->org, [
            'profit_determination' => ProfitDetermination::DoubleEntry,
            'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1,
            'starts_on' => $startsOn->addMonth(),
            'note' => null,
        ]);
    }

    public function test_periods_start_open(): void {
        $year = app(FiscalYearService::class)->create($this->org, CarbonImmutable::create(2026, 1, 1));

        $this->assertSame(AccountingPeriodStatus::Open, $year->periods->first()?->status);
        $this->assertTrue($year->periods->first()?->status->acceptsPostings());
    }

    public function test_setup_page_requires_the_view_permission(): void {
        $member = User::factory()->create(['organization_id' => $this->org->id]);

        $this->actingAs($member)->get(route('finance.accounting.setup'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('finance.accounting.setup'))->assertOk();
    }

    /** Die Dialogfragmente werden separat geladen — ein Blade-Fehler fiele sonst erst im Betrieb auf. */
    public function test_the_dialogs_render(): void {
        $this->prepare();

        $this->actingAs($this->admin)->get(route('finance.accounting.fiscal-years.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('finance.accounting.sovereignty.create'))->assertOk();
    }

    public function test_configuring_requires_the_configure_permission(): void {
        $member = User::factory()->create(['organization_id' => $this->org->id]);

        $this->actingAs($member)->put(route('finance.accounting.update'), [
            'profit_determination' => ProfitDetermination::Euer->value,
            'base_currency' => 'EUR',
            'fiscal_year_start_month' => 1,
        ])->assertForbidden();
    }

    public function test_activation_over_http_writes_the_authority_section(): void {
        $this->prepare();

        $this->actingAs($this->admin)
            ->post(route('finance.accounting.activate'))
            ->assertRedirect();

        $this->assertSame(AccountingSovereignty::Local, AccountingProfile::query()->sole()->sovereignty);
        $this->assertSame(1, AccountingSovereigntyPeriod::query()->count());
    }

    public function test_foreign_authority_of_another_organization_does_not_leak(): void {
        $this->prepare();
        $this->profiles()->activateLocal($this->org, $this->admin);

        $other = Organization::factory()->create();
        $this->assertSame(AccountingSovereignty::Preaccounting, $this->resolver()->at($other));
        $this->assertFalse($this->resolver()->allowsLocalPosting($other));
    }

    public function test_the_fiscal_year_is_scoped_to_its_organization(): void {
        app(FiscalYearService::class)->create($this->org, CarbonImmutable::create(2026, 1, 1));

        $this->assertSame(1, AccountingFiscalYear::query()->where('organization_id', $this->org->id)->count());
        $this->assertSame(0, AccountingFiscalYear::query()->where('organization_id', '!=', $this->org->id)->count());
    }
}
