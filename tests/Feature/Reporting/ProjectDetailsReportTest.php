<?php

namespace Tests\Feature\Reporting;

use App\Models\Customer;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ProjectDetailsReportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
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
            'status' => Project::STATUS_ACTIVE,
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
            'kind' => TimeEntry::KIND_WORK,
        ]);
        $response = $this->actingAs($this->user)->get(route('reports.project-details', [
            'project_id' => $this->project->id,
            'year' => 2030,
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
            'kind' => TimeEntry::KIND_WORK,
        ]);
        $response = $this->actingAs($this->user)->get(route('reports.project-details', [
            'project_id' => $this->project->id,
            'year' => 2030,
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
            'kind' => TimeEntry::KIND_WORK,
        ]);
        $response = $this->actingAs($this->user)->get(route('reports.project-details', [
            'project_id' => $this->project->id,
            'year' => 2030,
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
