<?php
/*
 * Created on   : Wed Jul 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Models\{Customer, Project, TimeEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

class BillingReportTest extends TestCase {
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
            ->get(route('reports.billing', $params));
    }

    public function test_route_renders_for_admin(): void {
        $this->getWithRange()->assertOk();
    }

    public function test_forbidden_for_non_admin(): void {
        $plain = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($plain)
            ->withSession($this->dateRangeSession(now()->subDays(30)->toDateString(), now()->toDateString()))
            ->get(route('reports.billing'))
            ->assertForbidden();
    }

    public function test_accountant_can_view_report(): void {
        // MVP-460: timeEntry.viewAny öffnet den Report für die Buchhaltung.
        $accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($accountant)
            ->withSession($this->dateRangeSession(now()->subDays(30)->toDateString(), now()->toDateString()))
            ->get(route('reports.billing'))
            ->assertOk();
    }

    public function test_unbilled_kpi_ignores_exported_entries(): void {
        // Regression MVP-460: exported=true (z. B. per Pivot gebündelte
        // Nicht-Primär-Einträge einer Rechnung) zählt nicht mehr als unbilled.
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
        ]);

        TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->admin->id,
            'billable' => true,
            'exported' => false,
            'minutes' => 60,
            'date' => now()->subDays(5)->toDateString(),
        ]);
        TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->admin->id,
            'billable' => true,
            'exported' => true,
            'minutes' => 45,
            'date' => now()->subDays(5)->toDateString(),
        ]);

        $response = $this->getWithRange();
        $response->assertOk();

        /** @var array{count:int, minutes:int, projected_revenue:float} $unbilled */
        $unbilled = $response->viewData('unbilled');
        $this->assertSame(1, $unbilled['count']);
        $this->assertSame(60, $unbilled['minutes']);
    }

    public function test_csv_export_returns_csv_with_metadata(): void {
        $response = $this->getWithRange(['export' => 'csv']);
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = (string) $response->getContent();
        $this->assertStringContainsString('#report:billing', $content);
        $this->assertStringContainsString('Bereich;Schlüssel;Anzahl', $content);
    }
}
