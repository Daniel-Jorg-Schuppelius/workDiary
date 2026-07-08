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
use App\Models\{Asset, AssetDefect, DiaryEntry, OpenIssue, Project, Protocol, User};
use App\Services\Asset\RecurringDefectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

        $response = $this->actingAs($this->user)
            ->withSession($this->dateRangeSession(now()->subMonth()->startOfMonth(), now()->endOfMonth()))
            ->get(route('reports.assets.drilldown.protocols', [
                'asset_id' => $this->asset->id,
            ]));
        $response->assertOk();
        $response->assertSee('Lager defekt');
    }

    private function defect(string $reportedAt): AssetDefect {
        return AssetDefect::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $this->asset->id,
            'reported_by_user_id' => $this->user->id,
            'reported_at' => $reportedAt,
        ]);
    }

    public function test_recurring_flag_uses_twelve_month_window_not_period(): void {
        Carbon::setTestNow('2026-06-15 12:00:00');
        // 1 Defekt im Berichtszeitraum (Juni) + 2 weitere im 12-Monats-Fenster.
        $this->defect('2026-06-10');
        $this->defect('2026-01-10');
        $this->defect('2025-08-10');

        $rows = app(RecurringDefectService::class)->pareto(
            $this->organization->id,
            Carbon::parse('2026-06-01'),
            Carbon::parse('2026-06-30 23:59:59'),
        );

        $this->assertCount(1, $rows);
        $this->assertSame(1, $rows[0]['total']);        // nur Juni
        $this->assertSame(3, $rows[0]['recent_total']); // 12-Monats-Fenster
        $this->assertTrue($rows[0]['is_recurring']);    // >= 3 in 12 Monaten

        Carbon::setTestNow();
    }

    public function test_recurring_defects_drilldown_lists_pareto(): void {
        Carbon::setTestNow('2026-06-15 12:00:00');
        foreach (['2026-06-01', '2026-06-05', '2026-06-10'] as $d) {
            $this->defect($d);
        }

        $response = $this->actingAs($this->user)
            ->withSession($this->dateRangeSession(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30')))
            ->get(route('reports.assets.drilldown.recurring-defects'));

        $response->assertOk();
        $rows = $response->viewData('rows');
        $this->assertSame($this->asset->id, $rows[0]['asset_id']);
        $this->assertSame(3, $rows[0]['total']);
        $this->assertTrue($rows[0]['is_recurring']);
        $response->assertSee('Pumpe X');
        $response->assertSee(__('Wiederholdefekt'));

        Carbon::setTestNow();
    }

    public function test_recurring_defects_csv_writes_audit_log(): void {
        Carbon::setTestNow('2026-06-15 12:00:00');
        $this->defect('2026-06-10');

        $response = $this->actingAs($this->user)
            ->withSession($this->dateRangeSession(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30')))
            ->get(route('reports.assets.drilldown.recurring-defects', ['export' => 'csv']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'event' => 'report.exported',
        ]);

        Carbon::setTestNow();
    }

    public function test_dossier_shows_recurring_defect_badge(): void {
        Carbon::setTestNow('2026-06-15 12:00:00');
        foreach (['2026-06-01', '2026-04-01', '2026-02-01'] as $d) {
            $this->defect($d);
        }

        $response = $this->actingAs($this->user)->get(route('assets.dossier', $this->asset));

        $response->assertOk();
        $response->assertSee(__('Wiederholdefekt'));

        Carbon::setTestNow();
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
