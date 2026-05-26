<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetAnalysisReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\OpenIssue\{OpenIssueSeverity, OpenIssueSource, OpenIssueStatus, OpenIssueVisibility};
use App\Enums\Project\ProjectStatus;
use App\Enums\Protocol\ProtocolType;
use App\Models\{Asset, AuditLog, DiaryEntry, OpenIssue, Project, Protocol, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

class AssetAnalysisReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $user;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->user = User::factory()->admin()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'MVP-041 Projekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_route_renders_for_authenticated_user(): void {
        $response = $this->actingAs($this->user)->get(route('reports.assets'));
        $response->assertOk();
        $response->assertSee('Produktanalyse');
    }

    public function test_aggregates_defect_count_and_open_issues_per_asset(): void {
        $assetA = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Pumpe A',
            'asset_no' => 'AS-A-0001',
            'category_code' => 'PUMP',
            'manufacturer' => 'ACME',
            'model' => 'PX-100',
        ]);

        $entry = DiaryEntry::factory()->for($this->user)->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'asset_id' => $assetA->id,
            'created_at' => now()->subDays(2),
        ]);

        Protocol::create([
            'organization_id' => $this->organization->id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'type' => ProtocolType::Defect->value,
            'title' => 'Defekt entdeckt',
            'occurred_at' => now()->subDays(1),
            'created_by_user_id' => $this->user->id,
        ]);

        OpenIssue::create([
            'organization_id' => $this->organization->id,
            'subject_type' => Asset::class,
            'subject_id' => $assetA->id,
            'title' => 'Reparatur',
            'status' => OpenIssueStatus::Open->value,
            'severity' => OpenIssueSeverity::Medium->value,
            'source_type' => OpenIssueSource::Manual->value,
            'visibility' => OpenIssueVisibility::Internal->value,
            'created_by_user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.assets'));
        $response->assertOk();
        $response->assertSee('Pumpe A');

        /** @var list<array<string, mixed>> $rows */
        $rows = $response->viewData('rows');
        $this->assertNotEmpty($rows);
        $row = $rows[0];
        $this->assertSame(1, $row['assetCount']);
        $this->assertSame(1, $row['entryCount']);
        $this->assertSame(1, $row['defectCount']);
        $this->assertSame(1, $row['openIssueCount']);
        $this->assertGreaterThan(0, $row['defectRate']);
    }

    public function test_group_by_model_aggregates_assets(): void {
        Asset::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
            'manufacturer' => 'ACME',
            'model' => 'PX-100',
            'category_code' => 'PUMP',
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.assets', ['group_by' => 'model']));
        $response->assertOk();
        /** @var list<array<string, mixed>> $rows */
        $rows = $response->viewData('rows');
        $this->assertNotEmpty($rows);
        $this->assertSame(2, $rows[0]['assetCount']);
        $this->assertStringContainsString('ACME PX-100', $rows[0]['label']);
    }

    public function test_csv_export_audit_log_is_written(): void {
        Asset::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.assets', ['export' => 'csv']));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $body = $response->getContent();
        $this->assertIsString($body);
        $this->assertStringContainsString('#report:assets-analysis', $body);
        $this->assertStringContainsString('#generated:', $body);
        $this->assertMatchesRegularExpression('/#filter_hash:[0-9a-f]{8}/', $body);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'event' => 'report.exported',
        ]);

        $audit = AuditLog::where('event', 'report.exported')->latest('id')->firstOrFail();
        $changes = $audit->changes ?? [];
        $this->assertIsArray($changes);
        $this->assertArrayHasKey('filter_hash', $changes);
        $fullHash = (string) $changes['filter_hash'];
        $this->assertSame(64, strlen($fullHash));
        $this->assertStringContainsString('#filter_hash:' . substr($fullHash, 0, 8), $body);
    }
}
