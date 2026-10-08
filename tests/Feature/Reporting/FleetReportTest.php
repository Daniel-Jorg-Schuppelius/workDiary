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

use App\Models\Platform\User;
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

    /** Eine korrigierte Fahrt zählt nur mit ihrer Korrektur, nicht zusätzlich als Original. */
    public function test_corrected_trips_are_counted_once(): void {
        $vehicle = \App\Models\Fleet\Vehicle::factory()->create(['organization_id' => $this->organization->id]);
        $original = \App\Models\Travel\TravelLog::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'vehicle_id' => $vehicle->id,
            'date' => now()->subDays(3)->startOfDay(),
            'distance_km' => 100,
        ]);
        \App\Models\Travel\TravelLog::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'vehicle_id' => $vehicle->id,
            'date' => now()->subDays(3)->startOfDay(),
            'distance_km' => 80,
            'corrects_travel_log_id' => $original->id,
        ]);

        $this->getWithRange()
            ->assertOk()
            ->assertViewHas('totals', static fn(array $totals): bool => abs($totals['km'] - 80.0) < 0.001 && $totals['trip_count'] === 1);
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
