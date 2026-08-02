<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerRetentionReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, Project, TimeEntry, User};
use App\Services\Reporting\CustomerRetentionReportBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Kundenbindung & Kohorten (MVP-466): Kohorten-Matrix nach Erstleistungs-
 * jahr und die exakt aufgehende Bestandsbrücke (Start + Neu + Zurück-
 * gewonnen − Neu-wieder-inaktiv − Verloren = Ende).
 *
 * Fixture (Zeitraum 2030, „verloren nach" 180 Tagen):
 *  - Konstant:     2028, 2029-09, 2030-08  → Start & Ende (Kohorte 2028)
 *  - Verloren:     nur 2029-08             → Start, dann verloren
 *  - NeuBleibt:    Erstleistung 2030-09    → Neukunde, am Ende aktiv
 *  - Rückkehrer:   2027-08 + 2030-10       → zurückgewonnen (Kohorte 2027)
 *  - NeuWiederWeg: nur 2030-02             → Neukunde, wieder inaktiv
 */
class CustomerRetentionReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $spec = [
            'Konstant' => ['2028-05-05', '2029-09-10', '2030-08-15'],
            'Verloren' => ['2029-08-01'],
            'NeuBleibt' => ['2030-09-01'],
            'Rückkehrer' => ['2027-08-01', '2030-10-01'],
            'NeuWiederWeg' => ['2030-02-01'],
        ];

        foreach ($spec as $name => $days) {
            $customer = Customer::create([
                'organization_id' => $this->organization->id,
                'name' => $name . ' GmbH',
            ]);
            $project = Project::create([
                'organization_id' => $this->organization->id,
                'customer_id' => $customer->id,
                'name' => 'Projekt ' . $name,
                'status' => ProjectStatus::Active->value,
                'created_by' => $this->admin->id,
            ]);
            foreach ($days as $day) {
                TimeEntry::create([
                    'organization_id' => $this->organization->id,
                    'project_id' => $project->id,
                    'user_id' => $this->admin->id,
                    'date' => $day,
                    'started_at' => $day . ' 09:00:00',
                    'ended_at' => $day . ' 10:00:00',
                    'kind' => TimeEntryKind::Work->value,
                    'billable' => true,
                ]);
            }
        }
    }

    private function build(): array {
        return app(CustomerRetentionReportBuilder::class)->build(
            CarbonImmutable::parse('2030-01-01'),
            CarbonImmutable::parse('2030-12-31'),
            6,
            180,
        );
    }

    public function test_bridge_adds_up_exactly(): void {
        $bridge = $this->build()['bridge'];

        $this->assertSame(2, $bridge['start']);
        $this->assertSame(3, $bridge['end']);
        $this->assertSame(['NeuBleibt GmbH'], array_column($bridge['new'], 'customerName'));
        $this->assertSame(['Rückkehrer GmbH'], array_column($bridge['reactivated'], 'customerName'));
        $this->assertSame(['NeuWiederWeg GmbH'], array_column($bridge['newChurned'], 'customerName'));
        $this->assertSame(['Verloren GmbH'], array_column($bridge['lost'], 'customerName'));

        // Start + Neu (alle) + Zurückgewonnen − Neu-wieder-inaktiv − Verloren = Ende.
        $this->assertSame(
            $bridge['end'],
            $bridge['start']
                + count($bridge['new']) + count($bridge['newChurned'])
                + count($bridge['reactivated'])
                - count($bridge['newChurned'])
                - count($bridge['lost']),
        );
    }

    public function test_cohorts_group_by_first_service_year(): void {
        $cohorts = $this->build()['cohorts'];

        $this->assertSame(range(2025, 2030), $cohorts['years']);
        $byYear = collect($cohorts['rows'])->keyBy('year');

        $this->assertSame(1, $byYear[2028]['size']);
        $this->assertSame([100.0, 100.0, 100.0, null, null, null], $byYear[2028]['cells']);

        $this->assertSame(1, $byYear[2029]['size']);
        $this->assertSame([100.0, 0.0, null, null, null, null], $byYear[2029]['cells']);

        // 2030: NeuBleibt + NeuWiederWeg.
        $this->assertSame(2, $byYear[2030]['size']);
        $this->assertSame([100.0, null, null, null, null, null], $byYear[2030]['cells']);
    }

    public function test_kpis_reflect_returning_and_lost(): void {
        $kpis = $this->build()['kpis'];

        // 2029 aktiv: Konstant + Verloren; davon 2030 wieder aktiv: nur Konstant.
        $this->assertSame(50.0, $kpis['returningRate']);
        $this->assertSame(2, $kpis['newCount']);
        $this->assertSame(1, $kpis['lostCount']);
        $this->assertSame(3, $kpis['endActive']);
        $this->assertNotNull($kpis['avgCustomerAgeYears']);
    }

    public function test_page_renders_bridge_series_and_requires_permission(): void {
        $plain = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($plain)
            ->withSession($this->dateRangeSession('2030-01-01', '2030-12-31'))
            ->get(route('reports.customer-retention'))
            ->assertForbidden();

        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeSession('2030-01-01', '2030-12-31'))
            ->get(route('reports.customer-retention', ['lost_days' => 180]));

        $response->assertOk();
        $series = $response->viewData('bridgeSeries');
        $this->assertSame([2, 1, -1, -1], array_column($series, 'y'));
    }
}
