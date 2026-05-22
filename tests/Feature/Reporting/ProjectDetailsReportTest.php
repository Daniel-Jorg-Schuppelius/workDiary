<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectDetailsReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Customer;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithGlobalDateRange;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ProjectDetailsReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $user;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Acme GmbH',
        ]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Website-Relaunch',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_route_renders_with_project(): void {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-04-10',
            'started_at' => '2030-04-10 09:00:00',
            'ended_at' => '2030-04-10 11:00:00',
            'kind' => TimeEntryKind::Work->value,
        ]);
        $response = $this->actingAs($this->user)
            ->withSession($this->dateRangeYear(2030))
            ->get(route('reports.project-details', [
                'project_id' => $this->project->id,
            ]));
        $response->assertOk();
        $response->assertSee('Website-Relaunch');
    }

    public function test_csv_export(): void {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-04-10',
            'started_at' => '2030-04-10 09:00:00',
            'ended_at' => '2030-04-10 11:00:00',
            'kind' => TimeEntryKind::Work->value,
        ]);
        $response = $this->actingAs($this->user)
            ->withSession($this->dateRangeYear(2030))
            ->get(route('reports.project-details', [
                'project_id' => $this->project->id,
                'export' => 'csv',
            ]));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $body = $response->getContent() ?: '';
        $this->assertStringContainsString('120', $body);
    }

    public function test_pdf_export(): void {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-04-10',
            'started_at' => '2030-04-10 09:00:00',
            'ended_at' => '2030-04-10 11:00:00',
            'kind' => TimeEntryKind::Work->value,
        ]);
        $response = $this->actingAs($this->user)
            ->withSession($this->dateRangeYear(2030))
            ->get(route('reports.project-details', [
                'project_id' => $this->project->id,
                'export' => 'pdf',
            ]));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_requires_authentication(): void {
        $this->get(route('reports.project-details'))->assertRedirect(route('login'));
    }
}
