<?php
/*
 * Created on   : Wed Jul 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FleetReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

class FleetReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->user = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    /**
     * @param  array<string, string>  $params
     * @return TestResponse<\Illuminate\Http\Response>
     */
    private function getWithRange(array $params = []): TestResponse {
        return $this->actingAs($this->user)
            ->withSession($this->dateRangeSession(now()->subDays(30)->toDateString(), now()->toDateString()))
            ->get(route('reports.fleet', $params));
    }

    public function test_route_renders(): void {
        $this->getWithRange()->assertOk();
    }

    public function test_requires_authentication(): void {
        $this->get(route('reports.fleet'))->assertRedirect(route('login'));
    }

    public function test_csv_export_returns_csv_with_metadata(): void {
        $response = $this->getWithRange(['export' => 'csv']);
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = (string) $response->getContent();
        $this->assertStringContainsString('#report:fleet', $content);
        $this->assertStringContainsString('Kennzeichen;Bezeichnung;Antrieb', $content);
    }
}
