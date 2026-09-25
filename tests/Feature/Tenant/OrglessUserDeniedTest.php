<?php
/*
 * Created on   : Sun Sep 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrglessUserDeniedTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Tenant;

use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Mandanten-Gate „kein Kontext, kein Zugriff" (Mandanten-Review 2026-09-13):
 * Der OrganizationScope filtert nur, wenn `currentOrganization` gebunden ist.
 * Ein angemeldeter Nutzer ohne Organisation bekam keine Bindung und sah über
 * die globale Suche die Kunden ALLER Mandanten (per Sonde belegt); die 423 der
 * Modul-Routen kamen nur zufällig vom Plan-Gate. Seitdem weist
 * SetOrganizationContext solche Nutzer auf JEDER authentifizierten Web- und
 * API-Route ab — nur Abmelden, Installer und Legacy bleiben erreichbar.
 */
class OrglessUserDeniedTest extends TestCase {
    use RefreshDatabase;

    /** Route-Namen(-Präfixe), die ohne Organisation erreichbar bleiben müssen. */
    private const EXEMPT_PREFIXES = ['logout', 'legacy.', 'install.'];

    protected function setUp(): void {
        parent::setUp();
        $organization = Organization::factory()->create();
        Customer::factory()->create(['organization_id' => $organization->id, 'name' => 'Fremdkunde Sichtbar']);
        app()->forgetInstance('currentOrganization');
    }

    private function orgless(): User {
        return User::factory()->user()->create(['organization_id' => null]);
    }

    public function test_global_search_no_longer_leaks_other_tenants(): void {
        $response = $this->actingAs($this->orgless())->get(route('search.index', ['q' => 'Fremdkunde']));

        $response->assertForbidden();
        $response->assertDontSee('Fremdkunde Sichtbar');
        $this->assertFalse(app()->bound('currentOrganization'));
    }

    public function test_json_requests_get_a_typed_error(): void {
        $this->actingAs($this->orgless())
            ->getJson(route('dashboard'))
            ->assertForbidden()
            ->assertJsonPath('error', 'organization_missing');
    }

    public function test_orgless_user_can_still_log_out(): void {
        // Bewusst der Pfad: vier Routen tragen den Namen „logout" (Web, Portal,
        // Legacy, Hinweisgeber), route('logout') träfe die zuletzt registrierte.
        $this->actingAs($this->orgless())->post('/logout')->assertRedirect();
        $this->assertGuest();
    }

    public function test_orgless_user_has_no_access_to_the_new_system(): void {
        $this->assertFalse($this->orgless()->canAccessNew());
        $this->assertTrue(User::factory()->platformAdmin()->create(['organization_id' => null])->canAccessNew());
    }

    public function test_platform_admin_without_own_organization_keeps_working(): void {
        $admin = User::factory()->platformAdmin()->create(['organization_id' => null]);

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $this->assertTrue(app()->bound('currentOrganization'));
    }

    public function test_legacy_shadow_account_is_sent_to_the_legacy_area(): void {
        config()->set('database.connections.legacy.database', 'legacy_probe');
        $shadow = User::factory()->legacyOnly()->create(['organization_id' => null]);

        $this->actingAs($shadow)->get(route('dashboard'))->assertRedirect(route('legacy.diary.index'));
    }

    public function test_every_authenticated_web_get_route_denies_orgless_users(): void {
        $actor = $this->orgless();
        [$checked, $failures] = $this->sweep('web', 'auth', fn (string $uri) => $this->actingAs($actor)->get($uri)->status());

        $this->assertGreaterThan(100, $checked, 'Route-Sweep hat zu wenige Routen erfasst');
        $this->assertSame([], $failures, "Authentifizierte Web-Routen ohne Org-Kontext erreichbar:\n" . implode("\n", $failures));
    }

    public function test_every_sanctum_api_get_route_denies_orgless_users(): void {
        $actor = $this->orgless();
        Sanctum::actingAs($actor, ['*']);
        [$checked, $failures] = $this->sweep('api', 'auth:sanctum', fn (string $uri) => $this->getJson($uri)->status());

        $this->assertGreaterThan(20, $checked, 'Route-Sweep hat zu wenige API-Routen erfasst');
        $this->assertSame([], $failures, "Sanctum-API-Routen ohne Org-Kontext erreichbar:\n" . implode("\n", $failures));
    }

    /**
     * Alle GET-Routen der Gruppe mit dem Auth-Middleware-Eintrag aufrufen;
     * Routenparameter werden passend zu ihren where-Constraints gefüllt — die
     * Abweisung greift VOR dem Model-Binding, ein 404 wäre also ein Befund.
     *
     * @param  callable(string): int  $request
     * @return array{int, list<string>}
     */
    private function sweep(string $group, string $auth, callable $request): array {
        $checked = 0;
        $failures = [];
        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            $middleware = $route->gatherMiddleware();
            if (! in_array($group, $middleware, true) || ! in_array($auth, $middleware, true)) {
                continue;
            }
            $name = (string) $route->getName();
            if ($this->isExempt($name)) {
                continue;
            }
            $uri = '/' . ltrim((string) preg_replace_callback('/\{(\w+)\??\}/', static function (array $m) use ($route): string {
                $where = $route->wheres[$m[1]] ?? null;
                // `timesheet`: Treffer-Sprung der Tätigkeitsrecherche (search.open, type = time_entry|timesheet).
                // `sign`: Übergangsdialog der Protokolle (protocols.transition-form, MVP-883).
                foreach (['1', 'a', 'csv', 'block', 'timesheet', 'sign'] as $candidate) {
                    if (! is_string($where) || preg_match('#^(?:' . $where . ')$#', $candidate) === 1) {
                        return $candidate;
                    }
                }

                return '1';
            }, $route->uri()), '/');
            if (! $route->matches(Request::create($uri))) {
                $this->fail(sprintf('Route %s [%s] ließ sich nicht adressieren — Kandidatenliste im Sweep erweitern.', $name, $route->uri()));
            }
            $status = $request($uri);
            $checked++;
            if ($status !== 403) {
                $failures[] = sprintf('%s [%s] -> %d', $name !== '' ? $name : $uri, $uri, $status);
            }
            app()->forgetInstance('currentOrganization');
        }
        sort($failures);

        return [$checked, $failures];
    }

    private function isExempt(string $name): bool {
        foreach (self::EXEMPT_PREFIXES as $prefix) {
            if ($name === rtrim($prefix, '.') || str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
