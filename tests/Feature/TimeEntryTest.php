<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Project\ProjectStatus;
use App\Models\{Project, TimeEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class TimeEntryTest extends TestCase {
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
            'name' => 'Test-Projekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_create_dialog_renders(): void {
        $this->actingAs($this->user)
            ->get(route('projects.time-entries.create', $this->project) . '?dialog=1')
            ->assertOk()
            ->assertSee(__('Zeiteintrag erfassen'));
    }

    public function test_user_can_log_time(): void {
        $this->actingAs($this->user)
            ->post(route('projects.time-entries.store', $this->project), [
                'date' => '2030-01-15',
                'minutes' => 90,
                'task_id' => null,
                'description' => 'Konzept erstellt',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('time_entries', [
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'minutes' => 90,
            'description' => 'Konzept erstellt',
        ]);
    }

    /**
     * Vollaudit 2026-07 (N2): kaputte datetime-local-Eingaben (z. B. manipuliertes
     * POST) müssen als Validierungsfehler enden — vorher warf die ungeschützte
     * Tz-Umrechnung in prepareForValidation() einen 500er.
     */
    public function test_invalid_range_input_yields_validation_error_not_500(): void {
        $this->actingAs($this->user)
            ->from(route('projects.time-entries.create', $this->project))
            ->post(route('projects.time-entries.store', $this->project), [
                'started_at' => 'kein-datum-99:99',
                'ended_at' => '2030-01-15T18:00',
                'description' => 'Range kaputt',
            ])
            ->assertRedirect(route('projects.time-entries.create', $this->project))
            ->assertSessionHasErrors(['started_at']);

        $this->assertDatabaseMissing('time_entries', ['description' => 'Range kaputt']);
    }

    public function test_owner_can_update_time_entry(): void {
        $entry = TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-01-01',
            'minutes' => 60,
        ]);

        $this->actingAs($this->user)
            ->put(route('projects.time-entries.update', [$this->project, $entry]), [
                'date' => '2030-01-02',
                'minutes' => 120,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('time_entries', [
            'id' => $entry->id,
            'minutes' => 120,
        ]);
    }

    public function test_non_owner_cannot_update_time_entry(): void {
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $entry = TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-01-01',
            'minutes' => 60,
        ]);

        $this->actingAs($other)
            ->put(route('projects.time-entries.update', [$this->project, $entry]), [
                'date' => '2030-01-05',
                'minutes' => 999,
            ])
            ->assertForbidden();
    }

    public function test_non_owner_cannot_delete_time_entry(): void {
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $entry = TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-01-01',
            'minutes' => 60,
        ]);

        $this->actingAs($other)
            ->delete(route('projects.time-entries.destroy', [$this->project, $entry]))
            ->assertForbidden();
    }

    public function test_owner_can_delete_time_entry(): void {
        $entry = TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-01-01',
            'minutes' => 60,
        ]);

        $this->actingAs($this->user)
            ->delete(route('projects.time-entries.destroy', [$this->project, $entry]))
            ->assertRedirect();

        $this->assertDatabaseMissing('time_entries', ['id' => $entry->id]);
    }
}
