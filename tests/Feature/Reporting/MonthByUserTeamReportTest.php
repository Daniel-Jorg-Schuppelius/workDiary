<?php
/*
 * Created on   : Mon May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MonthByUserTeamReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Project, TimeEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

class MonthByUserTeamReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    private User $user;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Demo',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_render(): void {
        $this->getWithYearRange()->assertOk();
    }

    public function test_non_admin_forbidden(): void {
        $response = $this->actingAs($this->user)
            ->withSession($this->dateRangeYear(2030))
            ->get(route('reports.month-by-user-team'));
        $response->assertForbidden();
    }

    public function test_aggregates_minutes_per_month_per_user(): void {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-03-04',
            'started_at' => '2030-03-04 09:00:00',
            'ended_at' => '2030-03-04 12:00:00',
            'kind' => TimeEntryKind::Work->value,
        ]);
        $this->getWithYearRange()->assertOk()->assertSee('3:00');
    }

    public function test_csv_export_returns_download(): void {
        $response = $this->getWithYearRange(['export' => 'csv']);
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('monat-team-2030.csv', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_xlsx_export_returns_download(): void {
        $response = $this->getWithYearRange(['export' => 'xlsx']);
        $response->assertOk();
        $response->assertHeader('Content-Type', \App\Support\XlsxExport::MIME);
        $this->assertStringContainsString('monat-team-2030.xlsx', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_pdf_export_returns_pdf(): void {
        $response = $this->getWithYearRange(['export' => 'pdf']);
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_requires_authentication(): void {
        $this->get(route('reports.month-by-user-team'))->assertRedirect(route('login'));
    }

    private function getWithYearRange(array $parameters = []): TestResponse {
        return $this->actingAs($this->admin)
            ->withSession($this->dateRangeYear(2030))
            ->get(route('reports.month-by-user-team', $parameters));
    }
}
