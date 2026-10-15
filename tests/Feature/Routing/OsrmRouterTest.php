<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OsrmRouterTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Routing;

use App\Services\Routing\{OsrmRouter, RoutingException};
use Psr\Http\Message\RequestInterface;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

class OsrmRouterTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        config()->set('routing.osrm.base_url', 'http://osrm.test');
        config()->set('routing.osrm.profile', 'driving');
    }

    private function router(): OsrmRouter {
        return $this->app->make(OsrmRouter::class);
    }

    public function test_returns_route_result(): void {
        $fake = FakePluginHttp::fake([
            'http://osrm.test/*' => FakePluginHttp::response([
                'code' => 'Ok',
                'routes' => [[
                    'distance' => 12345.6,
                    'duration' => 678.9,
                    'geometry' => ['type' => 'LineString', 'coordinates' => [[13.0, 52.0], [13.1, 52.1]]],
                    'legs' => [['summary' => 'test']],
                ]],
            ]),
        ]);

        $result = $this->router()->route([[13.0, 52.0], [13.1, 52.1]]);

        $this->assertSame(12346, $result->distanceMeters);
        $this->assertSame(679, $result->durationSeconds);
        $this->assertSame(12.35, $result->distanceKm());
        $this->assertSame(12, $result->durationMinutes());
        $this->assertNotNull($result->geometry);

        $fake->assertSent(function (RequestInterface $req): bool {
            return str_contains((string) $req->getUri(), '/route/v1/driving/');
        });
    }

    public function test_requires_two_coordinates(): void {
        $this->expectException(RoutingException::class);
        $this->router()->route([[13.0, 52.0]]);
    }

    public function test_throws_on_non_ok(): void {
        FakePluginHttp::fake([
            'http://osrm.test/*' => FakePluginHttp::response(['code' => 'NoRoute', 'routes' => []]),
        ]);

        $this->expectException(RoutingException::class);
        $this->router()->route([[13.0, 52.0], [13.1, 52.1]]);
    }

    /** MVP-1171: `/table` liefert Distanz- und Dauer-Matrix; Koordinaten gehen als lng,lat. */
    public function test_table_returns_distance_and_duration_matrices(): void {
        $fake = FakePluginHttp::fake([
            'http://osrm.test/*' => FakePluginHttp::response([
                'code' => 'Ok',
                'distances' => [[0, 1000], [1000, 0]],
                'durations' => [[0, 90.4], [null, 0]],
            ]),
        ]);

        $table = $this->router()->table([[13.0, 52.0], [13.1, 52.1]]);

        $this->assertSame(1000.0, $table->distances[0][1]);
        $this->assertSame(90.4, $table->durations[0][1]);
        $this->assertInfinite($table->durations[1][0]);
        $fake->assertSent(function (RequestInterface $req): bool {
            $uri = (string) $req->getUri();

            return str_contains($uri, '/table/v1/driving/13.000000,52.000000;13.100000,52.100000')
                && str_contains($uri, 'annotations=duration');
        });
    }

    public function test_table_without_durations_is_invalid(): void {
        FakePluginHttp::fake([
            'http://osrm.test/*' => FakePluginHttp::response(['code' => 'Ok', 'distances' => [[0, 1], [1, 0]]]),
        ]);

        $this->expectException(RoutingException::class);
        $this->router()->table([[13.0, 52.0], [13.1, 52.1]]);
    }
}
