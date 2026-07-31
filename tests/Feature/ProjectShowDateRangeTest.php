<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectShowDateRangeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Diary\Mode;
use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Enums\Timesheet\TimesheetStatus;
use App\Models\{DiaryEntry, Project, TimeEntry, Timesheet, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Projekt-Detailseite folgt dem globalen Header-Zeitraum (AGENTS.md §8):
 * Zeiterfassungs-, Stundenzettel- und Aufträge-Tab filtern nach dem in der
 * Session gepinnten Zeitraum; „Gesamt" bleibt als All-time-Anker erhalten.
 * Zusätzlich: „nicht abrechenbar"-Badges für Zeiteinträge und Stundenzettel.
 *
 * Zeitraum wird immer via WithGlobalDateRange gepinnt und alle Datumsfelder
 * explizit gesetzt — die Factory-Streuung (date über 3 Monate) wäre sonst flaky.
 */
class ProjectShowDateRangeTest extends TestCase {
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
            'name' => 'Zeitraum-Testprojekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
            'billable' => true,
        ]);
    }

    public function test_time_tab_filters_entries_by_global_range(): void {
        $this->createTimeEntry('2026-06-10', 9, 11, description: 'Eintrag-im-Juni');
        $this->createTimeEntry('2026-05-10', 9, 10, description: 'Eintrag-im-Mai');

        $response = $this->getProjectPinnedToJune();

        $response->assertOk();
        $response->assertSee('Eintrag-im-Juni');
        $response->assertDontSee('Eintrag-im-Mai');
        // Zeitraum-Summe nur Juni (120 min), Gesamt bleibt All-time-Anker (180 min).
        $response->assertViewHas('rangeMinutes', 120);
        $response->assertViewHas('totalMinutes', 180);
    }

    public function test_my_minutes_scoped_to_range_and_user(): void {
        $colleague = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->createTimeEntry('2026-06-10', 9, 11);
        $this->createTimeEntry('2026-05-10', 9, 10);
        $this->createTimeEntry('2026-06-12', 9, 12, user: $colleague);

        $response = $this->getProjectPinnedToJune();

        $response->assertOk();
        $response->assertViewHas('myMinutes', 120);
        $response->assertViewHas('rangeMinutes', 300);
    }

    public function test_timesheets_filtered_by_work_date(): void {
        $this->createTimesheet('2026-06-15');
        $this->createTimesheet('2026-05-15');

        $response = $this->getProjectPinnedToJune();

        $response->assertOk();
        $response->assertViewHas('timesheets', function ($timesheets): bool {
            $dates = $timesheets->getCollection()->map(fn (Timesheet $ts) => $ts->work_date->toDateString());

            return $dates->contains('2026-06-15') && ! $dates->contains('2026-05-15');
        });
    }

    public function test_diary_tab_filters_fixed_entries_but_keeps_backlog(): void {
        DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'mode' => Mode::Fixed->value,
            'start_at' => '2026-05-05 09:00:00',
            'end_at' => '2026-05-05 10:00:00',
            'content' => 'Fixtermin-im-Mai',
        ]);
        DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'mode' => Mode::Backlog->value,
            'start_at' => null,
            'end_at' => null,
            'content' => 'Backlog-ohne-Datum',
        ]);

        $response = $this->getProjectPinnedToJune();

        $response->assertOk();
        $response->assertSee('Backlog-ohne-Datum');
        $response->assertDontSee('Fixtermin-im-Mai');
    }

    public function test_non_billable_time_entry_shows_badge(): void {
        $this->createTimeEntry('2026-06-10', 9, 11, billable: false);

        $response = $this->getProjectPinnedToJune();

        $response->assertOk();
        $response->assertSee(__('nicht abrechenbar'));
    }

    public function test_billable_entries_show_no_badge(): void {
        $this->createTimeEntry('2026-06-10', 9, 11, billable: true);

        $response = $this->getProjectPinnedToJune();

        $response->assertOk();
        $response->assertDontSee(__('nicht abrechenbar'));
    }

    public function test_timesheet_with_non_billable_entries_shows_count_badge(): void {
        $timesheet = $this->createTimesheet('2026-06-15');
        $this->createTimeEntry('2026-06-15', 9, 11, billable: false, timesheet: $timesheet);

        $response = $this->getProjectPinnedToJune();

        $response->assertOk();
        $response->assertViewHas('timesheets', function ($timesheets): bool {
            $ts = $timesheets->getCollection()->first();

            return $ts !== null && (int) $ts->non_billable_count === 1;
        });
        $response->assertSee(__('nicht abrechenbar'));
    }

    /** @return TestResponse<\Illuminate\Http\Response> */
    private function getProjectPinnedToJune(): TestResponse {
        return $this->actingAs($this->user)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('projects.show', $this->project));
    }

    private function createTimeEntry(
        string $date,
        int $fromHour,
        int $toHour,
        ?User $user = null,
        ?bool $billable = true,
        ?Timesheet $timesheet = null,
        string $description = '',
    ): TimeEntry {
        return TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'timesheet_id' => $timesheet?->id,
            'user_id' => ($user ?? $this->user)->id,
            'date' => $date,
            'started_at' => $date . ' ' . str_pad((string) $fromHour, 2, '0', STR_PAD_LEFT) . ':00:00',
            'ended_at' => $date . ' ' . str_pad((string) $toHour, 2, '0', STR_PAD_LEFT) . ':00:00',
            'kind' => TimeEntryKind::Work->value,
            'billable' => $billable,
            'description' => $description,
        ]);
    }

    private function createTimesheet(string $workDate): Timesheet {
        return Timesheet::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'work_date' => $workDate,
            'status' => TimesheetStatus::Draft->value,
        ]);
    }
}
