<?php
/*
 * Created on   : Sat May 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatabaseUnavailableTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Support\DatabaseHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DatabaseUnavailableTest extends TestCase {
    use RefreshDatabase;

    public function test_pdo_exception_renders_friendly_503_page(): void {
        Route::get('/__test_db_down', function (): void {
            throw new \PDOException('SQLSTATE[HY000] [2002] Connection timed out');
        })->withoutMiddleware([]);

        $response = $this->get('/__test_db_down');

        $response->assertStatus(503);
        $response->assertSee('Datenbank vorübergehend nicht erreichbar', false);
    }

    public function test_pdo_exception_returns_json_for_api_clients(): void {
        Route::get('/__test_db_down_json', function (): void {
            throw new \PDOException('connection refused');
        })->withoutMiddleware([]);

        $response = $this->getJson('/__test_db_down_json');

        $response->assertStatus(503);
        $response->assertExactJson(['message' => 'Database temporarily unavailable.']);
    }

    /**
     * Ein Sperrtimeout ist KEIN Verbindungsausfall — trägt unter MariaDB aber
     * denselben SQLSTATE HY000 wie einer.
     *
     * Vorher genügte HY000, um die Verbindung 60 s als ausgefallen zu
     * markieren: die nächste, völlig unbeteiligte Anfrage im selben Prozess
     * bekam 503. Genau so entstanden die wandernden 503 in der Parallel-Suite
     * (ein Test rot, isoliert grün, jedes Mal ein anderer).
     */
    public function test_general_error_does_not_take_the_database_down(): void {
        Route::get('/__test_lock_timeout', function (): void {
            throw $this->queryException('HY000', 1205, 'Lock wait timeout exceeded');
        });

        $this->get('/__test_lock_timeout')->assertStatus(500);

        $this->assertTrue(
            DatabaseHealth::isAvailable(DatabaseHealth::defaultConnection()),
            'Ein Abfragefehler darf die Verbindung nicht als ausgefallen markieren.',
        );
    }

    /** Ein echter Verbindungsabbruch dagegen schon — und er hinterlässt seinen Grund. */
    public function test_lost_connection_marks_the_database_down_with_a_reason(): void {
        Route::get('/__test_gone_away', function (): void {
            throw $this->queryException('HY000', 2006, 'MySQL server has gone away');
        });

        try {
            $this->get('/__test_gone_away')->assertStatus(503);

            $connection = DatabaseHealth::defaultConnection();
            $this->assertFalse(DatabaseHealth::isAvailable($connection));
            $this->assertStringContainsString('gone away', (string) DatabaseHealth::markerInfo($connection)['reason']);
        } finally {
            DatabaseHealth::reset(DatabaseHealth::defaultConnection());
        }
    }

    /** Integritätsverletzungen waren nie betroffen — die Regel bleibt es. */
    public function test_constraint_violation_does_not_take_the_database_down(): void {
        Route::get('/__test_constraint', function (): void {
            throw $this->queryException('23000', 1062, 'Duplicate entry');
        });

        $this->get('/__test_constraint')->assertStatus(500);
        $this->assertTrue(DatabaseHealth::isAvailable(DatabaseHealth::defaultConnection()));
    }

    private function queryException(string $sqlState, int $driverCode, string $message): \Illuminate\Database\QueryException {
        $pdo = new \PDOException(sprintf('SQLSTATE[%s] [%d] %s', $sqlState, $driverCode, $message));
        $pdo->errorInfo = [$sqlState, $driverCode, $message];

        return new \Illuminate\Database\QueryException(
            DatabaseHealth::defaultConnection(),
            'select 1',
            [],
            $pdo,
        );
    }

    public function test_marked_legacy_connection_short_circuits_legacy_area_only(): void {
        DatabaseHealth::markUnavailable('legacy');

        try {
            // Legacy-Bereich: sofort 503 aus dem Marker, ohne Connect-Versuch.
            $this->get('/legacy/diary')
                ->assertStatus(503)
                ->assertSee('Datenbank vorübergehend nicht erreichbar', false);

            // Rest der App bleibt davon unberührt.
            $this->get('/login')->assertOk();
        } finally {
            DatabaseHealth::reset('legacy');
        }
    }
}
