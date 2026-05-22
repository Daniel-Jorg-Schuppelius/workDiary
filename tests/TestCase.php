<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TestCase.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests;

use App\Support\DatabaseHealth;
use Database\Seeders\TestingSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase {
    /**
     * Wird von Laravels RefreshDatabase ausgewertet: legt einmalig pro
     * Test-Prozess Permissions und globale Rollen an. Spart so die
     * sonst per setUp() pro Testmethode ausgeführten RolesSeeder- und
     * PermissionsSeeder-Calls.
     */
    protected bool $seed = true;

    protected string $seeder = TestingSeeder::class;

    protected function setUp(): void {
        parent::setUp();

        // Verhindert, dass DatabaseHealth-Marker zwischen Tests (oder zwischen
        // Tests und der Dev-Umgebung) durchsickern. Tests, die absichtlich
        // PDOExceptions ausl\u00f6sen (z. B. DatabaseUnavailableTest), w\u00fcrden
        // sonst nachfolgende Tests pauschal in 503 laufen lassen.
        DatabaseHealth::reset();
    }

    protected function tearDown(): void {
        DatabaseHealth::reset();

        parent::tearDown();
    }
}
