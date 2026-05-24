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

use App\Http\Controllers\Reporting\EntryTypeAnalysisReportController;
use App\Http\Controllers\Reporting\EntryTypeDrilldownReportController;
use App\Enums\OpenIssue\{OpenIssueSeverity, OpenIssueSource, OpenIssueStatus, OpenIssueVisibility};
use App\Enums\Project\ProjectStatus;
use App\Enums\Protocol\ProtocolType;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{AuditLog, DiaryEntry, EntryType, OpenIssue, Project, Protocol, TimeEntry, User};
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
        $response->assertSee(route('diary.index', [
            'from' => now()->subDays(30)->toDateString(),
            'to' => now()->toDateString(),
            'entry_type' => $this->entryType->id,
        ]));
        $response->assertSee(route('reports.entry-types.drilldown.protocols', [
            'entry_type_id' => $this->entryType->id,
        ]), false);
        $response->assertSee(route('reports.entry-types.drilldown.open-issues', [
            'entry_type_id' => $this->entryType->id,
            'escalated' => 1,
        ]));
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

    public function test_report_can_be_exported_as_pdf(): void {
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
            ->get(route('reports.entry-types', ['export' => 'pdf']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_report_export_writes_audit_log_entry(): void {
        $entry = $this->createEntryTypeDiaryEntry();
        $this->createEntryTypeWorkTimeEntry($entry);

        $this->actingAs($this->user)
            ->withSession($this->dateRangeSession(now()->subDays(30)->toDateString(), now()->toDateString()))
            ->get(route('reports.entry-types', ['entry_type_id' => $this->entryType->id, 'export' => 'pdf']))
            ->assertOk();

        $log = AuditLog::query()
            ->where('event', 'report.exported')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(EntryTypeAnalysisReportController::class, $log->auditable_type);
        $this->assertSame($this->organization->id, $log->organization_id);
        $this->assertSame($this->user->id, $log->user_id);
        $this->assertSame('entry-types-analysis', $log->changes['report_code'] ?? null);
        $this->assertSame('pdf', $log->changes['format'] ?? null);
        $this->assertSame($this->entryType->id, $log->changes['filters']['entry_type_id'] ?? null);
        $this->assertTrue(is_string($log->changes['filter_hash'] ?? null));
        $this->assertSame('127.0.0.1', $log->ip);
        $this->assertTrue(is_string($log->user_agent));
    }

    public function test_report_csv_export_writes_audit_log_entry(): void {
        $entry = $this->createEntryTypeDiaryEntry();
        $this->createEntryTypeWorkTimeEntry($entry);

        $this->actingAs($this->user)
            ->withSession($this->dateRangeSession(now()->subDays(30)->toDateString(), now()->toDateString()))
            ->get(route('reports.entry-types', ['entry_type_id' => $this->entryType->id, 'export' => 'csv']))
            ->assertOk();

        $log = AuditLog::query()
            ->where('event', 'report.exported')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(EntryTypeAnalysisReportController::class, $log->auditable_type);
        $this->assertSame($this->organization->id, $log->organization_id);
        $this->assertSame($this->user->id, $log->user_id);
        $this->assertSame('entry-types-analysis', $log->changes['report_code'] ?? null);
        $this->assertSame('csv', $log->changes['format'] ?? null);
        $this->assertSame($this->entryType->id, $log->changes['filters']['entry_type_id'] ?? null);
        $this->assertTrue(is_string($log->changes['filter_hash'] ?? null));
        $this->assertSame('127.0.0.1', $log->ip);
        $this->assertTrue(is_string($log->user_agent));
    }

    public function test_open_issues_drilldown_route_renders_for_entry_type(): void {
        $entry = DiaryEntry::factory()->for($this->user)->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'entry_type_id' => $this->entryType->id,
            'created_at' => now()->subDays(2),
        ]);

        OpenIssue::create([
            'organization_id' => $this->organization->id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'source_type' => OpenIssueSource::Manual->value,
            'source_ref_id' => null,
            'title' => 'EntryType Drilldown Issue',
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
            ->get(route('reports.entry-types.drilldown.open-issues', [
                'entry_type_id' => $this->entryType->id,
                'escalated' => 1,
            ]));

        $response->assertOk();
        $response->assertSeeText('EntryType Drilldown Issue');
    }

    public function test_protocols_drilldown_route_renders_for_entry_type(): void {
        $entry = DiaryEntry::factory()->for($this->user)->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'entry_type_id' => $this->entryType->id,
            'created_at' => now()->subDays(2),
        ]);

        Protocol::factory()->for($entry, 'subject')->state([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::Defect->value,
            'title' => 'EntryType Drilldown Protocol',
            'occurred_at' => now()->subDays(2),
        ])->create();

        $response = $this->actingAs($this->user)
            ->withSession($this->dateRangeSession(now()->subDays(30)->toDateString(), now()->toDateString()))
            ->get(route('reports.entry-types.drilldown.protocols', [
                'entry_type_id' => $this->entryType->id,
            ]));

        $response->assertOk();
        $response->assertSeeText('EntryType Drilldown Protocol');
    }

    public function test_open_issues_drilldown_can_be_exported_as_csv(): void {
        $entry = DiaryEntry::factory()->for($this->user)->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'entry_type_id' => $this->entryType->id,
            'created_at' => now()->subDays(2),
        ]);

        OpenIssue::create([
            'organization_id' => $this->organization->id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'source_type' => OpenIssueSource::Manual->value,
            'source_ref_id' => null,
            'title' => 'EntryType CSV Issue',
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
            ->get(route('reports.entry-types.drilldown.open-issues', [
                'entry_type_id' => $this->entryType->id,
                'export' => 'csv',
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('content-disposition');

        $content = (string) $response->getContent();
        $this->assertStringContainsString('ID;Titel;Status;Severity', $content);
        $this->assertStringContainsString('EntryType CSV Issue', $content);
    }

    public function test_protocols_drilldown_can_be_exported_as_csv(): void {
        $entry = DiaryEntry::factory()->for($this->user)->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'entry_type_id' => $this->entryType->id,
            'created_at' => now()->subDays(2),
        ]);

        Protocol::factory()->for($entry, 'subject')->state([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::Defect->value,
            'title' => 'EntryType CSV Protocol',
            'occurred_at' => now()->subDays(2),
        ])->create();

        $response = $this->actingAs($this->user)
            ->withSession($this->dateRangeSession(now()->subDays(30)->toDateString(), now()->toDateString()))
            ->get(route('reports.entry-types.drilldown.protocols', [
                'entry_type_id' => $this->entryType->id,
                'export' => 'csv',
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('content-disposition');

        $content = (string) $response->getContent();
        $this->assertStringContainsString('ID;Titel;Status;Typ', $content);
        $this->assertStringContainsString('EntryType CSV Protocol', $content);
    }

    public function test_open_issues_drilldown_can_be_exported_as_pdf(): void {
        $entry = DiaryEntry::factory()->for($this->user)->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'entry_type_id' => $this->entryType->id,
            'created_at' => now()->subDays(2),
        ]);

        OpenIssue::create([
            'organization_id' => $this->organization->id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'source_type' => OpenIssueSource::Manual->value,
            'source_ref_id' => null,
            'title' => 'EntryType PDF Issue',
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
            ->get(route('reports.entry-types.drilldown.open-issues', [
                'entry_type_id' => $this->entryType->id,
                'export' => 'pdf',
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_protocols_drilldown_can_be_exported_as_pdf(): void {
        $entry = DiaryEntry::factory()->for($this->user)->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'entry_type_id' => $this->entryType->id,
            'created_at' => now()->subDays(2),
        ]);

        Protocol::factory()->for($entry, 'subject')->state([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::Defect->value,
            'title' => 'EntryType PDF Protocol',
            'occurred_at' => now()->subDays(2),
        ])->create();

        $response = $this->actingAs($this->user)
            ->withSession($this->dateRangeSession(now()->subDays(30)->toDateString(), now()->toDateString()))
            ->get(route('reports.entry-types.drilldown.protocols', [
                'entry_type_id' => $this->entryType->id,
                'export' => 'pdf',
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_drilldown_export_writes_audit_log_entry(): void {
        $entry = $this->createEntryTypeDiaryEntry(null);
        $this->createEntryTypeProtocol($entry, 'Audit Drilldown Protokoll');

        $this->actingAs($this->user)
            ->withSession($this->dateRangeSession(now()->subDays(30)->toDateString(), now()->toDateString()))
            ->get(route('reports.entry-types.drilldown.protocols', [
                'entry_type_id' => $this->entryType->id,
                'export' => 'pdf',
            ]))
            ->assertOk();

        $log = AuditLog::query()
            ->where('event', 'report.exported')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(EntryTypeDrilldownReportController::class, $log->auditable_type);
        $this->assertSame($this->organization->id, $log->organization_id);
        $this->assertSame($this->user->id, $log->user_id);
        $this->assertSame('entry-type-drilldown-protocols', $log->changes['report_code'] ?? null);
        $this->assertSame('pdf', $log->changes['format'] ?? null);
        $this->assertSame($this->entryType->id, $log->changes['filters']['entry_type_id'] ?? null);
        $this->assertTrue(is_string($log->changes['filter_hash'] ?? null));
        $this->assertSame('127.0.0.1', $log->ip);
        $this->assertTrue(is_string($log->user_agent));
    }

    public function test_open_issues_drilldown_export_writes_audit_log_entry(): void {
        $entry = $this->createEntryTypeDiaryEntry(null);
        $this->createEntryTypeOpenIssue($entry, 'Audit Drilldown Punkt');

        $this->actingAs($this->user)
            ->withSession($this->dateRangeSession(now()->subDays(30)->toDateString(), now()->toDateString()))
            ->get(route('reports.entry-types.drilldown.open-issues', [
                'entry_type_id' => $this->entryType->id,
                'escalated' => 1,
                'export' => 'csv',
            ]))
            ->assertOk();

        $log = AuditLog::query()
            ->where('event', 'report.exported')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(EntryTypeDrilldownReportController::class, $log->auditable_type);
        $this->assertSame($this->organization->id, $log->organization_id);
        $this->assertSame($this->user->id, $log->user_id);
        $this->assertSame('entry-type-drilldown-open-issues', $log->changes['report_code'] ?? null);
        $this->assertSame('csv', $log->changes['format'] ?? null);
        $this->assertSame($this->entryType->id, $log->changes['filters']['entry_type_id'] ?? null);
        $this->assertTrue(is_string($log->changes['filter_hash'] ?? null));
        $this->assertSame('127.0.0.1', $log->ip);
        $this->assertTrue(is_string($log->user_agent));
    }

    public function test_protocols_drilldown_csv_export_writes_audit_log_entry(): void {
        $entry = $this->createEntryTypeDiaryEntry(null);
        $this->createEntryTypeProtocol($entry, 'Audit Drilldown Protokoll CSV');

        $this->actingAs($this->user)
            ->withSession($this->dateRangeSession(now()->subDays(30)->toDateString(), now()->toDateString()))
            ->get(route('reports.entry-types.drilldown.protocols', [
                'entry_type_id' => $this->entryType->id,
                'export' => 'csv',
            ]))
            ->assertOk();

        $log = AuditLog::query()
            ->where('event', 'report.exported')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(EntryTypeDrilldownReportController::class, $log->auditable_type);
        $this->assertSame($this->organization->id, $log->organization_id);
        $this->assertSame($this->user->id, $log->user_id);
        $this->assertSame('entry-type-drilldown-protocols', $log->changes['report_code'] ?? null);
        $this->assertSame('csv', $log->changes['format'] ?? null);
        $this->assertSame($this->entryType->id, $log->changes['filters']['entry_type_id'] ?? null);
        $this->assertTrue(is_string($log->changes['filter_hash'] ?? null));
        $this->assertSame('127.0.0.1', $log->ip);
        $this->assertTrue(is_string($log->user_agent));
    }

    public function test_open_issues_drilldown_pdf_export_writes_audit_log_entry(): void {
        $entry = $this->createEntryTypeDiaryEntry(null);
        $this->createEntryTypeOpenIssue($entry, 'Audit Drilldown Punkt PDF');

        $this->actingAs($this->user)
            ->withSession($this->dateRangeSession(now()->subDays(30)->toDateString(), now()->toDateString()))
            ->get(route('reports.entry-types.drilldown.open-issues', [
                'entry_type_id' => $this->entryType->id,
                'escalated' => 1,
                'export' => 'pdf',
            ]))
            ->assertOk();

        $log = AuditLog::query()
            ->where('event', 'report.exported')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(EntryTypeDrilldownReportController::class, $log->auditable_type);
        $this->assertSame($this->organization->id, $log->organization_id);
        $this->assertSame($this->user->id, $log->user_id);
        $this->assertSame('entry-type-drilldown-open-issues', $log->changes['report_code'] ?? null);
        $this->assertSame('pdf', $log->changes['format'] ?? null);
        $this->assertSame($this->entryType->id, $log->changes['filters']['entry_type_id'] ?? null);
        $this->assertTrue(is_string($log->changes['filter_hash'] ?? null));
        $this->assertSame('127.0.0.1', $log->ip);
        $this->assertTrue(is_string($log->user_agent));
    }

    private function createEntryTypeDiaryEntry(?int $plannedMinutes = 60): DiaryEntry {
        $attributes = [
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'entry_type_id' => $this->entryType->id,
            'created_at' => now()->subDays(2),
        ];

        if ($plannedMinutes !== null) {
            $attributes['planned_minutes'] = $plannedMinutes;
        }

        return DiaryEntry::factory()->for($this->user)->create($attributes);
    }

    private function createEntryTypeWorkTimeEntry(DiaryEntry $entry): void {
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
    }

    private function createEntryTypeOpenIssue(DiaryEntry $entry, string $title): OpenIssue {
        return OpenIssue::create([
            'organization_id' => $this->organization->id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'source_type' => OpenIssueSource::Manual->value,
            'source_ref_id' => null,
            'title' => $title,
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
    }

    private function createEntryTypeProtocol(DiaryEntry $entry, string $title): Protocol {
        return Protocol::factory()->for($entry, 'subject')->state([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::Defect->value,
            'title' => $title,
            'occurred_at' => now()->subDays(2),
        ])->create();
    }
}
