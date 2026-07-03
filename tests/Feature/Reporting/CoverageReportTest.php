<?php
/*
 * Created on   : Thu Jul 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CoverageReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

class CoverageReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_route_renders_for_admin(): void {
        $response = $this->getWithMonthRange('reports.coverage');

        $response->assertOk();
    }

    public function test_csv_export_contains_report_meta_line(): void {
        $response = $this->getWithMonthRange('reports.coverage', ['export' => 'csv']);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $body = $response->getContent() ?: '';
        $this->assertStringContainsString('#report:coverage', $body);
        $this->assertStringContainsString('Schichttyp', $body);
    }

    /**
     * @param  array<string, string>  $parameters
     * @return TestResponse<\Illuminate\Http\Response>
     */
    private function getWithMonthRange(string $routeName, array $parameters = []): TestResponse {
        return $this->actingAs($this->admin)
            ->withSession($this->dateRangeMonth(2026, 5))
            ->get(route($routeName, $parameters));
    }
}
