<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryLockedMonthTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Time;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeApproval\MonthClosureStatus;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\{MonthClosure, TimeEntry};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Die Monatssperre beim Buchen gilt für den, dem der Eintrag gehört — nicht
 * für eine `user_id` aus der Eingabe (Vollscan 2026-10-21).
 */
class TimeEntryLockedMonthTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $owner;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->owner = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Sperrprojekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->owner->id,
        ]);
        MonthClosure::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->owner->id,
            'period_year' => 2030,
            'period_month' => 6,
            'status' => MonthClosureStatus::Approved,
        ]);
    }

    public function test_foreign_user_id_does_not_open_the_own_closed_month(): void {
        $colleague = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->owner)
            ->post(route('projects.time-entries.store', $this->project), [
                'date' => '2030-06-14',
                'minutes' => 60,
                'user_id' => $colleague->id,
            ])
            ->assertSessionHasErrors('date');

        $this->assertDatabaseMissing('time_entries', ['user_id' => $this->owner->id, 'date' => '2030-06-14']);
    }

    public function test_editing_another_persons_entry_checks_the_owners_month(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $entry = TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'date' => '2030-07-10',
            'minutes' => 45,
        ]);

        $this->actingAs($admin)
            ->put(route('projects.time-entries.update', [$this->project, $entry]), [
                'date' => '2030-06-20',
                'minutes' => 45,
            ])
            ->assertSessionHasErrors('date');

        $this->assertSame('2030-07-10', $entry->fresh()->date->toDateString());
    }
}
