<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVatFilingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{ProfitDetermination, VatFilingInterval};
use App\Models\Accounting\{AccountingEvent, AccountingVatFilingPeriod};
use App\Models\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, FiscalYearService, VatFilingProfileResolver};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Meldeprofil der Umsatzsteuer (Feature 125, MVP-684).
 *
 * Abnahme: Ohne Festlegung gilt das Quartal; ein Wechsel erzeugt Abschnitt und
 * Kettenereignis; der Ableitungsvorschlag ändert nichts von selbst.
 */
class AccountingVatFilingTest extends TestCase {
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

    private function resolver(): VatFilingProfileResolver {
        return app(VatFilingProfileResolver::class);
    }

    /** Der gesetzliche Regelfall gilt ohne jede Einstellung. */
    public function test_the_default_is_the_calendar_quarter(): void {
        $this->assertSame(VatFilingInterval::Quarterly, $this->resolver()->at($this->org));
        $this->assertSame(0, AccountingVatFilingPeriod::query()->count());
    }

    /** Ein Wechsel schließt den laufenden Abschnitt und schreibt die Kette. */
    public function test_switching_closes_the_running_section_and_writes_the_chain(): void {
        $resolver = $this->resolver();

        $resolver->switchTo($this->org, VatFilingInterval::Monthly, $this->startsOn, $this->admin, 'Bescheid vom 12.12.2025');
        $resolver->switchTo($this->org, VatFilingInterval::Quarterly, $this->startsOn->addYear(), $this->admin, null);

        $sections = AccountingVatFilingPeriod::query()->orderBy('valid_from')->get();

        $this->assertCount(2, $sections);
        $this->assertSame('2026-12-31', $sections[0]->valid_to?->toDateString());
        $this->assertNull($sections[1]->valid_to);

        $this->assertSame(VatFilingInterval::Monthly, $resolver->at($this->org, CarbonImmutable::create(2026, 6, 30)));
        $this->assertSame(VatFilingInterval::Quarterly, $resolver->at($this->org, CarbonImmutable::create(2027, 6, 30)));

        $this->assertSame(2, AccountingEvent::query()->where('event', 'accounting.filing_interval_switched')->count());
    }

    /** Derselbe Zeitraum zum selben Stichtag ist kein Wechsel. */
    public function test_switching_to_the_same_interval_is_refused(): void {
        $this->resolver()->switchTo($this->org, VatFilingInterval::Monthly, $this->startsOn, $this->admin, null);

        $this->expectException(ValidationException::class);
        $this->resolver()->switchTo($this->org, VatFilingInterval::Monthly, $this->startsOn->addMonths(3), $this->admin, null);
    }

    /** Ein späterer Abschnitt blockiert den Wechsel — sonst entstünden Lücken. */
    public function test_a_later_section_blocks_an_earlier_switch(): void {
        $this->resolver()->switchTo($this->org, VatFilingInterval::Monthly, $this->startsOn->addYear(), $this->admin, null);

        $this->expectException(ValidationException::class);
        $this->resolver()->switchTo($this->org, VatFilingInterval::Annual, $this->startsOn, $this->admin, null);
    }

    /**
     * Der Vorschlag folgt § 18 Abs. 2 UStG — und ändert nichts.
     */
    public function test_the_suggestion_follows_the_thresholds_without_applying_them(): void {
        $resolver = $this->resolver();

        $this->assertSame(VatFilingInterval::Monthly, $resolver->suggest(2026, '9500.00')['interval']);
        $this->assertSame(VatFilingInterval::Quarterly, $resolver->suggest(2026, '4000.00')['interval']);
        $this->assertSame(VatFilingInterval::Annual, $resolver->suggest(2026, '1800.00')['interval']);

        // Grenzwerte: genau 9.000 € ist noch nicht monatlich, genau 2.000 €
        // noch befreit — „mehr als" und „nicht mehr als" im Gesetzestext.
        $this->assertSame(VatFilingInterval::Quarterly, $resolver->suggest(2026, '9000.00')['interval']);
        $this->assertSame(VatFilingInterval::Annual, $resolver->suggest(2026, '2000.00')['interval']);

        $this->assertSame(VatFilingInterval::Quarterly, $resolver->at($this->org));
        $this->assertSame(0, AccountingVatFilingPeriod::query()->count());
    }

    /** Die Neugründer-Regel ist bis einschließlich 2026 ausgesetzt. */
    public function test_the_founder_rule_returns_in_2027(): void {
        $this->assertFalse($this->resolver()->suggest(2026, '1000.00')['founder_rule_active']);
        $this->assertTrue($this->resolver()->suggest(2027, '1000.00')['founder_rule_active']);
    }

    /** Die Verlängerung gilt fort, die Sondervorauszahlung ist jährlich. */
    public function test_an_extension_keeps_applying_in_later_years(): void {
        $resolver = $this->resolver();

        $resolver->recordExtension($this->org, 2026, CarbonImmutable::create(2026, 2, 8), '1200.00', $this->admin, null);

        $this->assertTrue($resolver->hasExtension($this->org, CarbonImmutable::create(2026, 5, 1)));
        $this->assertTrue($resolver->hasExtension($this->org, CarbonImmutable::create(2028, 5, 1)));
        $this->assertFalse($resolver->hasExtension($this->org, CarbonImmutable::create(2025, 5, 1)));

        $this->assertSame('1200.00', $resolver->extensionFor($this->org, 2026)?->special_prepayment_amount?->getAmount());
        $this->assertNull($resolver->extensionFor($this->org, 2027));
        $this->assertSame(1, AccountingEvent::query()->where('event', 'accounting.vat_extension_recorded')->count());
    }

    /** Ein zweiter Eintrag für dasselbe Jahr schreibt fort, statt zu doppeln. */
    public function test_recording_the_same_year_twice_updates_instead_of_duplicating(): void {
        $resolver = $this->resolver();

        $resolver->recordExtension($this->org, 2026, CarbonImmutable::create(2026, 2, 8), '1200.00', $this->admin, null);
        $resolver->recordExtension($this->org, 2026, CarbonImmutable::create(2026, 2, 8), '1350.00', $this->admin, 'korrigiert');

        $this->assertSame(1, \App\Models\Accounting\AccountingVatExtension::query()->count());
        $this->assertSame('1350.00', $resolver->extensionFor($this->org, 2026)?->special_prepayment_amount?->getAmount());
    }

    /** Der Einrichtungsassistent zeigt Zeitraum und Vorschlag. */
    public function test_the_setup_page_shows_the_filing_profile(): void {
        $this->actingAs($this->admin)
            ->get(route('finance.accounting.setup'))
            ->assertOk()
            ->assertSee(__('accounting.filing.title'))
            ->assertSee(VatFilingInterval::Quarterly->label());
    }
}
