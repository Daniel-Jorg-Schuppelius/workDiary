<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WeatherServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Weather;

use App\Models\WeatherSnapshot;
use App\Services\Weather\{OpenMeteoProvider, WeatherService};
use GuzzleHttp\{Client, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 062, MVP-131: Wetter-Snapshots. HTTP über Guzzle-MockHandler; prüft
 * Parsing/Speicherung, Idempotenz (kein Zweitabruf), grazile Ausfallbehandlung
 * (null statt Exception) und die Unveränderlichkeit des Snapshots.
 */
final class WeatherServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function service(MockHandler $mock): WeatherService {
        // PluginApiClient mit Mock-Transport (C10): gleiche Naht wie produktiv.
        $client = new \App\Plugins\Support\PluginApiClient(
            'weather-open-meteo',
            'https://api.open-meteo.com',
            new Client(['handler' => HandlerStack::create($mock)]),
        );

        return new WeatherService(new OpenMeteoProvider($client));
    }

    private function okBody(): string {
        return (string) json_encode([
            'daily' => [
                'time' => ['2025-06-15'],
                'temperature_2m_max' => [24.5],
                'temperature_2m_min' => [12.1],
                'precipitation_sum' => [3.4],
                'wind_gusts_10m_max' => [45.0],
                'weather_code' => [61],
            ],
        ]);
    }

    public function test_fetches_and_stores_immutable_snapshot(): void {
        $service = $this->service(new MockHandler([new Response(200, [], $this->okBody())]));

        $snap = $service->snapshot($this->organization, 52.52, 13.405, Carbon::parse('2025-06-15'));

        $this->assertInstanceOf(WeatherSnapshot::class, $snap);
        $this->assertSame('open-meteo', $snap->provider);
        $this->assertSame('24.50', $snap->temp_max);
        $this->assertSame('12.10', $snap->temp_min);
        $this->assertSame('3.40', $snap->precipitation_mm);
        $this->assertSame(61, $snap->weather_code);
        $this->assertNotNull($snap->fetched_at);
        $this->assertSame(['time' => ['2025-06-15']], ['time' => $snap->raw['daily']['time']]);
    }

    public function test_snapshot_is_idempotent_no_second_fetch(): void {
        // Nur EINE Mock-Antwort: ein Zweitabruf würde „Mock queue is empty" werfen.
        $service = $this->service(new MockHandler([new Response(200, [], $this->okBody())]));

        $first = $service->snapshot($this->organization, 52.52, 13.405, Carbon::parse('2025-06-15'));
        $second = $service->snapshot($this->organization, 52.52, 13.405, Carbon::parse('2025-06-15'));

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second?->id);
        $this->assertSame(1, WeatherSnapshot::query()->count());
    }

    public function test_provider_failure_returns_null_without_blocking(): void {
        $service = $this->service(new MockHandler([new Response(503)]));

        $snap = $service->snapshot($this->organization, 52.52, 13.405, Carbon::parse('2025-06-15'));

        $this->assertNull($snap);
        $this->assertSame(0, WeatherSnapshot::query()->count());
    }

    public function test_snapshot_cannot_be_modified_afterwards(): void {
        $service = $this->service(new MockHandler([new Response(200, [], $this->okBody())]));
        $snap = $service->snapshot($this->organization, 52.52, 13.405, Carbon::parse('2025-06-15'));

        $this->expectException(RuntimeException::class);
        $snap?->update(['temp_max' => 99]);
    }
}
