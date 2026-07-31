<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuickBookTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Project\ProjectStatus;
use App\Models\{Attendance, Customer, Organization, Project, Task, TimeEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Rang 37 Quick-Buchung: der offene Block wird korrekt gebucht — sowohl über
 * das No-JS-Formular (Redirect) als auch über den JSON-Weg (Drag/Ctrl+Enter).
 */
class QuickBookTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->project = $this->makeProject($this->organization->id, 'Test-Projekt');
    }

    private function makeProject(int $orgId, string $name): Project {
        return Project::create([
            'organization_id' => $orgId,
            'name' => $name,
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_open_block_is_booked_via_no_js_form_and_redirects(): void {
        $this->actingAs($this->user)
            ->post(route('today.quick-book'), [
                'project' => $this->project->sqid,
                'started_at' => '2026-07-07T08:00:00',
                'ended_at' => '2026-07-07T10:30:00',
            ])
            ->assertRedirect(route('today.show', ['date' => '2026-07-07']));

        // Minuten aus der Spanne (150) vom TimeEntry-Hook berechnet.
        $this->assertDatabaseHas('time_entries', [
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'minutes' => 150,
        ]);
    }

    public function test_quick_book_returns_json_for_drag_path(): void {
        $this->actingAs($this->user)
            ->postJson(route('today.quick-book'), [
                'project' => $this->project->sqid,
                'started_at' => '2026-07-07T13:00:00',
                'ended_at' => '2026-07-07T14:15:00',
            ])
            ->assertStatus(201)
            ->assertJson(['ok' => true])
            ->assertJsonPath('entry.minutes', 75);
    }

    public function test_duration_mode_books_minutes_without_range(): void {
        $this->actingAs($this->user)
            ->post(route('today.quick-book'), [
                'project' => $this->project->sqid,
                'minutes' => 45,
                'date' => '2026-07-07',
                'description' => 'Nachtrag',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('time_entries', [
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'minutes' => 45,
            'description' => 'Nachtrag',
        ]);
    }

    public function test_time_input_is_required(): void {
        $this->actingAs($this->user)
            ->postJson(route('today.quick-book'), ['project' => $this->project->sqid])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['started_at', 'minutes']);

        $this->assertDatabaseCount('time_entries', 0);
    }

    public function test_today_page_limits_drag_targets_and_groups_project_select(): void {
        // Anwesenheit ohne gebuchte Spannen → genau ein offener Block heute.
        Attendance::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'started_at' => now()->subHours(2),
            'date' => now()->startOfDay(),
        ]);

        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Muster GmbH',
        ]);
        foreach (range(1, 12) as $i) {
            Project::create([
                'organization_id' => $this->organization->id,
                'customer_id' => $customer->id,
                'name' => sprintf('Projekt %02d', $i),
                'status' => ProjectStatus::Active->value,
                'created_by' => $this->user->id,
            ]);
        }
        // Dauer-Eintrag (ohne Spanne): macht das Projekt „zuletzt verwendet",
        // ohne den offenen Block zu verkleinern.
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => now()->toDateString(),
            'minutes' => 30,
        ]);

        $html = $this->actingAs($this->user)
            ->get(route('today.show'))
            ->assertOk()
            ->getContent() ?: '';

        // Nur die Top 10 als Drag-Ziele; der Rest (inkl. des automatischen
        // „Wartung"-Projekts aus Customer::booted()) steckt im Dropdown.
        $activeProjects = Project::query()->where('status', ProjectStatus::Active->value)->count();
        $this->assertGreaterThan(10, $activeProjects);
        $this->assertSame(10, substr_count($html, 'data-qb-target'));
        $this->assertStringContainsString(sprintf('+ %d weitere im Dropdown', $activeProjects - 10), $html);
        // Dropdown gruppiert: „Zuletzt verwendet" zuerst, Rest je Kunde.
        $this->assertStringContainsString('Zuletzt verwendet', $html);
        $this->assertStringContainsString('optgroup label="Muster GmbH"', $html);
    }

    public function test_foreign_organization_project_is_rejected(): void {
        $otherOrg = Organization::factory()->create();
        app()->instance('currentOrganization', $otherOrg);
        $foreign = $this->makeProject((int) $otherOrg->id, 'Fremd-Projekt');
        app()->instance('currentOrganization', $this->organization);

        $this->actingAs($this->user)
            ->post(route('today.quick-book'), [
                'project' => $foreign->sqid,
                'started_at' => '2026-07-07T08:00:00',
                'ended_at' => '2026-07-07T09:00:00',
            ])
            ->assertNotFound();

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
            ->post(route('today.quick-book'), [
                'project' => $this->project->sqid,
                'task' => $foreignTask->sqid,
                'started_at' => '2026-07-07T08:00:00',
                'ended_at' => '2026-07-07T09:00:00',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('time_entries', 0);
    }
}
