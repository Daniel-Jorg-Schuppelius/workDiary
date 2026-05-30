<?php
/*
 * Created on   : Mon May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectInactiveReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Project, TimeEntry, User};
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

class ProjectInactiveReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    private Project $inactive;

    private Project $active;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->inactive = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Schlummert',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);

        $this->active = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Lebendig',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);

        // Aktiver Eintrag im Range für das aktive Projekt.
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->active->id,
            'user_id' => $this->admin->id,
            'date' => '2030-03-15',
            'started_at' => '2030-03-15 09:00:00',
            'ended_at' => '2030-03-15 10:00:00',
            'kind' => TimeEntryKind::Work->value,
        ]);
    }

    public function test_route_renders_only_inactive(): void {
        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeYear(2030))
            ->get(route('reports.project-inactive'));
        $response->assertOk();
        $response->assertSee('Schlummert');
        $response->assertDontSee('Lebendig');
    }

    public function test_csv_export(): void {
        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeYear(2030))
            ->get(route('reports.project-inactive', ['export' => 'csv']));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Schlummert', (string) $response->getContent());
    }

    public function test_xlsx_export(): void {
        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeYear(2030))
            ->get(route('reports.project-inactive', ['export' => 'xlsx']));
        $response->assertOk();
        $response->assertHeader('Content-Type', \App\Support\XlsxExport::MIME);
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_bulk_archive(): void {
        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeYear(2030))
            ->post(route('reports.project-inactive.archive'), [
                'project_ids' => [Sqid::encode(Project::class, $this->inactive->id)],
            ]);
        $response->assertRedirect(route('reports.project-inactive'));

        $this->inactive->refresh();
        $this->assertSame(ProjectStatus::Archived, $this->inactive->status);
        $this->assertNotNull($this->inactive->archived_at);
    }

    public function test_requires_authentication(): void {
        $this->get(route('reports.project-inactive'))->assertRedirect(route('login'));
    }
}
