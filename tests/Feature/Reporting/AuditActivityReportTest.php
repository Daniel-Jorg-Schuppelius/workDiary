<?php
/*
 * Created on   : Wed Jul 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditActivityReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

class AuditActivityReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->admin = User::factory()->admin()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    /**
     * @param  array<string, string>  $params
     * @return TestResponse<\Illuminate\Http\Response>
     */
    private function getWithRange(array $params = []): TestResponse {
        return $this->actingAs($this->admin)
            ->withSession($this->dateRangeSession(now()->subDays(30)->toDateString(), now()->toDateString()))
            ->get(route('reports.audit-activity', $params));
    }

    public function test_route_renders_for_admin(): void {
        $this->getWithRange()->assertOk();
    }

    public function test_forbidden_for_non_admin(): void {
        $plain = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($plain)
            ->withSession($this->dateRangeSession(now()->subDays(30)->toDateString(), now()->toDateString()))
            ->get(route('reports.audit-activity'))
            ->assertForbidden();
    }

    public function test_csv_export_returns_csv_with_metadata(): void {
        $response = $this->getWithRange(['export' => 'csv']);
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = (string) $response->getContent();
        $this->assertStringContainsString('#report:audit-activity', $content);
        $this->assertStringContainsString('Bereich;Schlüssel;Anzahl', $content);
    }
}
