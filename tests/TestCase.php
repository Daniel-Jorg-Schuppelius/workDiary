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
use Illuminate\Foundation\Testing\{RefreshDatabaseState, TestCase as BaseTestCase};
use Tests\Support\PersistedTestDatabase;

abstract class TestCase extends BaseTestCase {
    /**
     * Wird von Laravels RefreshDatabase ausgewertet: legt einmalig pro
     * Test-Prozess Permissions und globale Rollen an. Spart so die
     * sonst per setUp() pro Testmethode ausgeführten RolesSeeder- und
     * PermissionsSeeder-Calls.
     */
    protected bool $seed = true;

    protected string $seeder = TestingSeeder::class;

    /** Prozessweiter Zustand des Fingerprint-Skips (siehe PersistedTestDatabase). */
    private static bool $markerHandled = false;

    protected function setUp(): void {
        // Vor dem App-Boot: ist die MariaDB-Test-DB aus einem früheren Lauf
        // noch pristine, RefreshDatabase das migrate:fresh ersparen.
        if (!RefreshDatabaseState::$migrated && !self::$markerHandled && PersistedTestDatabase::enabled() && PersistedTestDatabase::isPristine()) {
            RefreshDatabaseState::$migrated = true;
            self::$markerHandled = true;
        }

        parent::setUp();

        // Lief eben ein frisches migrate:fresh+seed (erster RefreshDatabase-Test
        // des Prozesses), den neuen Zustand als wiederverwendbar markieren.
        if (!self::$markerHandled && RefreshDatabaseState::$migrated && PersistedTestDatabase::enabled()) {
            PersistedTestDatabase::storeMarker();
            self::$markerHandled = true;
        }

        // Tests sollen nicht vom gebauten Vite-Manifest abhaengen.
        $this->withoutVite();

        // withoutVite() tauscht die Vite-Instanz gegen eine Fake-Instanz aus,
        // wodurch der in AppServiceProvider beim Boot gesetzte CSP-Nonce
        // verloren geht. Erneut setzen, damit @cspNonce / der CSP-Header
        // (siehe SecurityHeaders, CspNonceTest) weiterhin ein Nonce tragen.
        \Illuminate\Support\Facades\Vite::useCspNonce();

        // Verhindert, dass DatabaseHealth-Marker zwischen Tests (oder zwischen
        // Tests und der Dev-Umgebung) durchsickern. Tests, die absichtlich
        // PDOExceptions ausl\u00f6sen (z. B. DatabaseUnavailableTest), w\u00fcrden
        // sonst nachfolgende Tests pauschal in 503 laufen lassen.
        DatabaseHealth::reset();

        // Statischer Per-Request-Cache des LegacyRoleResolver (user-id →
        // legacy-id). In Produktion prozess-/request-stabil, aber in Tests
        // resetten die IDs pro Methode — ohne Reset läuft ein späterer Test im
        // selben Worker auf eine stale legacy-id (falsche Admin-Auflösung).
        \App\Legacy\Support\LegacyRoleResolver::flush();
    }

    protected function tearDown(): void {
        DatabaseHealth::reset();

        parent::tearDown();

        // RefreshDatabase setzt $migrated zurück, wenn die Verbindung beim
        // Abbau nicht mehr in der Transaktion steckt (z. B. implizites Commit
        // durch DDL im Test). Dann ist die DB verschmutzt → Marker weg, damit
        // der nächste Lauf frisch migriert.
        if (self::$markerHandled && !RefreshDatabaseState::$migrated && PersistedTestDatabase::enabled()) {
            PersistedTestDatabase::invalidate();
            self::$markerHandled = false;
        }
    }
}
