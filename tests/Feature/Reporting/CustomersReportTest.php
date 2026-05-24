<?php

/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomersReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\OpenIssue\{OpenIssueSeverity, OpenIssueSource, OpenIssueStatus, OpenIssueVisibility};
use App\Enums\Project\ProjectStatus;
use App\Enums\Protocol\ProtocolType;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, DiaryEntry, OpenIssue, Project, Protocol, TimeEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

class CustomersReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->user = User::factory()->admin()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Musterkunde GmbH',
        ]);

        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Wartungsvertrag',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_route_renders_for_authenticated_user(): void {
        $response = $this->actingAs($this->user)->get(route('reports.customers'));
        $response->assertOk();
        $response->assertSee('Kundenanalyse');
    }

    public function test_report_shows_core_metrics_for_customer(): void {
        $entry = DiaryEntry::factory()->for($this->user)->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'project_id' => $this->project->id,
            'title' => 'Serviceeinsatz A',
            'created_at' => now()->subDays(3),
        ]);

        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'diary_entry_id' => $entry->id,
            'user_id' => $this->user->id,
            'date' => now()->subDays(3)->toDateString(),
            'started_at' => now()->subDays(3)->setTime(9, 0)->toDateTimeString(),
            'ended_at' => now()->subDays(3)->setTime(11, 0)->toDateTimeString(),
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
        ]);

        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'diary_entry_id' => $entry->id,
            'user_id' => $this->user->id,
            'date' => now()->subDays(2)->toDateString(),
            'started_at' => now()->subDays(2)->setTime(12, 0)->toDateTimeString(),
            'ended_at' => now()->subDays(2)->setTime(13, 0)->toDateTimeString(),
            'kind' => TimeEntryKind::Work->value,
            'billable' => false,
        ]);

        OpenIssue::create([
            'organization_id' => $this->organization->id,
            'subject_type' => Customer::class,
            'subject_id' => $this->customer->id,
            'source_type' => OpenIssueSource::Manual->value,
            'source_ref_id' => null,
            'title' => 'Escalation',
            'description' => null,
            'category' => 'customer',
            'severity' => OpenIssueSeverity::High->value,
            'status' => OpenIssueStatus::Blocked->value,
            'assignee_user_id' => $this->user->id,
            'due_at' => now()->addDays(2),
            'visibility' => OpenIssueVisibility::Internal->value,
            'closed_at' => null,
            'closed_by_user_id' => null,
            'closed_reason' => null,
            'created_by_user_id' => $this->user->id,
        ]);

        Protocol::factory()->for($entry, 'subject')->state([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::Defect->value,
            'title' => 'Nacharbeit Protokoll',
        ])->create();

        $response = $this->actingAs($this->user)
            ->withSession($this->dateRangeSession(now()->subDays(30)->toDateString(), now()->toDateString()))
            ->get(route('reports.customers'));

        $response->assertOk();
        $response->assertSee('Musterkunde GmbH');
        $response->assertSee('180', false);
        $response->assertSee('120', false);
        $response->assertSee('60', false);
        $response->assertSee('1', false);
        $response->assertSee(route('diary.index', [
            'customer' => $this->customer->id,
            'from' => now()->subDays(30)->toDateString(),
            'to' => now()->toDateString(),
            'project' => null,
            'user' => null,
        ]), false);
        $response->assertSee(route('diary.index', [
            'customer' => $this->customer->id,
            'from' => now()->subDays(30)->toDateString(),
            'to' => now()->toDateString(),
            'status' => 3,
        ]), false);
    }

    public function test_diary_index_filters_by_customer_from_report_drilldown(): void {
        $otherCustomer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Zweitkunde AG',
        ]);

        DiaryEntry::factory()->for($this->user)->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'project_id' => $this->project->id,
            'title' => 'Passender Auftrag',
            'content' => 'Passender Auftrag mit relevanten Details',
            'created_at' => now()->subDays(2),
        ]);

        DiaryEntry::factory()->for($this->user)->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $otherCustomer->id,
            'project_id' => Project::create([
                'organization_id' => $this->organization->id,
                'customer_id' => $otherCustomer->id,
                'name' => 'Fremdprojekt',
                'status' => ProjectStatus::Active->value,
                'created_by' => $this->user->id,
            ])->id,
            'title' => 'Fremder Auftrag',
            'content' => 'Fremder Auftrag mit fremden Details',
            'created_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($this->user)
            ->withSession($this->dateRangeSession(now()->subDays(30)->toDateString(), now()->toDateString()))
            ->get(route('diary.index', ['customer' => $this->customer->id]));

        $response->assertOk();
        $response->assertSeeText('Passender Auftrag mit relevanten Details');
        $response->assertDontSeeText('Fremder Auftrag mit fremden Details');
    }

    public function test_requires_authentication(): void {
        $this->get(route('reports.customers'))->assertRedirect(route('login'));
    }

    public function test_report_can_be_exported_as_csv(): void {
        $entry = DiaryEntry::factory()->for($this->user)->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'project_id' => $this->project->id,
            'created_at' => now()->subDays(2),
        ]);

        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'diary_entry_id' => $entry->id,
            'user_id' => $this->user->id,
            'date' => now()->subDays(2)->toDateString(),
            'started_at' => now()->subDays(2)->setTime(10, 0)->toDateTimeString(),
            'ended_at' => now()->subDays(2)->setTime(11, 30)->toDateTimeString(),
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession($this->dateRangeSession(now()->subDays(30)->toDateString(), now()->toDateString()))
            ->get(route('reports.customers', ['export' => 'csv']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('content-disposition');

        $content = (string) $response->getContent();
        $this->assertStringContainsString('Kunde;Auftraege;GesamtMinuten', $content);
        $this->assertStringContainsString('Musterkunde GmbH', $content);
    }
}
