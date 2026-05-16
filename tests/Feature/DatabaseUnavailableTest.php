<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DatabaseUnavailableTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdo_exception_renders_friendly_503_page(): void
    {
        Route::get('/__test_db_down', function (): void {
            throw new \PDOException('SQLSTATE[HY000] [2002] Connection timed out');
        })->withoutMiddleware([]);

        $response = $this->get('/__test_db_down');

        $response->assertStatus(503);
        $response->assertSee('Datenbank vorübergehend nicht erreichbar', false);
    }

    public function test_pdo_exception_returns_json_for_api_clients(): void
    {
        Route::get('/__test_db_down_json', function (): void {
            throw new \PDOException('connection refused');
        })->withoutMiddleware([]);

        $response = $this->getJson('/__test_db_down_json');

        $response->assertStatus(503);
        $response->assertExactJson(['message' => 'Database temporarily unavailable.']);
    }
}
