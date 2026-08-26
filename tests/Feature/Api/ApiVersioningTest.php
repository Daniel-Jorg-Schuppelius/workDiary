<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApiVersioningTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\DeprecatedApiAlias;
use App\Models\{Customer, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-717 (Vollscan J10): kanonische `/api/v1/…`-Pfade, unversionierter
 * Kompatibilitäts-Alias mit Deprecation/Sunset-Header, Ingest unverändert.
 */
final class ApiVersioningTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
    }

    public function test_named_api_routes_live_under_v1_and_legacy_alias_mirrors_them(): void {
        $this->assertSame('/api/v1/customers', parse_url(route('api.customers.index'), PHP_URL_PATH));
        $this->assertSame('/api/customers', parse_url(route('api.legacy.customers.index'), PHP_URL_PATH));

        $canonical = [];
        $legacy = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();
            if (str_starts_with($name, 'api.legacy.')) {
                $legacy[substr($name, strlen('api.legacy.'))] = $route->uri();
            } elseif (str_starts_with($name, 'api.') && str_starts_with($route->uri(), 'api/v1/')) {
                $canonical[substr($name, strlen('api.'))] = $route->uri();
            }
        }

        $this->assertNotEmpty($canonical);
        $this->assertSame(array_keys($canonical), array_keys($legacy), 'Alias-Gruppe muss jede v1-Route spiegeln.');
        foreach ($canonical as $key => $uri) {
            $this->assertSame('api/v1/' . substr($legacy[$key], strlen('api/')), $uri, $key);
        }
    }

    public function test_v1_and_legacy_path_return_identical_payload_but_only_legacy_is_deprecated(): void {
        Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Alias GmbH']);
        Sanctum::actingAs($this->admin, ['customers:read']);

        $v1 = $this->getJson('/api/v1/customers')->assertOk();
        $legacy = $this->getJson('/api/customers')->assertOk();

        $this->assertSame($v1->json('data'), $legacy->json('data'));
        $this->assertSame('Alias GmbH', $v1->json('data.0.name'));

        $v1->assertHeaderMissing('Deprecation');
        $v1->assertHeaderMissing('Sunset');
        $legacy->assertHeader('Deprecation', 'true');
        $legacy->assertHeader('Sunset', DeprecatedApiAlias::SUNSET_HTTP_DATE);
        $this->assertStringContainsString('/api/v1/customers>; rel="successor-version"', (string) $legacy->headers->get('Link'));
    }

    public function test_ability_scope_is_enforced_on_both_paths(): void {
        Sanctum::actingAs($this->admin, ['diary:read']);

        $this->getJson('/api/v1/customers')->assertForbidden();
        $this->getJson('/api/customers')->assertForbidden();
    }

    public function test_ingest_endpoints_stay_unversioned(): void {
        foreach (['api.terminal.ingest', 'api.location.ingest', 'api.cti.webhook', 'api.patrol.scan'] as $name) {
            $uri = Route::getRoutes()->getByName($name)?->uri();
            $this->assertNotNull($uri, $name);
            $this->assertStringStartsNotWith('api/v1/', (string) $uri, $name);
            $this->assertNull(Route::getRoutes()->getByName(str_replace('api.', 'api.legacy.', $name)), $name . ' darf keinen Alias haben');
        }
    }
}
