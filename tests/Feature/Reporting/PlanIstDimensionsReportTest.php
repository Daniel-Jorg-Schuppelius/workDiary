<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanIstDimensionsReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\User\Permission as P;
use App\Models\{Attendance, Organization, Project, ScheduledShift, ShiftType, TimeEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * A14 · MVP-333 (Feature 007): erweiterte Plan/Ist-Dimensionen Schicht/
 * Projekt/Standort — Rechte-Matrix (Bestandsrecht `report.presence.
 * organization`), Org-Isolation über HTTP und leere Zustände.
 */
class PlanIstDimensionsReportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** @return list<string> */
    private static function dimensionRoutes(): array {
        return ['reports.plan-ist.shifts', 'reports.plan-ist.projects', 'reports.plan-ist.sites'];
    }

    public function test_dimensions_require_org_report_permission(): void {
        $plain = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $teamOnly = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $teamOnly->givePermissionTo(P::ReportPresenceTeam->value);

        $org = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $org->givePermissionTo(P::ReportPresenceOrganization->value);

        foreach (self::dimensionRoutes() as $route) {
            $this->actingAs($plain)->get(route($route))->assertForbidden();
            // Team-Recht deckt die org-weiten Dimensionen bewusst NICHT.
            $this->actingAs($teamOnly)->get(route($route))->assertForbidden();
            $this->actingAs($org)->get(route($route))->assertOk();
        }
    }

    public function test_dimensions_render_empty_states(): void {
        $viewer = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $viewer->givePermissionTo(P::ReportPresenceOrganization->value);

        $this->actingAs($viewer)->get(route('reports.plan-ist.shifts'))
            ->assertOk()
            ->assertSee(__('Keine geplanten Schichten im Zeitraum.'));

        $this->actingAs($viewer)->get(route('reports.plan-ist.projects'))
            ->assertOk()
            ->assertSee(__('Keine Projektzeiten oder geplanten Aufträge im Zeitraum.'));

        $this->actingAs($viewer)->get(route('reports.plan-ist.sites'))
            ->assertOk()
            // Solldaten-Lücke wird immer ausgewiesen, dazu der leere Zustand.
            ->assertSee(__('Keine ortsbasiert erfassten Zeiten im Zeitraum.'));
    }

    public function test_shift_dimension_renders_data_and_week_grouping(): void {
        $viewer = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $viewer->givePermissionTo(P::ReportPresenceOrganization->value);

        $type = ShiftType::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Frühdienst',
            'default_start_time' => '08:00',
            'default_end_time' => '16:00',
        ]);
        $worker = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        ScheduledShift::factory()->published()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $worker->id,
            'shift_type_id' => $type->id,
            'date' => '2024-01-15',
        ]);
        Attendance::withoutEvents(function () use ($worker): void {
            Attendance::query()->create([
                'organization_id' => $this->organization->id,
                'user_id' => $worker->id,
                'date' => '2024-01-15',
                'started_at' => '2024-01-15 08:00:00',
                'ended_at' => '2024-01-15 16:00:00',
                'duration_minutes' => 480,
            ]);
        });

        $this->actingAs($viewer)
            ->get(route('reports.plan-ist.shifts', ['from' => '2024-01-01', 'to' => '2024-01-31']))
            ->assertOk()
            ->assertSee('Frühdienst');

        $this->actingAs($viewer)
            ->get(route('reports.plan-ist.shifts', ['from' => '2024-01-01', 'to' => '2024-01-31', 'group' => 'week']))
            ->assertOk()
            ->assertSee('2024-W03');
    }

    public function test_project_dimension_is_org_isolated_over_http(): void {
        $viewer = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $viewer->givePermissionTo(P::ReportPresenceOrganization->value);

        $own = Project::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Eigenes Projekt']);
        TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $viewer->id,
            'project_id' => $own->id,
            'date' => '2024-01-15',
            'minutes' => 60,
            'billable' => false,
        ]);

        $otherOrg = Organization::factory()->create();
        $foreignUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $foreign = Project::factory()->create(['organization_id' => $otherOrg->id, 'name' => 'Fremdorg-Projekt']);
        TimeEntry::factory()->create([
            'organization_id' => $otherOrg->id,
            'user_id' => $foreignUser->id,
            'project_id' => $foreign->id,
            'date' => '2024-01-15',
            'minutes' => 60,
            'billable' => false,
        ]);

        $this->actingAs($viewer)
            ->get(route('reports.plan-ist.projects', ['from' => '2024-01-01', 'to' => '2024-01-31']))
            ->assertOk()
            ->assertSee('Eigenes Projekt')
            ->assertDontSee('Fremdorg-Projekt');
    }
}
