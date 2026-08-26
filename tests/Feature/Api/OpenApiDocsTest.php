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

use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Support\Facades\{Artisan, Route};
use Tests\TestCase;

/**
 * OpenAPI-Dokument (Feature 008; MVP-717 Vollscan J10): Generierung,
 * Swagger-UI, Version aus APP_VERSION, beide Server (v1 + Alias) und das
 * Vollständigkeits-Gate: jede benannte Sanctum-Route `api.*` unter `api/v1/`
 * (ohne die `api.legacy.*`-Aliasse; Ingest-/Plugin-Webhook-/interne Routen
 * liegen außerhalb der Versionsgruppe) muss als Pfad+Methode im Dokument
 * vorkommen — sonst fehlt einem Controller das #[OA\…]-Attribut. PATCH-
 * Duplikate gelten über die dokumentierte PUT-Operation als abgedeckt.
 */
class OpenApiDocsTest extends TestCase {
    /** Öffentliche Geräte-Ingest-Routen (Token im Pfad) — bewusst ohne Sanctum, im Dokument optional. */
    private const INGEST_ROUTES = ['api.patrol.scan', 'api.location.ingest', 'api.cti.webhook', 'api.terminal.ingest'];

    public function test_swagger_docs_route_serves_json(): void {
        Artisan::call('l5-swagger:generate');
        $response = $this->get('/docs');
        $response->assertOk();
        $body = $response->getContent();
        $this->assertNotFalse($body);
        $data = JsonHelper::decode($body);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('openapi', $data);
        $this->assertArrayHasKey('paths', $data);
        $this->assertArrayHasKey('/customers', $data['paths']);
        $this->assertArrayHasKey('/projects', $data['paths']);
        $this->assertArrayHasKey('/tasks', $data['paths']);
        $this->assertSame('workDiary REST API', $data['info']['title']);
        $this->assertSame((string) config('app.version'), $data['info']['version'], 'info.version kommt aus APP_VERSION (config app.version).');
        $this->assertArrayHasKey('bearerAuth', $data['components']['securitySchemes']);
        $this->assertSame(['/api/v1', '/api'], array_column($data['servers'], 'url'));
    }

    public function test_every_named_sanctum_route_is_documented(): void {
        Artisan::call('l5-swagger:generate');
        $raw = file_get_contents(storage_path('api-docs/api-docs.json'));
        $this->assertNotFalse($raw);
        $data = JsonHelper::decode($raw);
        $this->assertIsArray($data);
        /** @var array<string, array<string, mixed>> $paths */
        $paths = $data['paths'];

        $documented = [];
        foreach ($paths as $path => $operations) {
            foreach (array_keys($operations) as $method) {
                $documented[strtoupper((string) $method) . ' ' . $path] = true;
            }
        }

        $missing = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();
            if (! str_starts_with($name, 'api.') || str_starts_with($name, 'api.legacy.') || in_array($name, self::INGEST_ROUTES, true)) {
                continue;
            }
            if (! str_starts_with($route->uri(), 'api/v1/')) {
                continue;
            }
            $path = '/' . preg_replace('#^api/v1/#', '', $route->uri());
            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }
                if ($method === 'PATCH' && isset($documented['PUT ' . $path])) {
                    continue;
                }
                if (! isset($documented[$method . ' ' . $path])) {
                    $missing[] = $method . ' ' . $path . ' (' . $name . ')';
                }
            }
        }

        sort($missing);
        $this->assertSame([], $missing, "Routen ohne #[OA\\…]-Attribut im generierten Dokument:\n" . implode("\n", $missing));
    }

    public function test_swagger_ui_renders(): void {
        $response = $this->get('/api/documentation');
        $response->assertOk();
        $response->assertSee('Swagger UI', false);
    }
}
