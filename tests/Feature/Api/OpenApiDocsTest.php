<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenApiDocsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OpenApiDocsTest extends TestCase {
    public function test_swagger_docs_route_serves_json(): void {
        Artisan::call('l5-swagger:generate');
        $response = $this->get('/docs');
        $response->assertOk();
        $body = $response->getContent();
        $this->assertNotFalse($body);
        $data = json_decode($body, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('openapi', $data);
        $this->assertArrayHasKey('paths', $data);
        $this->assertArrayHasKey('/customers', $data['paths']);
        $this->assertArrayHasKey('/projects', $data['paths']);
        $this->assertArrayHasKey('/tasks', $data['paths']);
        $this->assertSame('workDiary REST API', $data['info']['title']);
        $this->assertArrayHasKey('bearerAuth', $data['components']['securitySchemes']);
    }

    public function test_swagger_ui_renders(): void {
        $response = $this->get('/api/documentation');
        $response->assertOk();
        $response->assertSee('Swagger UI', false);
    }
}
