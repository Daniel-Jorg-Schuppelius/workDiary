<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GeocodeControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Routing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

class GeocodeControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        config()->set('routing.nominatim.base_url', 'http://nominatim.test');
        config()->set('routing.nominatim.rate_limit_per_sec', 1000);
    }

    public function test_requires_authentication(): void {
        $this->postJson(route('api.internal.geocode'), ['query' => 'Berlin'])
            ->assertStatus(401);
    }

    public function test_returns_coordinates(): void {
        FakePluginHttp::fake([
            'http://nominatim.test/*' => FakePluginHttp::response([
                ['lat' => '52.52', 'lon' => '13.405', 'display_name' => 'Berlin'],
            ], 200),
        ]);

        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->postJson(route('api.internal.geocode'), ['query' => 'Berlin'])
            ->assertOk()
            ->assertJson([
                'lat' => 52.52,
                'lng' => 13.405,
                'display_name' => 'Berlin',
                'provider' => 'nominatim',
            ]);
    }

    public function test_returns_404_on_no_match(): void {
        FakePluginHttp::fake([
            'http://nominatim.test/*' => FakePluginHttp::response([], 200),
        ]);

        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->postJson(route('api.internal.geocode'), ['query' => 'NoSuchPlace-XYZ'])
            ->assertStatus(404);
    }

    public function test_validates_query(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->postJson(route('api.internal.geocode'), ['query' => 'ab'])
            ->assertStatus(422);
    }
}
