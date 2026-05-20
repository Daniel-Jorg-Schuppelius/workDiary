<?php

/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerProjectReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Models\Customer;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithGlobalDateRange;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Enums\Project\ProjectStatus;

class CustomerProjectReportTest extends TestCase
{
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Acme GmbH',
        ]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Website-Relaunch',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
            'hourly_rate' => 100.0,
            'billable' => true,
        ]);
    }

    public function test_route_renders_for_authenticated_user(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.customer-project'));
        $response->assertOk();
        $response->assertSee('Aggregierte Stunden');
    }

    public function test_aggregates_by_customer_and_project(): void
    {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => now()->startOfYear()->addMonth()->toDateString(),
            'started_at' => now()->startOfYear()->addMonth()->setTime(9, 0)->toDateTimeString(),
            'ended_at' => now()->startOfYear()->addMonth()->setTime(11, 0)->toDateTimeString(),
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession($this->dateRangeSession(now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()))
            ->get(route('reports.customer-project'));
        $response->assertOk();
        $response->assertSee('Acme GmbH');
        $response->assertSee('Website-Relaunch');
        $response->assertSee('2:00 h', false);
    }

    public function test_requires_authentication(): void
    {
        $this->get(route('reports.customer-project'))->assertRedirect(route('login'));
    }

    public function test_csv_export_returns_download(): void
    {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => now()->startOfYear()->addMonth()->toDateString(),
            'started_at' => now()->startOfYear()->addMonth()->setTime(9, 0)->toDateTimeString(),
            'ended_at' => now()->startOfYear()->addMonth()->setTime(11, 0)->toDateTimeString(),
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession($this->dateRangeSession(now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()))
            ->get(route('reports.customer-project', ['export' => 'csv']));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $body = $response->getContent() ?: '';
        $this->assertStringContainsString('Acme GmbH', $body);
        $this->assertStringContainsString('Website-Relaunch', $body);
        $this->assertStringContainsString('120', $body);
    }

    public function test_pdf_export_returns_download(): void
    {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => now()->startOfYear()->addMonth()->toDateString(),
            'started_at' => now()->startOfYear()->addMonth()->setTime(9, 0)->toDateTimeString(),
            'ended_at' => now()->startOfYear()->addMonth()->setTime(11, 0)->toDateTimeString(),
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession($this->dateRangeSession(now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()))
            ->get(route('reports.customer-project', ['export' => 'pdf']));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('kunden-projekte_', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }
}
