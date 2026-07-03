<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MyMonthReportTest.php
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

class MyMonthReportTest extends TestCase {
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
        $response = $this->getWithMonthRange('reports.my-month');
        $response->assertOk();
    }

    public function test_lists_entries_grouped_by_day(): void {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-04-10',
            'started_at' => '2030-04-10 08:00:00',
            'ended_at' => '2030-04-10 10:30:00',
            'kind' => TimeEntryKind::Work->value,
            'description' => 'Konzept-Workshop',
        ]);

        $response = $this->getWithMonthRange('reports.my-month');
        $response->assertOk();
        $response->assertSee('Konzept-Workshop');
        $response->assertSee('2:30 h', false);
    }

    public function test_requires_authentication(): void {
        $this->get(route('reports.my-month'))->assertRedirect(route('login'));
    }

    public function test_csv_export_returns_download(): void {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-04-10',
            'started_at' => '2030-04-10 08:00:00',
            'ended_at' => '2030-04-10 10:00:00',
            'kind' => TimeEntryKind::Work->value,
            'description' => 'Workshop',
        ]);
        $response = $this->getWithMonthRange('reports.my-month', ['export' => 'csv']);
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('mein-monat-2030-04.csv', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Workshop', $response->getContent() ?: '');
        $this->assertStringContainsString('120', $response->getContent() ?: ''); // 2h = 120 min
        $this->assertStringContainsString('#report:my-month', $response->getContent() ?: '');
    }

    public function test_xlsx_export_returns_download(): void {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-04-10',
            'started_at' => '2030-04-10 08:00:00',
            'ended_at' => '2030-04-10 10:00:00',
            'kind' => TimeEntryKind::Work->value,
            'description' => 'Workshop',
        ]);
        $response = $this->getWithMonthRange('reports.my-month', ['export' => 'xlsx']);
        $response->assertOk();
        $response->assertHeader('Content-Type', \App\Support\XlsxExport::MIME);
        $this->assertStringContainsString('mein-monat-2030-04.xlsx', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_pdf_export_returns_download(): void {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-04-10',
            'started_at' => '2030-04-10 08:00:00',
            'ended_at' => '2030-04-10 10:00:00',
            'kind' => TimeEntryKind::Work->value,
            'description' => 'Workshop',
        ]);
        $response = $this->getWithMonthRange('reports.my-month', ['export' => 'pdf']);
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('mein-monat-2030-04.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    private function getWithMonthRange(string $routeName, array $parameters = []): TestResponse {
        return $this->actingAs($this->user)
            ->withSession($this->dateRangeMonth(2030, 4))
            ->get(route($routeName, $parameters));
    }
}
