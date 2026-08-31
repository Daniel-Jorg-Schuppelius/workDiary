<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanGateCoverageRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Http\Middleware\EnforcePlanModules;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Gate zu S-55 (Sicherheitsscan 2026-08-23).
 *
 * `config('plans.routes')` ordnet Namenspräfixe einem Modul zu, und
 * {@see EnforcePlanModules} setzt das mit 423 durch. Die Kern-Routen liegen in
 * einer Gruppe mit dieser Middleware — die Plugin-Routen mit **denselben**
 * Präfixen (`customers.lexoffice.*`, `invoices.lexoffice.*`,
 * `assets.remote-support.*`, `customers.msgraph.*`) registrierten nur
 * `['web','auth']`. Ein gesperrtes Modul blieb über sie erreichbar.
 *
 * Die Regel prüft das Verhältnis, nicht die Liste: **jede benannte Route, die
 * auf ein Modul gemappt ist, muss das Gate tragen.** Ein neues Plugin, das ein
 * bestehendes Präfix benutzt, fällt damit sofort auf.
 */
class PlanGateCoverageRuleTest extends TestCase {
    /**
     * Routen, die bewusst ohne Gate laufen — Name => Begründung.
     *
     * @var array<string, string>
     */
    /**
     * Routen, die trotz Anmeldepflicht bewusst ohne Gate laufen —
     * Name => Begründung.
     *
     * @var array<string, string>
     */
    private const EXCEPTIONS = [];

    /** Eine Ausnahme ohne Begründung ist ein Vergessen mit Alibi. */
    public function test_ausnahmen_sind_begruendet(): void {
        // Leer ist der erwünschte Zustand: eine angemeldete Route, die auf ein
        // Modul gemappt ist, gehört hinter das Gate — ohne Ausnahme.
        $this->assertLessThan(5, count(self::EXCEPTIONS), 'So viele Ausnahmen sind wieder eine Positivliste.');

        foreach (self::EXCEPTIONS as $name => $reason) {
            $this->assertGreaterThan(20, mb_strlen(trim($reason)), "Ausnahme {$name} ist nicht begründet.");
        }
    }

    public function test_gemappte_routen_tragen_das_plan_gate(): void {
        /** @var array<string, string> $map */
        $map = (array) config('plans.routes', []);
        $this->assertNotEmpty($map, 'Ohne Zuordnung prüft der Test nichts.');

        $violations = [];

        /** @var RoutingRoute $route */
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if ($name === null || isset(self::EXCEPTIONS[$name])) {
                continue;
            }

            if ($this->moduleFor($name, $map) === null) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            // Nur angemeldete Routen — Web wie API (seit S-12 erbt die REST-API
            // die Modulzuordnung der gleichnamigen Web-Route): das Gate liest
            // die Module der Organisation des ANGEMELDETEN Nutzers. Auf einer öffentlichen,
            // tokengebundenen Route hätte es nichts zu prüfen. Bewusst als
            // Bedingung statt als Namensliste — eine Liste öffentlicher Routen
            // altert, diese Regel nicht.
            //
            // Ob ein gesperrtes Modul auch bereits verschickte öffentliche
            // Links entwerten soll, ist eine Produktfrage; die Mandantensperre
            // für sessionlose Oberflächen behandelt S-42.
            if (! in_array('auth', $middleware, true)
                && ! in_array('auth:web', $middleware, true)
                && ! in_array('auth:sanctum', $middleware, true)) {
                continue;
            }
            $hasGate = in_array(EnforcePlanModules::class, $middleware, true)
                || in_array('plan.modules', $middleware, true);

            if (! $hasGate) {
                $violations[] = $name;
            }
        }

        sort($violations);

        $this->assertSame([], $violations, sprintf(
            "Diese Routen sind einem Modul zugeordnet, tragen aber kein Plan-Gate:\n  %s\n"
            . 'EnforcePlanModules in die Routen-Gruppe aufnehmen — sonst bleibt ein gesperrtes Modul über sie erreichbar.',
            implode("\n  ", $violations),
        ));
    }

    /**
     * @param  array<string, string>  $map
     */
    private function moduleFor(string $name, array $map): ?string {
        foreach ($map as $pattern => $module) {
            if (fnmatch((string) $pattern, $name)) {
                return (string) $module;
            }
        }

        return null;
    }
}
