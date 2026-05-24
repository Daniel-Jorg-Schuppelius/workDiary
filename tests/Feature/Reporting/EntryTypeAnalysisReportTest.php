<?php

/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EntryTypeAnalysisReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\OpenIssue\{OpenIssueSeverity, OpenIssueSource, OpenIssueStatus, OpenIssueVisibility};
use App\Enums\Project\ProjectStatus;
use App\Enums\Protocol\ProtocolType;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{DiaryEntry, EntryType, OpenIssue, Project, Protocol, TimeEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

class EntryTypeAnalysisReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $user;

    private Project $project;

    private EntryType $entryType;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->user = User::factory()->admin()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'MVP-040 Projekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);

        $this->entryType = EntryType::factory()->service()->create([
            'organization_id' => $this->organization->id,
            'slug' => 'service_custom',
            'label' => 'Service',
        ]);
    }

    public function test_route_renders_for_authenticated_user(): void {
        $response = $this->actingAs($this->user)->get(route('reports.entry-types'));
        $response->assertOk();
        $response->assertSee('Auftragstypanalyse');
    }

    public function test_report_calculates_plan_ist_and_rework_metrics(): void {
        $entryA = DiaryEntry::factory()->for($this->user)->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'entry_type_id' => $this->entryType->id,
            'planned_minutes' => 100,
            'created_at' => now()->subDays(5),
        ]);

        $entryB = DiaryEntry::factory()->for($this->user)->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'entry_type_id' => $this->entryType->id,
            'planned_minutes' => 50,
            'created_at' => now()->subDays(4),
        ]);

        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'diary_entry_id' => $entryA->id,
            'user_id' => $this->user->id,
            'date' => now()->subDays(5)->toDateString(),
            'started_at' => now()->subDays(5)->setTime(9, 0)->toDateTimeString(),
            'ended_at' => now()->subDays(5)->setTime(11, 30)->toDateTimeString(),
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
        ]);

        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'diary_entry_id' => $entryB->id,
            'user_id' => $this->user->id,
            'date' => now()->subDays(4)->toDateString(),
            'started_at' => now()->subDays(4)->setTime(13, 0)->toDateTimeString(),
            'ended_at' => now()->subDays(4)->setTime(14, 0)->toDateTimeString(),
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
        ]);

        Protocol::factory()->for($entryA, 'subject')->state([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::Defect->value,
            'title' => 'Nacharbeit',
        ])->create();

        OpenIssue::create([
            'organization_id' => $this->organization->id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entryB->id,
            'source_type' => OpenIssueSource::Manual->value,
            'source_ref_id' => null,
            'title' => 'Escalated',
            'description' => null,
            'category' => 'entry',
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

        $response = $this->actingAs($this->user)
            ->withSession($this->dateRangeSession(now()->subDays(30)->toDateString(), now()->toDateString()))
            ->get(route('reports.entry-types'));

        $response->assertOk();
        $response->assertSee('Service');
        $response->assertSee('2', false);
        $response->assertSee('75,00', false);
        $response->assertSee('105,00', false);
        $response->assertSee('1,400', false);
        $response->assertSee('1', false);
        $response->assertSee('50,00', false);
    }

    public function test_requires_authentication(): void {
        $this->get(route('reports.entry-types'))->assertRedirect(route('login'));
    }

    public function test_report_can_be_exported_as_csv(): void {
        $entry = DiaryEntry::factory()->for($this->user)->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'entry_type_id' => $this->entryType->id,
            'planned_minutes' => 60,
            'created_at' => now()->subDays(2),
        ]);

        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'diary_entry_id' => $entry->id,
            'user_id' => $this->user->id,
            'date' => now()->subDays(2)->toDateString(),
            'started_at' => now()->subDays(2)->setTime(10, 0)->toDateTimeString(),
            'ended_at' => now()->subDays(2)->setTime(11, 0)->toDateTimeString(),
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession($this->dateRangeSession(now()->subDays(30)->toDateString(), now()->toDateString()))
            ->get(route('reports.entry-types', ['export' => 'csv']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('content-disposition');

        $content = (string) $response->getContent();
        $this->assertStringContainsString('Auftragstyp;Auftraege;DurchschnittPlanMinuten', $content);
        $this->assertStringContainsString('Service', $content);
    }
}
