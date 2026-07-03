<?php
/*
 * Created on   : Wed Jul 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OperationsReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

class OperationsReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_route_renders(): void {
        $this->getWithRange('reports.operations')->assertOk();
    }

    public function test_requires_authentication(): void {
        $this->get(route('reports.operations'))->assertRedirect(route('login'));
    }

    public function test_csv_export_returns_download_with_metadata(): void {
        $response = $this->getWithRange('reports.operations', ['export' => 'csv']);
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('operations_2030-04-01_2030-04-30.csv', (string) $response->headers->get('Content-Disposition'));
        $body = $response->getContent() ?: '';
        $this->assertStringContainsString('#report:operations', $body);
        $this->assertStringContainsString('Service-Aufträge', $body);
    }

    private function getWithRange(string $routeName, array $parameters = []): TestResponse {
        return $this->actingAs($this->user)
            ->withSession($this->dateRangeMonth(2030, 4))
            ->get(route($routeName, $parameters));
    }
}
