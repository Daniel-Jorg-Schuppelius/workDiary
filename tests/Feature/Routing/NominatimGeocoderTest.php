<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NominatimGeocoderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Routing;

use App\Models\GeocodeCache;
use App\Services\Routing\{GeocodingException, NominatimGeocoder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

class NominatimGeocoderTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('routing.nominatim.base_url', 'http://nominatim.test');
        config()->set('routing.nominatim.user_agent', 'workDiary-tests');
        config()->set('routing.nominatim.email', 'test@example.com');
        config()->set('routing.nominatim.rate_limit_per_sec', 1000);
        config()->set('routing.cache.ttl_days', 30);
    }

    private function geocoder(): NominatimGeocoder {
        return $this->app->make(NominatimGeocoder::class);
    }

    public function test_resolves_and_caches_result(): void {
        $fake = FakePluginHttp::fake([
            'http://nominatim.test/*' => FakePluginHttp::response([
                [
                    'lat' => '52.5200',
                    'lon' => '13.4050',
                    'display_name' => 'Berlin, Deutschland',
                ],
            ]),
        ]);

        $result = $this->geocoder()->geocode('Berlin');

        $this->assertNotNull($result);
        $this->assertSame(52.52, $result->lat);
        $this->assertSame(13.405, $result->lng);
        $this->assertFalse($result->fromCache);
        $this->assertDatabaseHas('geocode_cache', ['query' => 'Berlin']);

        $fake->assertSentCount(1);
        $fake->assertSent(function (RequestInterface $req): bool {
            return str_starts_with((string) $req->getUri(), 'http://nominatim.test/search')
                && $req->getHeaderLine('User-Agent') === 'workDiary-tests';
        });
    }

    public function test_second_lookup_is_served_from_cache(): void {
        GeocodeCache::query()->create([
            'query_hash' => GeocodeCache::hashFor('Hamburg'),
            'query' => 'Hamburg',
            'address_formatted' => 'Hamburg, Deutschland',
            'lat' => 53.5511,
            'lng' => 9.9937,
            'provider' => 'nominatim',
            'raw' => [],
        ]);

        $fake = FakePluginHttp::fake();
        $result = $this->geocoder()->geocode('Hamburg');

        $this->assertNotNull($result);
        $this->assertTrue($result->fromCache);
        $fake->assertNothingSent();
    }

    public function test_returns_null_when_no_match(): void {
        FakePluginHttp::fake([
            'http://nominatim.test/*' => FakePluginHttp::response([]),
        ]);

        $this->assertNull($this->geocoder()->geocode('Nowheresville-123'));
        $this->assertDatabaseCount('geocode_cache', 0);
    }

    public function test_throws_when_provider_unavailable(): void {
        FakePluginHttp::fake([
            'http://nominatim.test/*' => FakePluginHttp::response('boom', 500),
        ]);

        $this->expectException(GeocodingException::class);
        $this->geocoder()->geocode('Berlin');
    }
}
