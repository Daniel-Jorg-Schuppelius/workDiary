<?php
/*
 * Created on   : Thu Jul 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnCallReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

class OnCallReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_route_renders(): void {
        $this->actingAs($this->admin)
            ->withSession($this->dateRangeYear(2030))
            ->get(route('reports.on-call'))
            ->assertOk();
    }

    public function test_csv_export(): void {
        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeYear(2030))
            ->get(route('reports.on-call', ['export' => 'csv']));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('#report:on-call', (string) $response->getContent());
        $this->assertStringContainsString('Mitarbeiter', (string) $response->getContent());
    }

    public function test_requires_authentication(): void {
        $this->get(route('reports.on-call'))->assertRedirect(route('login'));
    }
}
