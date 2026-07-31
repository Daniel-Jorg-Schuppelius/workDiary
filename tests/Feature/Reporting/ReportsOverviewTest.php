<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReportsOverviewTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Project, TimeEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Auswertungs-Landing (reports.index, Feature 002): persönliche KPIs +
 * Übersichts-Charts im globalen Zeitraum, Linkliste aus der gefilterten
 * NavigationRegistry (inkl. neuem Plan/Ist-Einstieg).
 */
class ReportsOverviewTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $user;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = $this->orgUser();
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Overview-Projekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_renders_kpis_and_overview_charts(): void {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-03-10',
            'started_at' => '2030-03-10 09:00:00',
            'ended_at' => '2030-03-10 12:30:00',
            'kind' => TimeEntryKind::Work->value,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession($this->dateRangeMonth(2030, 3))
            ->get(route('reports.index'));

        $response->assertOk();
        $response->assertSee('Meine Stunden', false);
        $response->assertSee('3:30 h', false);
        $response->assertSee('Stunden pro Tag', false);
        $response->assertSee('Top-Projekte nach Stunden', false);
        $response->assertSee('Overview-Projekt', false);
        $response->assertSee('<figure', false);
    }

    public function test_link_list_mirrors_navigation_registry_groups(): void {
        $response = $this->actingAs($this->user)
            ->withSession($this->dateRangeMonth(2030, 3))
            ->get(route('reports.index'));

        $response->assertOk();
        // Gruppen aus der Registry (ohne die Übersichtsgruppe selbst).
        $response->assertSee('Persönlich', false);
        $response->assertSee(route('reports.my-month'), false);
        // Plan/Ist ist jetzt verlinkt (war nur per URL erreichbar).
        $response->assertSee(route('reports.plan-ist.presence'), false);
    }

    public function test_admin_sees_permission_gated_reports_in_link_list(): void {
        $admin = $this->orgAdmin();

        $response = $this->actingAs($admin)
            ->withSession($this->dateRangeMonth(2030, 3))
            ->get(route('reports.index'));

        $response->assertOk();
        $response->assertSee(route('reports.economics'), false);
    }

    public function test_empty_period_renders_empty_states_without_crash(): void {
        $response = $this->actingAs($this->user)
            ->withSession($this->dateRangeMonth(2031, 1))
            ->get(route('reports.index'));

        $response->assertOk();
        $response->assertSee('Noch keine Daten', false);
    }

    public function test_requires_authentication(): void {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }
}
