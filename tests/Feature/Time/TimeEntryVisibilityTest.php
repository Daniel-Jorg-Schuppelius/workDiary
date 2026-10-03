<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryVisibilityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Time;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Enums\Timesheet\TimesheetStatus;
use App\Enums\User\Permission;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\{TimeEntry, Timesheet};
use App\Services\Timeline\DiaryEntryTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\{BuildsPolicyActors, WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Sicht auf Zeiten anderer (MVP-1073, Nachtrag): Wer nicht alle Zeiten sehen
 * darf, sieht in Listen und Summen nur die eigenen — am Projekt, in der
 * Fallakte, in der Auftrags-Timeline und am Kunden. Admin, Buchhaltung und
 * `timeEntry.viewAny` sehen alles.
 */
class TimeEntryVisibilityTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $worker;

    private User $colleague;

    private User $accounting;

    private Customer $customer;

    private Project $project;

    private DiaryEntry $order;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->worker = $this->orgUser();
        $this->colleague = $this->orgUser();
        $this->accounting = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Sicht-Testprojekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->worker->id,
            'billable' => true,
        ]);
        $this->order = DiaryEntry::factory()->for($this->worker)->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'project_id' => $this->project->id,
            'title' => 'Sicht-Testauftrag',
        ]);

        $this->createEntry($this->worker, '2026-06-10', 11, 'Eigene-Arbeit');
        $this->createEntry($this->colleague, '2026-06-11', 12, 'Kollegen-Arbeit');
    }

    public function test_scope_limits_plain_users_to_their_own_entries(): void {
        $viewer = $this->orgUser();
        $this->grantPermissions($viewer, [Permission::TimeEntryViewAny]);
        // Ohne Anfrage setzt keine Middleware den Rollen-Kontext der Organisation.
        $this->actAsTeam($this->organization);

        $this->assertFalse($this->worker->canViewAllTimeEntries());
        $this->assertTrue($this->accounting->canViewAllTimeEntries());
        $this->assertTrue($viewer->canViewAllTimeEntries());

        $this->assertSame(['Eigene-Arbeit'], TimeEntry::query()->visibleTo($this->worker)->pluck('description')->all());
        $this->assertSame(2, TimeEntry::query()->visibleTo($this->accounting)->count());
        $this->assertSame(2, TimeEntry::query()->visibleTo($viewer)->count());
    }

    public function test_project_page_shows_only_own_times_without_the_right(): void {
        $own = $this->projectPage($this->worker);
        $own->assertOk();
        $own->assertSee('Eigene-Arbeit');
        $own->assertDontSee('Kollegen-Arbeit');
        $own->assertSee(__('nur eigene Zeiten'));
        $own->assertViewHas('seesAllTimes', false);
        $own->assertViewHas('totalMinutes', 120);
        $own->assertViewHas('rangeMinutes', 120);

        $all = $this->projectPage($this->accounting);
        $all->assertOk();
        $all->assertSee('Eigene-Arbeit');
        $all->assertSee('Kollegen-Arbeit');
        $all->assertDontSee(__('nur eigene Zeiten'));
        $all->assertViewHas('seesAllTimes', true);
        $all->assertViewHas('totalMinutes', 300);
        $all->assertViewHas('rangeMinutes', 300);
    }

    public function test_permission_time_entry_view_any_opens_the_full_view(): void {
        $viewer = $this->orgUser();
        $this->grantPermissions($viewer, [Permission::TimeEntryViewAny]);

        $this->projectPage($viewer)
            ->assertOk()
            ->assertSee('Kollegen-Arbeit')
            ->assertViewHas('totalMinutes', 300);
    }

    public function test_project_page_lists_only_own_timesheets_without_the_right(): void {
        foreach ([$this->worker, $this->colleague] as $owner) {
            Timesheet::create([
                'organization_id' => $this->organization->id,
                'project_id' => $this->project->id,
                'user_id' => $owner->id,
                'work_date' => '2026-06-10',
                'status' => TimesheetStatus::Draft->value,
            ]);
        }

        $this->projectPage($this->worker)
            ->assertViewHas('timesheets', fn($timesheets): bool => $timesheets->total() === 1
                && (int) $timesheets->first()->user_id === $this->worker->id);
        $this->projectPage($this->accounting)
            ->assertViewHas('timesheets', fn($timesheets): bool => $timesheets->total() === 2);
    }

    public function test_case_file_shows_only_own_times_without_the_right(): void {
        $own = $this->actingAs($this->worker)->get(route('diary.case-file', $this->order));
        $own->assertOk();
        $own->assertSee('Eigene-Arbeit');
        $own->assertDontSee('Kollegen-Arbeit');
        $own->assertSee(__('nur eigene Zeiten'));
        $own->assertViewHas('totalMinutes', 120);

        $all = $this->actingAs($this->accounting)->get(route('diary.case-file', $this->order));
        $all->assertOk();
        $all->assertSee('Kollegen-Arbeit');
        $all->assertDontSee(__('nur eigene Zeiten'));
        $all->assertViewHas('totalMinutes', 300);
    }

    public function test_order_timeline_hides_time_items_of_others(): void {
        $timeline = app(DiaryEntryTimelineService::class);
        $this->actAsTeam($this->organization);

        $this->actingAs($this->worker);
        $own = $timeline->forDiaryEntry($this->order, $this->worker, ['time']);
        $this->assertCount(1, $own['items']);
        $this->assertStringContainsString('Eigene-Arbeit', (string) $own['items'][0]->summary);

        $this->actingAs($this->accounting);
        $this->assertCount(2, $timeline->forDiaryEntry($this->order, $this->accounting, ['time'])['items']);
    }

    public function test_customer_page_counts_only_own_times_without_the_right(): void {
        $own = $this->customerPage($this->worker);
        $own->assertOk();
        $own->assertSee(__('nur eigene Zeiten'));
        $own->assertViewHas('seesAllTimes', false);
        $own->assertViewHas('totalMinutes', 120);
        $own->assertViewHas('rangeMinutes', 120);
        $own->assertViewHas('statsTotal', fn(array $stats): bool => $stats['total_minutes'] === 120);
        $own->assertViewHas('statsRange', fn(array $stats): bool => $stats['total_minutes'] === 120);

        $all = $this->customerPage($this->accounting);
        $all->assertOk();
        $all->assertDontSee(__('nur eigene Zeiten'));
        $all->assertViewHas('totalMinutes', 300);
        $all->assertViewHas('rangeMinutes', 300);
        $all->assertViewHas('statsTotal', fn(array $stats): bool => $stats['total_minutes'] === 300);
    }

    /** @return TestResponse<\Illuminate\Http\Response> */
    private function projectPage(User $viewer): TestResponse {
        return $this->actingAs($viewer)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('projects.show', $this->project));
    }

    /** @return TestResponse<\Illuminate\Http\Response> */
    private function customerPage(User $viewer): TestResponse {
        return $this->actingAs($viewer)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('customers.show', $this->customer));
    }

    private function createEntry(User $user, string $date, int $toHour, string $description): TimeEntry {
        return TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'diary_entry_id' => $this->order->id,
            'user_id' => $user->id,
            'date' => $date,
            'started_at' => $date . ' 09:00:00',
            'ended_at' => $date . ' ' . $toHour . ':00:00',
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'description' => $description,
        ]);
    }
}
