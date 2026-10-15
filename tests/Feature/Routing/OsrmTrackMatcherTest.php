<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OsrmTrackMatcherTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Routing;

use App\Services\Routing\OsrmTrackMatcher;
use Psr\Http\Message\RequestInterface;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** Kartenabgleich einer GPS-Spur über OSRM `/match` (MVP-1185). */
class OsrmTrackMatcherTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        config()->set('routing.osrm.base_url', 'http://osrm.test');
        config()->set('routing.osrm.profile', 'driving');
    }

    public function test_sums_matched_distance_in_km(): void {
        $fake = FakePluginHttp::fake([
            'http://osrm.test/*' => FakePluginHttp::response([
                'code' => 'Ok',
                'matchings' => [['distance' => 1500.4], ['distance' => 520.0]],
                'tracepoints' => [],
            ]),
        ]);

        $km = $this->app->make(OsrmTrackMatcher::class)->distanceKm([
            ['lat' => 52.0, 'lng' => 13.0],
            ['lat' => 52.01, 'lng' => 13.01],
            ['lat' => 52.02, 'lng' => 13.02],
        ]);

        $this->assertSame(2.02, $km);
        $fake->assertSent(static function (RequestInterface $req): bool {
            $uri = (string) $req->getUri();

            return str_contains($uri, '/match/v1/driving/13.000000,52.000000;') && str_contains($uri, 'gaps=ignore');
        });
    }

    public function test_returns_null_without_matching(): void {
        FakePluginHttp::fake([
            'http://osrm.test/*' => FakePluginHttp::response(['code' => 'NoMatch', 'message' => 'Could not match the trace.']),
        ]);

        $this->assertNull($this->app->make(OsrmTrackMatcher::class)->distanceKm([
            ['lat' => 52.0, 'lng' => 13.0],
            ['lat' => 52.01, 'lng' => 13.01],
        ]));
    }

    public function test_thins_long_tracks_to_osrm_limit(): void {
        $fake = FakePluginHttp::fake([
            'http://osrm.test/*' => FakePluginHttp::response(['code' => 'Ok', 'matchings' => [['distance' => 1000.0]]]),
        ]);
        $points = [];
        for ($i = 0; $i < 350; $i++) {
            $points[] = ['lat' => 52.0 + $i * 0.0001, 'lng' => 13.0 + $i * 0.0001];
        }

        $this->assertSame(1.0, $this->app->make(OsrmTrackMatcher::class)->distanceKm($points));
        $fake->assertSent(static function (RequestInterface $req): bool {
            $path = $req->getUri()->getPath();

            return substr_count($path, ';') === OsrmTrackMatcher::MAX_POINTS - 1
                && str_contains($path, '13.034900,52.034900'); // letzter Punkt bleibt
        });
    }

    public function test_single_point_yields_null(): void {
        $this->assertNull($this->app->make(OsrmTrackMatcher::class)->distanceKm([['lat' => 52.0, 'lng' => 13.0]]));
    }
}
