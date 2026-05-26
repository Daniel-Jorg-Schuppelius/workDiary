<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetDrilldownReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\OpenIssue\{OpenIssueSeverity, OpenIssueSource, OpenIssueStatus, OpenIssueVisibility};
use App\Enums\Project\ProjectStatus;
use App\Enums\Protocol\ProtocolType;
use App\Models\{Asset, DiaryEntry, OpenIssue, Project, Protocol, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

class AssetDrilldownReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $user;

    private Project $project;

    private Asset $asset;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->user = User::factory()->admin()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'MVP-042 Projekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);

        $this->asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Pumpe X',
            'asset_no' => 'AS-X-9001',
            'category_code' => 'PUMP',
            'manufacturer' => 'ACME',
            'model' => 'PX-200',
        ]);
    }

    public function test_open_issues_drilldown_lists_open_issues_for_asset(): void {
        OpenIssue::create([
            'organization_id' => $this->organization->id,
            'subject_type' => Asset::class,
            'subject_id' => $this->asset->id,
            'title' => 'Reparatur fällig',
            'status' => OpenIssueStatus::Open->value,
            'severity' => OpenIssueSeverity::Medium->value,
            'source_type' => OpenIssueSource::Manual->value,
            'visibility' => OpenIssueVisibility::Internal->value,
            'created_by_user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.assets.drilldown.open-issues', [
            'asset_id' => $this->asset->id,
        ]));
        $response->assertOk();
        $response->assertSee('Reparatur fällig');
    }

    public function test_escalated_filter_only_returns_blocked_open_issues(): void {
        OpenIssue::create([
            'organization_id' => $this->organization->id,
            'subject_type' => Asset::class,
            'subject_id' => $this->asset->id,
            'title' => 'Routinewartung',
            'status' => OpenIssueStatus::Open->value,
            'severity' => OpenIssueSeverity::Low->value,
            'source_type' => OpenIssueSource::Manual->value,
            'visibility' => OpenIssueVisibility::Internal->value,
            'created_by_user_id' => $this->user->id,
        ]);
        OpenIssue::create([
            'organization_id' => $this->organization->id,
            'subject_type' => Asset::class,
            'subject_id' => $this->asset->id,
            'title' => 'Notfall blockiert',
            'status' => OpenIssueStatus::Blocked->value,
            'severity' => OpenIssueSeverity::High->value,
            'source_type' => OpenIssueSource::Manual->value,
            'visibility' => OpenIssueVisibility::Internal->value,
            'created_by_user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.assets.drilldown.open-issues', [
            'asset_id' => $this->asset->id,
            'escalated' => 1,
        ]));
        $response->assertOk();
        $response->assertSee('Notfall blockiert');
        $response->assertDontSee('Routinewartung');
    }

    public function test_protocols_drilldown_lists_defect_protocols(): void {
        $entry = DiaryEntry::factory()->for($this->user)->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'asset_id' => $this->asset->id,
        ]);
        Protocol::create([
            'organization_id' => $this->organization->id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'type' => ProtocolType::Defect->value,
            'title' => 'Lager defekt',
            'occurred_at' => now()->subDay(),
            'created_by_user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.assets.drilldown.protocols', [
            'asset_id' => $this->asset->id,
        ]));
        $response->assertOk();
        $response->assertSee('Lager defekt');
    }

    public function test_csv_export_writes_audit_log(): void {
        OpenIssue::create([
            'organization_id' => $this->organization->id,
            'subject_type' => Asset::class,
            'subject_id' => $this->asset->id,
            'title' => 'Audit-Issue',
            'status' => OpenIssueStatus::Open->value,
            'severity' => OpenIssueSeverity::Medium->value,
            'source_type' => OpenIssueSource::Manual->value,
            'visibility' => OpenIssueVisibility::Internal->value,
            'created_by_user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.assets.drilldown.open-issues', [
            'asset_id' => $this->asset->id,
            'export' => 'csv',
        ]));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'event' => 'report.exported',
        ]);
    }
}
