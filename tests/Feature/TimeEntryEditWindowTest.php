<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryEditWindowTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Project\ProjectStatus;
use App\Enums\Timesheet\TimesheetStatus;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\Timekeeping\TimeEntryEditPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class TimeEntryEditWindowTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->user = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Edit-Window-Projekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
    }

    private function makeEntry(string $date, array $overrides = []): TimeEntry {
        return TimeEntry::create(array_merge([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => $date,
            'minutes' => 60,
        ], $overrides));
    }

    public function test_owner_can_edit_within_default_window(): void {
        $entry = $this->makeEntry(now()->subDays(3)->toDateString());

        $this->actingAs($this->user)
            ->put(route('projects.time-entries.update', [$this->project, $entry]), [
                'date' => $entry->date->format('Y-m-d'),
                'minutes' => 90,
            ])
            ->assertRedirect();

        $this->assertSame(90, (int) $entry->fresh()->minutes);
    }

    public function test_owner_cannot_edit_after_window(): void {
        $entry = $this->makeEntry(now()->subDays(30)->toDateString());

        $this->actingAs($this->user)
            ->put(route('projects.time-entries.update', [$this->project, $entry]), [
                'date' => $entry->date->format('Y-m-d'),
                'minutes' => 90,
            ])
            ->assertForbidden();

        $this->assertSame(60, (int) $entry->fresh()->minutes);
    }

    public function test_admin_can_edit_outside_window(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $entry = $this->makeEntry(now()->subDays(60)->toDateString());

        $this->actingAs($admin)
            ->put(route('projects.time-entries.update', [$this->project, $entry]), [
                'date' => $entry->date->format('Y-m-d'),
                'minutes' => 120,
            ])
            ->assertRedirect();

        $this->assertSame(120, (int) $entry->fresh()->minutes);
    }

    public function test_exported_entry_is_hard_locked_for_owner(): void {
        $entry = $this->makeEntry(now()->subDay()->toDateString(), ['exported' => true]);

        $policy = app(TimeEntryEditPolicy::class);
        $this->assertTrue($policy->isHardLocked($entry)['locked']);
        $this->assertSame(TimeEntryEditPolicy::REASON_EXPORTED, $policy->blockReason($entry));

        $this->actingAs($this->user)
            ->put(route('projects.time-entries.update', [$this->project, $entry]), [
                'date' => $entry->date->format('Y-m-d'),
                'minutes' => 30,
            ])
            ->assertForbidden();
    }

    public function test_signed_timesheet_locks_owner_edits(): void {
        $timesheet = Timesheet::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'work_date' => now()->subDay()->toDateString(),
            'status' => TimesheetStatus::Signed->value,
        ]);

        $entry = $this->makeEntry(now()->subDay()->toDateString(), ['timesheet_id' => $timesheet->id]);

        $policy = app(TimeEntryEditPolicy::class);
        $this->assertSame(TimeEntryEditPolicy::REASON_TIMESHEET_SIGNED, $policy->blockReason($entry));

        $this->actingAs($this->user)
            ->put(route('projects.time-entries.update', [$this->project, $entry]), [
                'date' => $entry->date->format('Y-m-d'),
                'minutes' => 30,
            ])
            ->assertForbidden();
    }

    public function test_comments_allowed_even_when_entry_is_locked(): void {
        $entry = $this->makeEntry(now()->subDays(60)->toDateString(), ['exported' => true]);

        $this->actingAs($this->user)
            ->post(route('time-entries.comments.store', $entry), ['body' => 'Korrekturhinweis'])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', [
            'commentable_type' => TimeEntry::class,
            'commentable_id' => $entry->id,
            'user_id' => $this->user->id,
            'body' => 'Korrekturhinweis',
        ]);
    }

    public function test_setting_overrides_window_days(): void {
        config()->set('timesheet.edit_window.days', 0);

        $entry = $this->makeEntry(now()->subDay()->toDateString());

        $policy = app(TimeEntryEditPolicy::class);
        $this->assertFalse($policy->canSelfEdit($entry));
        $this->assertSame(TimeEntryEditPolicy::REASON_WINDOW, $policy->blockReason($entry));
    }
}
