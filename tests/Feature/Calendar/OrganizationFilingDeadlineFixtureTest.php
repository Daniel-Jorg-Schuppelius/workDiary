<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationFilingDeadlineFixtureTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Enums\Finance\VatFilingInterval;
use App\Models\Accounting\AccountingVatExtension;
use App\Models\Platform\{Holiday, Organization};
use App\Services\Accounting\Filing\VatReturnPeriod;
use App\Services\Accounting\VatFilingProfileResolver;
use App\Services\Calendar\Filing\FilingDeadlineCalculator;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\JsonHelper;
use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Eingefrorene Fristen der eigenen Organisation (Feature 197, MVP-1631):
 * erzeugt am 2026-11-12 mit dem Rechner im Finanzmodul VOR seinem Umzug in den
 * Kalender. Der Umzug darf keinen dieser Termine ändern — Voranmeldung je Monat
 * und Quartal mit und ohne Dauerfristverlängerung, Zusammenfassende Meldung,
 * Sondervorauszahlung und Jahreserklärung (beraten und unberaten) für die
 * Zeiträume 2025–2027, je für Berlin, Bayern und Berlin mit eigenen
 * Feiertagen der Organisation (die für die eigenen Fristen weiter zählen).
 * Nach dem Umzug läuft derselbe Fall über das Profil aus
 * {@see VatFilingProfileResolver::filingProfile()} — wie im Finanzmodul.
 *
 * Neu erzeugen nur mit `FREEZE_FILING_DEADLINES=1` und nur, wenn sich das Recht
 * ändert — nie, damit ein Umbau grün wird.
 */
final class OrganizationFilingDeadlineFixtureTest extends TestCase {
    use RefreshDatabase;

    private const string FIXTURE = 'tests/Fixtures/filing-deadlines/organization-2025-2027.json';

    /** @var list<string> Rechtsräume; `+betrieb` = Berlin mit eigenen Feiertagen der Organisation (zählen für die eigenen Fristen mit). */
    private const array REGIONS = ['Germany\\Berlin', 'Germany\\Bavaria', 'Germany\\Berlin+betrieb'];

    /** @var list<int> */
    private const array YEARS = [2025, 2026, 2027];

    public function test_the_organizations_own_deadlines_stay_frozen(): void {
        $actual = $this->compute();
        $path = base_path(self::FIXTURE);

        if (getenv('FREEZE_FILING_DEADLINES') === '1') {
            File::write($path, JsonHelper::encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        }

        $this->assertFileExists($path);
        $expected = JsonHelper::decode(File::read($path), true);
        $this->assertIsArray($expected);
        $this->assertSame($expected, $actual);
    }

    /** @return array<string, string> Fall → Datum */
    private function compute(): array {
        $out = [];
        foreach (self::REGIONS as $region) {
            foreach ([false, true] as $extension) {
                $organization = Organization::factory()->create(['settings' => ['holidays' => ['provider' => explode('+', $region)[0]]]]);
                app()->instance('currentOrganization', $organization);
                if (str_ends_with($region, '+betrieb')) {
                    // Erfundene Betriebsfeiertage auf Fristtagen (Mittwoch 10.06.2026, Mittwoch 10.03.2027).
                    Holiday::query()->create(['organization_id' => $organization->id, 'date' => '2026-06-10', 'name' => 'Betriebsfeier', 'is_recurring' => false]);
                    Holiday::query()->create(['organization_id' => $organization->id, 'date' => '2027-03-10', 'name' => 'Betriebsfeier', 'is_recurring' => false]);
                }
                if ($extension) {
                    AccountingVatExtension::query()->create(['organization_id' => $organization->id, 'year' => 2024, 'granted_on' => '2024-02-01']);
                }
                $calculator = app(FilingDeadlineCalculator::class);
                $resolver = app(VatFilingProfileResolver::class);
                $prefix = $region . ($extension ? '|ext' : '|plain');
                foreach (self::YEARS as $year) {
                    for ($month = 1; $month <= 12; $month++) {
                        $period = VatReturnPeriod::make(VatFilingInterval::Monthly, $year, $month);
                        $out[$prefix . '|vat|' . $period->key] = $calculator->vatAdvance($resolver->filingProfile($organization, $period->to), $period->periodKey())->toDateString();
                        $out[$prefix . '|zm|' . $period->key] = $calculator->recapitulative($resolver->filingProfile($organization, $period->to), $period->periodKey())->toDateString();
                    }
                    for ($quarter = 1; $quarter <= 4; $quarter++) {
                        $period = VatReturnPeriod::make(VatFilingInterval::Quarterly, $year, $quarter);
                        $out[$prefix . '|vat|' . $period->key] = $calculator->vatAdvance($resolver->filingProfile($organization, $period->to), $period->periodKey())->toDateString();
                        $out[$prefix . '|zm|' . $period->key] = $calculator->recapitulative($resolver->filingProfile($organization, $period->to), $period->periodKey())->toDateString();
                    }
                    $out[$prefix . '|svz|' . $year] = $calculator->specialPrepayment($resolver->filingProfile($organization, CarbonImmutable::create($year, 2, 10)), $year)->toDateString();
                    $out[$prefix . '|annual|' . $year] = $calculator->annualReturn($resolver->filingProfile($organization, CarbonImmutable::create($year, 12, 31), false), $year)->toDateString();
                    $out[$prefix . '|annual-advised|' . $year] = $calculator->annualReturn($resolver->filingProfile($organization, CarbonImmutable::create($year, 12, 31), true), $year)->toDateString();
                }
                app()->forgetInstance('currentOrganization');
            }
        }
        ksort($out);

        return $out;
    }
}
