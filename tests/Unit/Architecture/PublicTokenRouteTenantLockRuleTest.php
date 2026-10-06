<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicTokenRouteTenantLockRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Routing\Route;
use ReflectionClass;
use Tests\TestCase;

/**
 * Gate zum Sicherheitsaudit 2026-10-04 (pub-3): eine Route ohne Anmeldung, die
 * über `{token}` oder `{code}` einen Datensatz erreicht, prüft die
 * Mandantensperre (`ChecksTenantPublicSurfaces` bzw.
 * `Organization::publicSurfacesAvailable()`). `EnforceTenantStatus` wirkt nur
 * mit angemeldeter Person; die Sperre fehlte deshalb an jedem Weg, der nach
 * ihrer Einführung dazukam.
 */
class PublicTokenRouteTenantLockRuleTest extends TestCase {
    /** Middleware, die die Sperre selbst prüft. */
    private const LOCKING_MIDDLEWARE = [
        \App\Http\Middleware\AuthenticateScim::class,
        \App\Http\Middleware\B2bCatalog\ResolveB2bCatalogOrganization::class,
        \App\Http\Middleware\Careers\ResolveCareerPortal::class,
    ];

    /** @var array<string, string> URI => Grund */
    private const ALLOWED = [
        'password/reset/{token}' => 'Kontofunktion vor der Anmeldung; die angemeldete Oberfläche sperrt EnforceTenantStatus.',
        'zahlen/{token}' => 'Entscheidung des Inhabers vom 2026-10-04: der Zahlungslink bleibt bei gesperrtem Mandanten offen.',
        'zahlen/{token}/fertig' => 'Rücksprung des Zahlungslinks, siehe `zahlen/{token}`.',
        'calendar/feed/{token}.ics' => 'Nur lesend: Kalender-Abo.',
        'calendar/org/{token}.ics' => 'Nur lesend: Kalender-Abo der Organisation.',
        'status/{token}' => 'Nur lesend: veröffentlichte Statusseite.',
        'serial-passport/{token}' => 'Nur lesend: Gerätepass.',
        'nachhaltigkeitsbericht/{token}' => 'Nur lesend: veröffentlichter Auszug.',
        'zertifikat/{code}' => 'Nur lesend: Dritte prüfen ein ausgestelltes Zertifikat; dessen Gültigkeit hängt nicht am Vertrag des Mandanten.',
        'zertifikat/{code}/credential.json' => 'Siehe `zertifikat/{code}`.',
        'zertifikat/{code}/jwt' => 'Siehe `zertifikat/{code}`.',
        'lrs/cmi5/fetch/{token}' => 'Einmal-Token eines laufenden Kursstarts; den Start sperren Anmeldung bzw. externer Lernzugang.',
        'api/webhooks/calendly/{token}' => 'Maschinen-Webhook: stößt denselben Abgleich an wie der geplante Lauf.',
        'api/webhooks/etsy/{token}' => 'Maschinen-Webhook: stößt denselben Abgleich an wie der geplante Lauf.',
        'api/webhooks/lexoffice/{organization}/{token}' => 'Maschinen-Webhook: stößt denselben Abgleich an wie der geplante Lauf.',
    ];

    public function test_public_token_routes_check_the_tenant_lock(): void {
        $router = app('router');
        $violations = [];
        $seen = [];
        $checked = 0;

        /** @var Route $route */
        foreach ($router->getRoutes() as $route) {
            $uri = $route->uri();
            if (preg_match('/\{(token|code)\??\}/', $uri) !== 1) {
                continue;
            }
            $middleware = $router->gatherRouteMiddleware($route);
            if ($this->containsAny($middleware, [Authenticate::class])) {
                continue;
            }
            if (isset(self::ALLOWED[$uri])) {
                $seen[$uri] = true;

                continue;
            }
            $checked++;
            if ($this->containsAny($middleware, self::LOCKING_MIDDLEWARE) || $this->controllerChecksLock($route)) {
                continue;
            }
            $violations[] = implode('|', $route->methods()) . ' ' . $uri . ' → ' . $route->getActionName();
        }
        sort($violations);

        $this->assertGreaterThan(20, $checked, 'Die Suche findet die öffentlichen Token-Routen nicht mehr.');
        $this->assertSame([], $violations, "Öffentliche Token-Route ohne Mandantensperre — `ChecksTenantPublicSurfaces` nutzen oder mit Grund in ALLOWED aufnehmen:\n" . implode("\n", $violations));
        $this->assertSame([], array_values(array_diff(array_keys(self::ALLOWED), array_keys($seen))), 'Veraltete Ausnahmen in ALLOWED.');
    }

    /**
     * @param  list<mixed>  $middleware
     * @param  list<class-string>  $classes
     */
    private function containsAny(array $middleware, array $classes): bool {
        foreach ($middleware as $entry) {
            if (! is_string($entry)) {
                continue;
            }
            foreach ($classes as $class) {
                if ($entry === $class || str_starts_with($entry, $class . ':')) {
                    return true;
                }
            }
        }

        return false;
    }

    private function controllerChecksLock(Route $route): bool {
        $class = $route->getControllerClass();
        if ($class === null || ! class_exists($class)) {
            return false;
        }
        $source = (string) file_get_contents((string) (new ReflectionClass($class))->getFileName());

        return str_contains($source, 'assertTenantPublicSurfacesAvailable(') || str_contains($source, 'publicSurfacesAvailable()');
    }
}
