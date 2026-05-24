<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MyYearReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Project, TimeEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

class MyYearReportTest extends TestCase {
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
            'name' => 'Report-Project',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_route_renders_for_authenticated_user(): void {
        $response = $this->actingAs($this->user)
            ->withSession($this->dateRangeYear(2030))
            ->get(route('reports.my-year'));
        $response->assertOk();
        $response->assertSee('Mein Jahr 2030', false);
    }

    public function test_aggregates_minutes_into_year_total(): void {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-03-15',
            'started_at' => '2030-03-15 09:00:00',
            'ended_at' => '2030-03-15 12:00:00',
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
        ]);
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-03-15',
            'started_at' => '2030-03-15 13:00:00',
            'ended_at' => '2030-03-15 14:30:00',
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
        ]);
        // anderes Jahr — darf nicht einfließen
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2029-12-31',
            'started_at' => '2029-12-31 09:00:00',
            'ended_at' => '2029-12-31 17:00:00',
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession($this->dateRangeYear(2030))
            ->get(route('reports.my-year'));
        $response->assertOk();
        // 3:00 + 1:30 = 4:30 h Jahressumme
        $response->assertSee('4:30 h', false);
    }

    public function test_filters_by_kind(): void {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-04-01',
            'started_at' => '2030-04-01 09:00:00',
            'ended_at' => '2030-04-01 11:00:00',
            'kind' => TimeEntryKind::Work->value,
        ]);
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-04-02',
            'started_at' => '2030-04-02 09:00:00',
            'ended_at' => '2030-04-02 14:00:00',
            'kind' => TimeEntryKind::Travel->value,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession($this->dateRangeYear(2030))
            ->get(route('reports.my-year', ['kind' => 'travel']));
        $response->assertOk();
        $response->assertSee('5:00 h', false); // nur Reise = 5:00
        $response->assertDontSee('7:00 h', false); // Summe wäre 7:00
    }

    public function test_requires_authentication(): void {
        $this->get(route('reports.my-year'))->assertRedirect(route('login'));
    }
}
