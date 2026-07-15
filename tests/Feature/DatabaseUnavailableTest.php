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
