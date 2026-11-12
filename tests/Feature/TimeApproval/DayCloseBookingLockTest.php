<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DayCloseBookingLockTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\TimeApproval;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeApproval\DayClosureStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Diary\DayClosure;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\TimeEntry;
use App\Services\Timekeeping\TimeEntryEditPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Der Tagesabschluss sperrt auch die Projektzeiten des Tages (MVP-1551). */
class DayCloseBookingLockTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string DAY = '2030-01-15';

    private User $user;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo(Carbon::parse('2030-01-16 10:00:00'));
        $this->setUpOrganization(['timezone' => 'UTC']);
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Sperrprojekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_closed_day_blocks_self_edit_with_its_own_reason(): void {
        $entry = $this->entry();
        $policy = app(TimeEntryEditPolicy::class);
        $this->assertTrue($this->user->can('update', $entry));

        $this->closure(DayClosureStatus::Closed);

        $this->assertSame(TimeEntryEditPolicy::REASON_DAY_CLOSED, $policy->blockReason($entry->fresh()));
        $this->assertSame(__('day-close.errors.day_closed_reason'), $policy->reasonLabel(TimeEntryEditPolicy::REASON_DAY_CLOSED));
        $this->assertFalse($this->user->can('update', $entry->fresh()));
        $this->assertFalse($this->user->can('delete', $entry->fresh()));
    }

    public function test_correction_state_blocks_and_released_correction_allows_bookings(): void {
        $entry = $this->entry();
        $closure = $this->closure(DayClosureStatus::Correction);
        $this->assertFalse($this->user->can('update', $entry->fresh()));

        // Korrektur freigegeben: Tag wieder offen, nur die Stempel bleiben gesperrt.
        $closure->update(['status' => DayClosureStatus::Open, 'attendance_locked' => true]);
        $this->assertTrue($this->user->can('update', $entry->fresh()));
    }

    public function test_entry_bar_rejects_closed_day_and_accepts_open_day(): void {
        $this->closure(DayClosureStatus::Closed);

        $this->actingAs($this->user)
            ->post(route('today.entry-bar.store'), ['project_id' => $this->project->sqid, 'date' => self::DAY, 'minutes' => 60])
            ->assertSessionHasErrors(['date' => __('day-close.errors.day_entry_locked')]);
        $this->actingAs($this->user)
            ->post(route('today.entry-bar.store'), ['project_id' => $this->project->sqid, 'date' => self::DAY, 'start_time' => '08:00', 'end_time' => '09:00'])
            ->assertSessionHasErrors('date');
        $this->assertSame(0, TimeEntry::query()->count());

        $this->actingAs($this->user)
            ->post(route('today.entry-bar.store'), ['project_id' => $this->project->sqid, 'date' => '2030-01-14', 'minutes' => 60])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, TimeEntry::query()->count());
    }

    public function test_quick_book_rejects_closed_day_in_both_modes(): void {
        $this->closure(DayClosureStatus::Closed);

        $this->actingAs($this->user)
            ->post(route('today.quick-book'), ['project' => $this->project->sqid, 'started_at' => self::DAY . 'T08:00:00', 'ended_at' => self::DAY . 'T09:00:00'])
            ->assertSessionHasErrors('started_at');
        $this->actingAs($this->user)
            ->post(route('today.quick-book'), ['project' => $this->project->sqid, 'minutes' => 30, 'date' => self::DAY])
            ->assertSessionHasErrors('date');
        $this->actingAs($this->user)
            ->post(route('today.quick-book-all'), ['blocks' => [['project' => $this->project->sqid, 'started_at' => self::DAY . 'T08:00:00', 'ended_at' => self::DAY . 'T09:00:00']]])
            ->assertSessionHasErrors('blocks.0.started_at');

        $this->assertSame(0, TimeEntry::query()->count());
    }

    public function test_quick_book_without_date_checks_today_and_stopwatch_does_not_start(): void {
        DayClosure::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'day' => '2030-01-16',
            'status' => DayClosureStatus::Closed,
        ]);

        $this->actingAs($this->user)
            ->post(route('today.quick-book'), ['project' => $this->project->sqid, 'minutes' => 30])
            ->assertSessionHasErrors('date');
        $this->actingAs($this->user)
            ->post(route('stopwatch.start'), ['project_id' => $this->project->sqid])
            ->assertSessionHasErrors(['stopwatch' => __('day-close.errors.day_entry_locked')]);

        $this->assertSame(0, TimeEntry::query()->count());
    }

    public function test_today_page_hides_entry_bar_and_names_the_correction_path(): void {
        $this->entry();
        $this->closure(DayClosureStatus::Closed);

        $this->actingAs($this->user)
            ->get(route('today.show', ['date' => self::DAY]))
            ->assertOk()
            ->assertSee(__('day-close.hint.day_closed'))
            ->assertDontSee('data-entry-bar', false);
    }

    private function entry(): TimeEntry {
        return TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => self::DAY,
            'minutes' => 60,
            'kind' => TimeEntryKind::Work->value,
        ]);
    }

    private function closure(DayClosureStatus $status): DayClosure {
        return DayClosure::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'day' => self::DAY,
            'status' => $status,
        ]);
    }
}
