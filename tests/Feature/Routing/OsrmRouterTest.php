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

use App\Services\Routing\OsrmRouter;
use App\Services\Routing\RoutingException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
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
        Http::fake([
            'osrm.test/*' => Http::response([
                'code' => 'Ok',
                'routes' => [[
                    'distance' => 12345.6,
                    'duration' => 678.9,
                    'geometry' => ['type' => 'LineString', 'coordinates' => [[13.0, 52.0], [13.1, 52.1]]],
                    'legs' => [['summary' => 'test']],
                ]],
            ], 200),
        ]);

        $result = $this->router()->route([[13.0, 52.0], [13.1, 52.1]]);

        $this->assertSame(12346, $result->distanceMeters);
        $this->assertSame(679, $result->durationSeconds);
        $this->assertSame(12.35, $result->distanceKm());
        $this->assertSame(12, $result->durationMinutes());
        $this->assertNotNull($result->geometry);

        Http::assertSent(function (ClientRequest $req): bool {
            return str_contains($req->url(), '/route/v1/driving/');
        });
    }

    public function test_requires_two_coordinates(): void {
        $this->expectException(RoutingException::class);
        $this->router()->route([[13.0, 52.0]]);
    }

    public function test_throws_on_non_ok(): void {
        Http::fake([
            'osrm.test/*' => Http::response(['code' => 'NoRoute', 'routes' => []], 200),
        ]);

        $this->expectException(RoutingException::class);
        $this->router()->route([[13.0, 52.0], [13.1, 52.1]]);
    }
}
