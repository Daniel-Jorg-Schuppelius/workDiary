<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryBarTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Project\ProjectStatus;
use App\Enums\Task\TaskStatus;
use App\Models\{DiaryEntry, Organization, Project, Task, TimeEntry, User};
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Eingabeleiste auf „Heute" (Toggl-artig): manuelle Buchung (Dauer/Von-Bis)
 * mit Projektwahl direkt in der Leiste + Options-Endpunkt für Aufgabe/Auftrag.
 */
class TimeEntryBarTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->project = $this->makeProject($this->organization->id, 'Leisten-Projekt');
    }

    private function makeProject(int $orgId, string $name): Project {
        return Project::create([
            'organization_id' => $orgId,
            'name' => $name,
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_manual_duration_entry_books_time(): void {
        $this->actingAs($this->user)
            ->post(route('today.entry-bar.store'), [
                'project_id' => $this->project->sqid,
                'date' => '2026-07-07',
                'minutes' => 90,
                'description' => 'Doku geschrieben',
            ])
            ->assertRedirect(route('today.show', ['date' => '2026-07-07']));

        $this->assertDatabaseHas('time_entries', [
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'minutes' => 90,
            'description' => 'Doku geschrieben',
        ]);
    }

    public function test_manual_range_entry_derives_minutes_and_converts_to_utc(): void {
        $this->actingAs($this->user)
            ->post(route('today.entry-bar.store'), [
                'project_id' => $this->project->sqid,
                'date' => '2026-07-07',
                'start_time' => '08:00',
                'end_time' => '10:30',
                'break_minutes' => 30,
            ])
            ->assertRedirect(route('today.show', ['date' => '2026-07-07']));

        $entry = TimeEntry::query()->firstOrFail();
        // 150 Minuten Spanne − 30 Minuten Pause (Model-Hook).
        $this->assertSame(120, (int) $entry->minutes);
        $expectedStart = CarbonImmutable::createFromFormat('Y-m-d H:i', '2026-07-07 08:00', Tz::current())->setTimezone('UTC');
        $this->assertTrue($entry->started_at?->equalTo($expectedStart));
    }

    public function test_manual_range_rolls_over_midnight_to_next_day(): void {
        $this->actingAs($this->user)
            ->post(route('today.entry-bar.store'), [
                'project_id' => $this->project->sqid,
                'date' => '2026-07-07',
                'start_time' => '23:30',
                'end_time' => '00:30',
            ])
            ->assertRedirect(route('today.show', ['date' => '2026-07-07']));

        $entry = TimeEntry::query()->firstOrFail();
        $this->assertSame(60, (int) $entry->minutes);
        $this->assertTrue($entry->ended_at?->greaterThan($entry->started_at));
    }

    public function test_minutes_required_without_range(): void {
        $this->actingAs($this->user)
            ->post(route('today.entry-bar.store'), [
                'project_id' => $this->project->sqid,
                'date' => '2026-07-07',
            ])
            ->assertSessionHasErrors(['minutes']);

        $this->assertDatabaseCount('time_entries', 0);
    }

    public function test_end_time_without_start_time_is_rejected(): void {
        $this->actingAs($this->user)
            ->post(route('today.entry-bar.store'), [
                'project_id' => $this->project->sqid,
                'date' => '2026-07-07',
                'end_time' => '10:00',
                'minutes' => 60,
            ])
            ->assertSessionHasErrors(['start_time']);

        $this->assertDatabaseCount('time_entries', 0);
    }

    public function test_foreign_organization_project_is_rejected(): void {
        $otherOrg = Organization::factory()->create();
        app()->instance('currentOrganization', $otherOrg);
        $foreign = $this->makeProject((int) $otherOrg->id, 'Fremd-Projekt');
        app()->instance('currentOrganization', $this->organization);

        $this->actingAs($this->user)
            ->post(route('today.entry-bar.store'), [
                'project_id' => $foreign->sqid,
                'date' => '2026-07-07',
                'minutes' => 30,
            ])
            ->assertSessionHasErrors(['project_id']);

        $this->assertDatabaseCount('time_entries', 0);
    }

    public function test_task_from_other_project_is_rejected(): void {
        $otherProject = $this->makeProject($this->organization->id, 'Anderes Projekt');
        $foreignTask = Task::create([
            'organization_id' => $this->organization->id,
            'project_id' => $otherProject->id,
            'title' => 'Fremd-Aufgabe',
        ]);

        $this->actingAs($this->user)
            ->post(route('today.entry-bar.store'), [
                'project_id' => $this->project->sqid,
                'task_id' => $foreignTask->sqid,
                'date' => '2026-07-07',
                'minutes' => 30,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('time_entries', 0);
    }

    public function test_options_endpoint_returns_project_tasks_and_orders(): void {
        $task = Task::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'title' => 'Offene Aufgabe',
        ]);
        Task::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'title' => 'Erledigte Aufgabe',
            'status' => TaskStatus::Done->value,
        ]);
        $diary = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'title' => 'Wartungsauftrag',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('today.entry-bar.options', ['project' => $this->project]))
            ->assertOk()
            ->assertJsonCount(1, 'tasks')
            ->assertJsonPath('tasks.0.id', $task->sqid)
            ->assertJsonPath('tasks.0.title', 'Offene Aufgabe');

        $response->assertJsonPath('diaryEntries.0.id', $diary->sqid)
            ->assertJsonPath('diaryEntries.0.label', 'Wartungsauftrag');
    }

    public function test_options_endpoint_rejects_foreign_project(): void {
        $otherOrg = Organization::factory()->create();
        app()->instance('currentOrganization', $otherOrg);
        $foreign = $this->makeProject((int) $otherOrg->id, 'Fremd-Projekt');
        $foreignSqid = $foreign->sqid;
        app()->instance('currentOrganization', $this->organization);

        $this->actingAs($this->user)
            ->getJson(route('today.entry-bar.options', ['project' => $foreignSqid]))
            ->assertNotFound();
    }
}
