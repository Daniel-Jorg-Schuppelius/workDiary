<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DwdProviderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Weather;

use App\Models\WeatherSnapshot;
use App\Services\Weather\Contracts\WeatherProvider;
use App\Services\Weather\{DwdProvider, OpenMeteoProvider, WeatherService};
use App\Settings\SettingScope;
use App\Support\Setting;
use CommonToolkit\Helper\FileSystem\FileTypes\ZipFile;
use GuzzleHttp\{Client, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 062, MVP-131 (Bauturbo A7): DWD-Open-Data als zweiter Wetterprovider.
 * HTTP über Guzzle-MockHandler (keine echten Downloads); Stationsliste als
 * eingecheckte Latin-1-Fixture (Festbreiten, Umlaute), Produkt-CSV wird im
 * Test in ein Mini-ZIP verpackt (Toolkit `ZipFile::create`). Geprüft werden
 * Stationslisten-Parsing inkl. Encoding, Haversine-Stationswahl samt
 * Gültigkeitszeitraum und Max-Distanz-Grenzfall, ZIP/CSV-Mapping mit
 * −999 ⇒ NULL, historical-Fallback, Snapshot-Idempotenz/-Unveränderlichkeit
 * und die Provider-Auswahl über das Org-Setting `weather.provider`.
 */
final class DwdProviderTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        // Zeit einfrieren, damit recent-Fenster (500 Tage) und Fixture-Daten
        // dauerhaft zusammenpassen.
        $this->travelTo(Carbon::parse('2025-07-01 12:00:00'));
    }

    private function provider(MockHandler $mock): DwdProvider {
        // PluginApiClient mit Mock-Transport (C10): gleiche Naht wie produktiv.
        return new DwdProvider(new \App\Plugins\Support\PluginApiClient(
            'weather-dwd',
            DwdProvider::BASE,
            new Client(['handler' => HandlerStack::create($mock)]),
        ));
    }

    /** Latin-1-Stationsliste (Festbreiten) — bewusst NICHT nach UTF-8 konvertiert. */
    private function stationsBody(): string {
        return (string) file_get_contents(base_path('tests/Fixtures/weather/KL_Tageswerte_Beschreibung_Stationen.txt'));
    }

    /** Packt die Produkt-CSV-Fixture wie beim DWD in ein ZIP (samt Metadaten-Beifang). */
    private function zipBody(string $productFixture): string {
        $zipPath = sys_get_temp_dir() . '/dwd-test-' . bin2hex(random_bytes(6)) . '.zip';
        ZipFile::create([
            [
                'path' => base_path("tests/Fixtures/weather/$productFixture"),
                'archiveName' => 'produkt_klima_tag_20241201_20250630_00403.txt',
            ],
            [
                'path' => base_path('tests/Fixtures/weather/KL_Tageswerte_Beschreibung_Stationen.txt'),
                'archiveName' => 'Metadaten_Stationsname_00403.txt',
            ],
        ], $zipPath);
        $body = (string) file_get_contents($zipPath);
        @unlink($zipPath);

        return $body;
    }

    public function test_maps_daily_values_from_station_zip(): void {
        $mock = new MockHandler([
            new Response(200, [], $this->stationsBody()),
            new Response(200, [], $this->zipBody('produkt_klima_tag_recent.txt')),
        ]);

        $result = $this->provider($mock)->daily(52.52, 13.405, Carbon::parse('2025-06-15'));

        $this->assertNotNull($result);
        $this->assertSame(12.1, $result['temp_min']);
        $this->assertSame(24.5, $result['temp_max']);
        $this->assertSame(3.4, $result['precipitation_mm']);
        $this->assertSame(45.0, $result['wind_gust_kmh']); // FX 12,5 m/s → km/h übers Toolkit.
        $this->assertNull($result['weather_code']); // KL-Tageswerte führen keinen WMO-Code.
        $this->assertSame('Quelle: Deutscher Wetterdienst', $result['raw']['attribution']); // CC-BY-Pflicht.
        $this->assertSame('CC BY 4.0', $result['raw']['license']);
        $this->assertSame(403, $result['raw']['station_id']);
        $this->assertSame('Berlin-Dahlem (FU)', $result['raw']['station_name']);
        $this->assertEqualsWithDelta(10.2, $result['raw']['distance_km'], 1.0);
        $this->assertStringContainsString('recent/tageswerte_KL_00403_akt.zip', (string) $mock->getLastRequest()?->getUri());
    }

    public function test_missing_values_minus_999_become_null(): void {
        $mock = new MockHandler([
            new Response(200, [], $this->stationsBody()),
            new Response(200, [], $this->zipBody('produkt_klima_tag_recent.txt')),
        ]);

        $result = $this->provider($mock)->daily(52.52, 13.405, Carbon::parse('2025-06-14'));

        $this->assertNotNull($result);
        $this->assertSame(9.8, $result['temp_min']);
        $this->assertSame(19.0, $result['temp_max']);
        $this->assertNull($result['precipitation_mm']); // RSK −999
        $this->assertNull($result['wind_gust_kmh']);    // FX −999
        $this->assertNull($result['raw']['sunshine_hours']); // SDK −999
    }

    public function test_haversine_skips_nearer_station_whose_period_ended(): void {
        // Abfragepunkt = exakt Berlin-Alexanderplatz (Messende 1970): die
        // Stationswahl muss auf das weiter entfernte, aktive Dahlem fallen.
        $mock = new MockHandler([
            new Response(200, [], $this->stationsBody()),
            new Response(200, [], $this->zipBody('produkt_klima_tag_recent.txt')),
        ]);

        $result = $this->provider($mock)->daily(52.5211, 13.4106, Carbon::parse('2025-06-15'));

        $this->assertNotNull($result);
        $this->assertSame(403, $result['raw']['station_id']);
    }

    public function test_returns_null_beyond_max_station_distance(): void {
        $mock = new MockHandler([
            new Response(200, [], $this->stationsBody()),
            new Response(200, [], $this->zipBody('produkt_klima_tag_recent.txt')),
        ]);
        $provider = $this->provider($mock);

        // Kiel: nächste aktive Station (Berlin-Dahlem) liegt ~300 km entfernt.
        $this->assertNull($provider->daily(54.32, 10.12, Carbon::parse('2025-06-15')));
        $this->assertSame(1, $mock->count()); // ZIP wurde NICHT abgerufen — kein Wert statt falscher Daten.

        // Grenzfall per Org-Setting: Dahlem (~10 km) fällt aus einem 5-km-Radius.
        Setting::set('weather.dwd_max_station_km', 5, SettingScope::Organization, $this->organization);
        $this->assertNull($provider->daily(52.52, 13.405, Carbon::parse('2025-06-15')));
        $this->assertSame(1, $mock->count()); // Stationsliste kommt aus dem Cache, ZIP weiterhin unangetastet.
    }

    public function test_station_list_latin1_umlauts_are_decoded(): void {
        $mock = new MockHandler([
            new Response(200, [], $this->stationsBody()),
            new Response(200, [], $this->zipBody('produkt_klima_tag_recent.txt')),
        ]);

        $result = $this->provider($mock)->daily(47.81, 7.64, Carbon::parse('2025-06-15'));

        $this->assertNotNull($result);
        $this->assertSame('Müllheim', $result['raw']['station_name']);
    }

    public function test_old_dates_fall_back_to_historical_archive(): void {
        $listing = '<html><a href="tageswerte_KL_00403_19500101_20241231_hist.zip">zip</a></html>';
        $mock = new MockHandler([
            new Response(200, [], $this->stationsBody()),
            new Response(200, [], $listing),
            new Response(200, [], $this->zipBody('produkt_klima_tag_historical.txt')),
        ]);

        $result = $this->provider($mock)->daily(52.52, 13.405, Carbon::parse('2020-05-10'));

        $this->assertNotNull($result);
        $this->assertSame(20.4, $result['temp_max']);
        $this->assertSame(36.0, $result['wind_gust_kmh']); // FX 10,0 m/s → km/h.
        $this->assertStringContainsString('historical/tageswerte_KL_00403_19500101_20241231_hist.zip', (string) $mock->getLastRequest()?->getUri());
    }

    public function test_snapshot_via_weather_service_is_idempotent_and_immutable(): void {
        // Nur EIN Antwortpaar: ein Zweitabruf würde „Mock queue is empty" werfen.
        $service = new WeatherService($this->provider(new MockHandler([
            new Response(200, [], $this->stationsBody()),
            new Response(200, [], $this->zipBody('produkt_klima_tag_recent.txt')),
        ])));

        $first = $service->snapshot($this->organization, 52.52, 13.405, Carbon::parse('2025-06-15'));
        $second = $service->snapshot($this->organization, 52.52, 13.405, Carbon::parse('2025-06-15'));

        $this->assertNotNull($first);
        $this->assertSame('dwd', $first->provider);
        $this->assertSame('24.50', $first->temp_max);
        $this->assertSame('Quelle: Deutscher Wetterdienst', $first->raw['attribution']);
        $this->assertSame($first->id, $second?->id);
        $this->assertSame(1, WeatherSnapshot::query()->count());

        $this->expectException(RuntimeException::class);
        $first->update(['temp_max' => 99]);
    }

    public function test_org_setting_selects_the_weather_provider(): void {
        $this->assertInstanceOf(OpenMeteoProvider::class, app(WeatherProvider::class)); // Default.

        Setting::set('weather.provider', 'dwd', SettingScope::Organization, $this->organization);
        $this->assertInstanceOf(DwdProvider::class, app(WeatherProvider::class));

        Setting::reset('weather.provider', SettingScope::Organization, $this->organization);
        $this->assertInstanceOf(OpenMeteoProvider::class, app(WeatherProvider::class));
    }
}
