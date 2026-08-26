<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WeatherForecastWarningTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Weather;

use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Enums\Weather\WeatherWarningThreshold;
use App\Models\{DiaryEntry, User, WeatherSnapshot, WeatherWarning};
use App\Models\Notification\{NotificationDispatchLog, NotificationRule};
use App\Plugins\Support\PluginApiClient;
use App\Services\Weather\Contracts\WeatherProvider;
use App\Services\Weather\{DwdProvider, OpenMeteoProvider, WeatherService};
use App\Settings\SettingScope;
use App\Support\Setting;
use Carbon\CarbonImmutable;
use GuzzleHttp\{Client, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Wetterwarnungen für disponierte Einsätze (Feature 062, MVP-716 — Vollscan
 * G15): Open-Meteo-Vorhersage über den Guzzle-MockHandler des
 * PluginApiClient (gleiche Naht wie produktiv), Schwellen je Org, genau eine
 * Meldung je Einsatz+Tag+Schwelle, DWD-null-Pfad, kein Ist-Snapshot.
 */
final class WeatherForecastWarningTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        Notification::fake();
        NotificationRule::factory()->forEvent(NotificationEvent::WeatherWarning)->create([
            'organization_id' => $this->organization->id,
            'channels' => [NotificationChannel::InApp->value],
            'notify_affected' => true,
        ]);
    }

    private function client(MockHandler $mock): PluginApiClient {
        return new PluginApiClient('weather-open-meteo', 'https://api.open-meteo.com', new Client(['handler' => HandlerStack::create($mock)]));
    }

    /** Bindet Open-Meteo mit Mock-Transport als Org-Provider. */
    private function bindOpenMeteo(MockHandler $mock): void {
        $client = $this->client($mock);
        $this->app->bind(WeatherProvider::class, static fn(): WeatherProvider => new OpenMeteoProvider($client));
    }

    /**
     * @param  array<string, array<int, float|int>>  $overrides
     */
    private function forecastBody(array $overrides = []): string {
        $today = CarbonImmutable::today();
        $daily = [
            'time' => [$today->toDateString(), $today->addDay()->toDateString(), $today->addDays(2)->toDateString()],
            'temperature_2m_max' => [22.0, 24.0, 31.5],
            'temperature_2m_min' => [12.0, 11.0, 18.0],
            'precipitation_sum' => [0.0, 35.2, 2.0],
            'wind_speed_10m_max' => [20.0, 45.0, 15.0],
            'wind_gusts_10m_max' => [30.0, 82.0, 25.0],
            'weather_code' => [1, 63, 0],
        ];

        return (string) json_encode(['daily' => array_merge($daily, $overrides)]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function dispatchedEntry(array $attributes = []): DiaryEntry {
        return DiaryEntry::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'assigned_user_id' => $this->admin->id,
            'title' => 'Dachmontage Musterstraße',
            'scheduled_for' => CarbonImmutable::today()->addDay()->toDateString(),
            'address_lat' => 52.520000,
            'address_lng' => 13.405000,
        ], $attributes));
    }

    public function test_open_meteo_forecast_parses_daily_rows_keyed_by_date(): void {
        $mock = new MockHandler([new Response(200, [], $this->forecastBody())]);
        $forecast = (new OpenMeteoProvider($this->client($mock)))->forecast(52.52, 13.405, 3);

        $this->assertNotNull($forecast);
        $this->assertCount(3, $forecast);
        $tomorrow = CarbonImmutable::today()->addDay()->toDateString();
        $this->assertSame(35.2, $forecast[$tomorrow]['precipitation_mm']);
        $this->assertSame(82.0, $forecast[$tomorrow]['wind_gust_kmh']);
        $this->assertSame(45.0, $forecast[$tomorrow]['wind_max_kmh']);
        $this->assertSame(63, $forecast[$tomorrow]['weather_code']);
        // Tage werden auf 1–7 geklemmt und als forecast_days übergeben.
        $this->assertStringContainsString('forecast_days=3', (string) $mock->getLastRequest()?->getUri());

        $failing = new OpenMeteoProvider($this->client(new MockHandler([new Response(503, [], 'down')])));
        $this->assertNull($failing->forecast(52.52, 13.405, 3));
    }

    public function test_dwd_provider_has_no_forecast_and_scan_degrades_without_calls(): void {
        // Leerer MockHandler: jeder HTTP-Aufruf würde „Mock queue is empty" werfen.
        $client = $this->client(new MockHandler([]));
        $this->app->bind(WeatherProvider::class, static fn(): WeatherProvider => new DwdProvider($client));
        $this->dispatchedEntry();

        $this->assertNull(app(WeatherService::class)->forecast($this->organization, 52.52, 13.405, 3));
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(0, WeatherWarning::query()->count());
        $this->assertSame(0, NotificationDispatchLog::query()->where('event', NotificationEvent::WeatherWarning->value)->count());
    }

    public function test_scan_warns_exactly_once_per_entry_day_and_threshold(): void {
        // Genau EINE Mock-Antwort: der zweite Lauf muss aus dem Cache kommen.
        $this->bindOpenMeteo(new MockHandler([new Response(200, [], $this->forecastBody())]));
        $entry = $this->dispatchedEntry();

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $tomorrow = CarbonImmutable::today()->addDay()->toDateString();
        $warnings = WeatherWarning::query()->orderBy('threshold')->get();
        $this->assertSame(['gust', 'rain'], $warnings->map(static fn(WeatherWarning $w): string => $w->threshold->value)->all());
        $this->assertTrue($warnings->every(static fn(WeatherWarning $w): bool => $w->diary_entry_id === $entry->id && $w->forecast_date->toDateString() === $tomorrow));
        $this->assertSame('35.20', (string) $warnings->firstWhere('threshold', WeatherWarningThreshold::Rain)?->value);
        $this->assertSame('20.00', (string) $warnings->firstWhere('threshold', WeatherWarningThreshold::Rain)?->limit_value);
        $this->assertSame('open-meteo', $warnings->first()?->provider);

        // Dedupe: eine Meldung je Warnung (Subjekt WeatherWarning), auch nach zwei Läufen.
        $logs = NotificationDispatchLog::query()->where('event', NotificationEvent::WeatherWarning->value)->get();
        $this->assertCount(2, $logs);
        $this->assertSame([WeatherWarning::class], $logs->pluck('subject_type')->unique()->values()->all());

        // Vorhersagen werden nie als Ist-Snapshot gespeichert.
        $this->assertSame(0, WeatherSnapshot::query()->count());
    }

    public function test_thresholds_are_org_configurable_and_frost_and_heat_fire(): void {
        $this->bindOpenMeteo(new MockHandler([new Response(200, [], $this->forecastBody([
            'temperature_2m_min' => [12.0, -2.5, 18.0],
        ]))]));
        Setting::set('weather.warn_rain_mm', 50, SettingScope::Organization, $this->organization);
        Setting::set('weather.warn_gust_kmh', 90, SettingScope::Organization, $this->organization);
        $this->organization->refresh();
        app()->instance('currentOrganization', $this->organization);

        $this->dispatchedEntry(); // morgen: Regen 35 < 50, Böen 82 < 90, Frost −2,5 ≤ 0
        $this->dispatchedEntry(['scheduled_for' => CarbonImmutable::today()->addDays(2)->toDateString()]); // übermorgen: Hitze 31,5 ≥ 30

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $thresholds = WeatherWarning::query()->orderBy('threshold')->get()->map(static fn(WeatherWarning $w): string => $w->threshold->value)->all();
        $this->assertSame(['frost', 'heat'], $thresholds);
    }

    public function test_entries_without_coordinates_outside_horizon_or_disabled_are_ignored(): void {
        // Leerer MockHandler: kein Kandidat darf einen Abruf auslösen.
        $this->bindOpenMeteo(new MockHandler([]));
        $this->dispatchedEntry(['address_lat' => null, 'address_lng' => null, 'customer_id' => null]);
        $this->dispatchedEntry(['scheduled_for' => CarbonImmutable::today()->addDays(5)->toDateString()]);
        $this->dispatchedEntry(['assigned_user_id' => null, 'planned_at' => null]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->assertSame(0, WeatherWarning::query()->count());

        // Org-Schalter aus: auch ein echter Kandidat löst keinen Abruf aus.
        Setting::set('weather.warnings_enabled', false, SettingScope::Organization, $this->organization);
        $this->organization->refresh();
        app()->instance('currentOrganization', $this->organization);
        $this->dispatchedEntry();

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->assertSame(0, WeatherWarning::query()->count());
    }
}
