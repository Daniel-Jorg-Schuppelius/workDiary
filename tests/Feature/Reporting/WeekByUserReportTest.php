<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WeekByUserReportTest.php
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

class WeekByUserReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $user;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Demo',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_route_renders(): void {
        $response = $this->actingAs($this->user)->get(route('reports.week-by-user'));
        $response->assertOk();
    }

    public function test_aggregates_minutes_per_day(): void {
        // Monday 2030-04-01 (week 14)
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-04-01',
            'started_at' => '2030-04-01 09:00:00',
            'ended_at' => '2030-04-01 11:00:00',
            'kind' => TimeEntryKind::Work->value,
        ]);

        $response = $this->getWithWeekRange('reports.week-by-user');
        $response->assertOk();
        $response->assertSee('2:00');
    }

    public function test_csv_export_returns_download(): void {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-04-01',
            'started_at' => '2030-04-01 09:00:00',
            'ended_at' => '2030-04-01 11:00:00',
            'kind' => TimeEntryKind::Work->value,
        ]);

        $response = $this->getWithWeekRange('reports.week-by-user', ['export' => 'csv']);
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('woche_2030-W14.csv', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('120', $response->getContent() ?: '');
    }

    public function test_xlsx_export_returns_download(): void {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-04-01',
            'started_at' => '2030-04-01 09:00:00',
            'ended_at' => '2030-04-01 11:00:00',
            'kind' => TimeEntryKind::Work->value,
        ]);

        $response = $this->getWithWeekRange('reports.week-by-user', ['export' => 'xlsx']);
        $response->assertOk();
        $response->assertHeader('Content-Type', \App\Support\XlsxExport::MIME);
        $this->assertStringContainsString('woche_2030-W14.xlsx', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_pdf_export_returns_download(): void {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-04-01',
            'started_at' => '2030-04-01 09:00:00',
            'ended_at' => '2030-04-01 11:00:00',
            'kind' => TimeEntryKind::Work->value,
        ]);

        $response = $this->getWithWeekRange('reports.week-by-user', ['export' => 'pdf']);
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_requires_authentication(): void {
        $this->get(route('reports.week-by-user'))->assertRedirect(route('login'));
    }

    private function getWithWeekRange(string $routeName, array $parameters = []): TestResponse {
        return $this->actingAs($this->user)
            ->withSession($this->dateRangeWeek(2030, 14))
            ->get(route($routeName, $parameters));
    }
}
