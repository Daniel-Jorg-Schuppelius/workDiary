<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HolidayServiceRegionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Holiday;

use App\Models\Organization;
use App\Services\HolidayService;
use App\Support\HolidayRegions;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 034 — mandantenbezogener Feiertags-Rechtsraum.
 *
 * Fronleichnam (Corpus Christi) ist 2026 am 04.06.2026; er gilt in Bayern,
 * aber NICHT in Berlin. Damit lässt sich prüfen, dass der HolidayService den
 * Yasumi-Provider aus den Organisations-Einstellungen auflöst.
 */
class HolidayServiceRegionTest extends TestCase {
    use RefreshDatabase;

    private function bindOrganizationWithProvider(?string $provider): void {
        $settings = $provider === null ? [] : ['holidays' => ['provider' => $provider]];
        $org = Organization::factory()->make(['settings' => $settings]);
        app()->instance('currentOrganization', $org);
    }

    public function test_bavaria_org_has_corpus_christi(): void {
        $this->bindOrganizationWithProvider('Germany\\Bavaria');

        $service = new HolidayService;

        $this->assertSame('Germany\\Bavaria', $service->provider());
        $this->assertTrue($service->isHoliday(CarbonImmutable::parse('2026-06-04')));
        $this->assertNotNull($service->nameFor(CarbonImmutable::parse('2026-06-04')));
    }

    public function test_berlin_org_has_no_corpus_christi(): void {
        $this->bindOrganizationWithProvider('Germany\\Berlin');

        $service = new HolidayService;

        $this->assertSame('Germany\\Berlin', $service->provider());
        $this->assertFalse($service->isHoliday(CarbonImmutable::parse('2026-06-04')));
    }

    public function test_shared_nationwide_holiday_applies_in_both_regions(): void {
        // Tag der Deutschen Einheit (03.10.) gilt bundesweit.
        $this->bindOrganizationWithProvider('Germany\\Bavaria');
        $this->assertTrue((new HolidayService)->isHoliday(CarbonImmutable::parse('2026-10-03')));

        $this->bindOrganizationWithProvider('Germany\\Berlin');
        $this->assertTrue((new HolidayService)->isHoliday(CarbonImmutable::parse('2026-10-03')));
    }

    public function test_falls_back_to_config_provider_without_org_override(): void {
        // Keine Org-Override → config('holidays.provider') greift (Default Berlin).
        $this->bindOrganizationWithProvider(null);

        $this->assertSame(
            (string) config('holidays.provider'),
            (new HolidayService)->provider(),
        );
    }

    public function test_cache_is_keyed_per_provider(): void {
        // Erst Bayern auflösen (cached corpusChristi), dann auf Berlin wechseln:
        // der Cache darf den bayerischen Feiertag nicht nach Berlin durchreichen.
        $this->bindOrganizationWithProvider('Germany\\Bavaria');
        $bavaria = new HolidayService;
        $this->assertTrue($bavaria->isHoliday(CarbonImmutable::parse('2026-06-04')));

        $this->bindOrganizationWithProvider('Germany\\Berlin');
        $this->assertFalse($bavaria->isHoliday(CarbonImmutable::parse('2026-06-04')));
    }

    public function test_holiday_regions_registry_is_consistent(): void {
        $this->assertTrue(HolidayRegions::isValid('Germany\\Bavaria'));
        $this->assertTrue(HolidayRegions::isValid('Germany'));
        $this->assertFalse(HolidayRegions::isValid('Atlantis'));
        $this->assertSame('Bayern', HolidayRegions::label('Germany\\Bavaria'));
        $this->assertContains('Germany\\Berlin', HolidayRegions::providers());
    }

    /** Vollaudit 2026-07 (M10): Schweiz inkl. Kantone im DACH-Rechtsraum. */
    public function test_swiss_cantons_are_selectable_and_resolve_holidays(): void {
        $this->assertTrue(HolidayRegions::isValid('Switzerland'));
        $this->assertTrue(HolidayRegions::isValid('Switzerland\\Zurich'));
        $this->assertSame('Zürich', HolidayRegions::label('Switzerland\\Zurich'));

        // Alle registrierten CH-Provider existieren wirklich in Yasumi
        // (unbekannte Provider ergäben stillschweigend leere Feiertagslisten).
        foreach (HolidayRegions::providers() as $provider) {
            if (! str_starts_with($provider, 'Switzerland')) {
                continue;
            }
            $this->assertTrue(
                class_exists('Yasumi\\Provider\\' . $provider),
                "Yasumi-Provider fehlt: {$provider}",
            );
        }

        // Zürich: Tag der Arbeit (1. Mai) und Bundesfeier (1. August) sind
        // Feiertage; der 3. Oktober (DE-Einheit) ist es NICHT.
        $this->bindOrganizationWithProvider('Switzerland\\Zurich');
        $service = new HolidayService;
        $this->assertSame('Switzerland\\Zurich', $service->provider());
        $this->assertTrue($service->isHoliday(CarbonImmutable::parse('2026-05-01')));
        $this->assertTrue($service->isHoliday(CarbonImmutable::parse('2026-08-01')));
        $this->assertFalse($service->isHoliday(CarbonImmutable::parse('2026-10-03')));
    }
}
