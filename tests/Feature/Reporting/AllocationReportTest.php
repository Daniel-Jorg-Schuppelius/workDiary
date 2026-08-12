<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AllocationReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\User\Permission;
use App\Models\{CostCenter, Organization, Project, TimeAllocation, TimeEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-514 P3 (Feature 103): Zeitaufteilungs-Auswertung — Berechtigung,
 * Gruppierung je Dimension, Mandantentrennung und Exporte.
 */
class AllocationReportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $viewer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->viewer = $this->orgUser();
        $this->viewer->givePermissionTo(Permission::ReportView->value);
    }

    private function allocate(int $orgId, string $type, int $targetId, int $minutes, string $date): TimeAllocation {
        $user = User::factory()->user()->create(['organization_id' => $orgId]);
        $entry = TimeEntry::factory()->create([
            'organization_id' => $orgId,
            'user_id' => $user->id,
            'project_id' => Project::factory()->create(['organization_id' => $orgId])->id,
            'minutes' => max(480, $minutes),
            'date' => $date,
        ]);

        return TimeAllocation::query()->create([
            'organization_id' => $orgId,
            'time_entry_id' => $entry->id,
            'allocatable_type' => TimeAllocation::TYPES[$type],
            'allocatable_id' => $targetId,
            'duration_minutes' => $minutes,
        ]);
    }

    public function test_requires_report_permission(): void {
        $plain = $this->orgUser();

        $this->actingAs($plain)->get(route('reports.allocations'))->assertForbidden();
        $this->actingAs($this->viewer)->get(route('reports.allocations'))->assertOk();
    }

    public function test_groups_allocations_by_dimension_and_hides_other_tenants(): void {
        $project = Project::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Anlage Nord']);
        $costCenter = CostCenter::create([
            'organization_id' => $this->organization->id,
            'code' => 'K100',
            'label' => 'Technik',
            'active' => true,
        ]);
        $this->allocate((int) $this->organization->id, 'project', (int) $project->id, 300, '2026-03-10');
        $this->allocate((int) $this->organization->id, 'project', (int) $project->id, 60, '2026-03-11');
        $this->allocate((int) $this->organization->id, 'cost_center', (int) $costCenter->id, 120, '2026-03-10');

        $foreignOrg = Organization::factory()->create();
        $foreignProject = Project::factory()->create(['organization_id' => $foreignOrg->id, 'name' => 'Fremd']);
        $this->allocate((int) $foreignOrg->id, 'project', (int) $foreignProject->id, 90, '2026-03-10');

        $response = $this->actingAs($this->viewer)
            ->get(route('reports.allocations', ['from' => '2026-03-01', 'to' => '2026-03-31']))
            ->assertOk();

        $groups = $response->viewData('groups');
        $this->assertSame(['cost_center', 'project'], array_keys($groups));
        $this->assertSame('Anlage Nord', $groups['project']['rows'][0]['name']);
        $this->assertSame(360, $groups['project']['rows'][0]['minutes']);
        $this->assertSame(2, $groups['project']['rows'][0]['entries']);
        $this->assertSame('K100 — Technik', $groups['cost_center']['rows'][0]['name']);
        $this->assertSame(120, $groups['cost_center']['rows'][0]['minutes']);
        $this->assertNotContains('Fremd', array_column($groups['project']['rows'], 'name'));
        $this->assertSame(480, $response->viewData('totalMinutes'));
    }

    public function test_period_filter_excludes_outside_allocations(): void {
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $this->allocate((int) $this->organization->id, 'project', (int) $project->id, 100, '2026-03-10');
        $this->allocate((int) $this->organization->id, 'project', (int) $project->id, 50, '2026-04-01');

        $response = $this->actingAs($this->viewer)
            ->get(route('reports.allocations', ['from' => '2026-03-01', 'to' => '2026-03-31']))
            ->assertOk();

        $this->assertSame(100, $response->viewData('totalMinutes'));
    }

    public function test_csv_and_pdf_export(): void {
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $this->allocate((int) $this->organization->id, 'project', (int) $project->id, 100, '2026-03-10');

        $csv = $this->actingAs($this->viewer)
            ->get(route('reports.allocations', ['from' => '2026-03-01', 'to' => '2026-03-31', 'export' => 'csv']));
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('Content-Type'));

        $pdf = $this->actingAs($this->viewer)
            ->get(route('reports.allocations', ['from' => '2026-03-01', 'to' => '2026-03-31', 'export' => 'pdf']));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
    }
}
