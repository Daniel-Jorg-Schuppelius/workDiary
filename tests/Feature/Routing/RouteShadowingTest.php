<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RouteShadowingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Routing;

use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Gate gegen verdeckte Routen. Der Router nimmt die erste passende Route; ein
 * fester Pfad hinter einer Parameter-Route wird nie erreicht — der feste Teil
 * gilt dann als Parameterwert. Fund 2026-10-04: `sign/timesheet/thanks` lag
 * hinter `sign/timesheet/{token}` (Danke-Seite nach der Kundensignatur 404),
 * `inbox/group/dismiss` hinter `inbox/{item}/dismiss`. Die Tests prüften nur
 * das Weiterleitungsziel, nie die Seite.
 */
class RouteShadowingTest extends TestCase {
    use RefreshDatabase;

    public function test_static_paths_are_not_shadowed_by_parameter_routes(): void {
        $shadowed = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_contains($route->uri(), '{') || $route->getDomain() !== null) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $matched = $this->matchOrNull($route->uri(), $method);
                if ($matched !== null && $matched !== $route && $matched->uri() !== $route->uri()) {
                    $shadowed[] = $method . ' ' . $route->uri() . ' → ' . $matched->uri();
                }
            }
        }

        $this->assertSame([], $shadowed, "Feste Pfade hinter einer Parameter-Route — die feste Route zuerst registrieren:\n" . implode("\n", $shadowed));
    }

    /** Beispieladresse je Parameter-Route: sie muss ihre eigene Route treffen, nicht eine frühere. */
    public function test_parameter_routes_match_their_own_urls(): void {
        $shadowed = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! str_contains($route->uri(), '{') || $route->getDomain() !== null) {
                continue;
            }
            $url = $this->sampleUrl($route);
            if ($url === null) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $matched = $this->matchOrNull($url, $method);
                if ($matched !== null && $matched !== $route && $matched->uri() !== $route->uri()) {
                    $shadowed[] = $method . ' ' . $route->uri() . ' → ' . $matched->uri();
                }
            }
        }

        $this->assertSame([], $shadowed, "Parameter-Routen, deren Adresse eine frühere Route trifft:\n" . implode("\n", $shadowed));
    }

    public function test_thanks_page_after_public_timesheet_signature_is_reachable(): void {
        $this->get(route('timesheets.public-thanks'))->assertOk();
    }

    public function test_inbox_group_dismiss_reaches_its_own_action(): void {
        $admin = User::factory()->admin()->create();

        // Ohne Angaben antwortet die Gruppen-Aktion mit Feldfehlern — vorher 404 aus der {item}-Route.
        $this->actingAs($admin)
            ->post(route('admin.integration.inbox.group.dismiss'))
            ->assertSessionHasErrors(['plugin', 'group_key']);
    }

    private function matchOrNull(string $uri, string $method): ?RoutingRoute {
        try {
            return Route::getRoutes()->match(Request::create('/' . ltrim($uri, '/'), $method));
        } catch (\Throwable) {
            return null;
        }
    }

    /** Setzt je Parameter einen Wert ein, der dessen Muster erfüllt; null, wenn keiner passt. */
    private function sampleUrl(RoutingRoute $route): ?string {
        $url = $route->uri();
        foreach ($route->parameterNames() as $name) {
            $pattern = $route->wheres[$name] ?? (Route::getPatterns()[$name] ?? null);
            $candidates = $name === 'project' ? ['kunde-a/projekt-b'] : ['Zq9Xw8Vt7R', '123', 'abc', 'de'];
            if ($pattern !== null) {
                foreach (explode('|', trim($pattern, '()')) as $literal) {
                    if (preg_match('/^[A-Za-z0-9_.-]+$/', $literal) === 1) {
                        $candidates[] = $literal;
                    }
                }
            }
            $value = null;
            foreach ($candidates as $candidate) {
                if ($pattern === null || preg_match('#^(?:' . $pattern . ')$#', $candidate) === 1) {
                    $value = $candidate;
                    break;
                }
            }
            if ($value === null) {
                return null;
            }
            $url = (string) preg_replace('/\{' . preg_quote($name, '/') . '\??\}/', $value, $url);
        }

        return $url;
    }
}
