<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryTagsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Project\ProjectStatus;
use App\Models\{Project, Tag, TimeEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Tags an Zeiteinträgen (Feature-Wunsch 2026-07-31): Bearbeitung über
 * Projekt-Dialog, Heute-Leiste und Admin-Formular; voll-ersetzende Semantik
 * bei manueller Bearbeitung, org-fremde IDs werden abgewiesen.
 */
class TimeEntryTagsTest extends TestCase {
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
            'name' => 'Tag-Projekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_project_dialog_store_persists_existing_and_new_tags(): void {
        $existing = Tag::create(['name' => 'Telefon', 'organization_id' => $this->organization->id]);

        $this->actingAs($this->user)
            ->post(route('projects.time-entries.store', $this->project), [
                'date' => '2030-01-15',
                'minutes' => 60,
                'description' => 'Mit Tags',
                'tag_ids' => [$existing->sqid],
                'new_tags' => 'Eskalation, Server',
            ])
            ->assertRedirect();

        $entry = TimeEntry::query()->where('description', 'Mit Tags')->firstOrFail();
        $this->assertSame(['Eskalation', 'Server', 'Telefon'], $entry->tags()->pluck('name')->sort()->values()->all());
    }

    public function test_project_dialog_update_replaces_and_clears_tags(): void {
        $keep = Tag::create(['name' => 'Bleibt', 'organization_id' => $this->organization->id]);
        $old = Tag::create(['name' => 'Alt', 'organization_id' => $this->organization->id]);

        $entry = TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-01-15',
            'minutes' => 30,
        ]);
        $entry->tags()->sync([$old->id]);

        $this->actingAs($this->user)
            ->put(route('projects.time-entries.update', [$this->project, $entry]), [
                'date' => '2030-01-15',
                'minutes' => 45,
                'tag_ids' => [$keep->sqid],
            ])
            ->assertRedirect();
        $this->assertSame(['Bleibt'], $entry->fresh()->tags()->pluck('name')->all());

        // Leere Auswahl leert (manuelle Bearbeitung = voll-ersetzend).
        $this->actingAs($this->user)
            ->put(route('projects.time-entries.update', [$this->project, $entry]), [
                'date' => '2030-01-15',
                'minutes' => 45,
            ])
            ->assertRedirect();
        $this->assertSame(0, $entry->fresh()->tags()->count());
    }

    public function test_foreign_org_tag_id_is_rejected(): void {
        $foreignTag = Tag::query()->withoutGlobalScopes()->create([
            'name' => 'Fremd',
            'organization_id' => $this->organization->id + 999,
        ]);

        $this->actingAs($this->user)
            ->from(route('projects.show', $this->project))
            ->post(route('projects.time-entries.store', $this->project), [
                'date' => '2030-01-15',
                'minutes' => 60,
                'description' => 'Fremder Tag',
                'tag_ids' => [$foreignTag->id],
            ])
            ->assertSessionHasErrors('tag_ids.0');

        $this->assertSame(0, TimeEntry::query()->count());
    }

    public function test_entry_bar_store_attaches_tags(): void {
        $this->actingAs($this->user)
            ->post(route('today.entry-bar.store'), [
                'project_id' => $this->project->sqid,
                'date' => '2030-01-15',
                'minutes' => 90,
                'description' => 'Leisten-Buchung',
                'new_tags' => 'Vor-Ort',
            ])
            ->assertRedirect();

        $entry = TimeEntry::query()->where('description', 'Leisten-Buchung')->firstOrFail();
        $this->assertSame(['Vor-Ort'], $entry->tags()->pluck('name')->all());
    }

    public function test_admin_store_attaches_tags(): void {
        $this->actingAs($this->user)
            ->post(route('admin-time-entries.store'), [
                'date' => '2030-01-15',
                'minutes' => 30,
                'activity_type' => 'admin',
                'description' => 'Verwaltung mit Tag',
                'new_tags' => 'Orga',
            ])
            ->assertRedirect();

        $entry = TimeEntry::query()->where('description', 'Verwaltung mit Tag')->firstOrFail();
        $this->assertSame(['Orga'], $entry->tags()->pluck('name')->all());
    }

    public function test_new_tag_matches_existing_name_case_insensitively(): void {
        Tag::create(['name' => 'Wartung', 'organization_id' => $this->organization->id]);

        $this->actingAs($this->user)
            ->post(route('projects.time-entries.store', $this->project), [
                'date' => '2030-01-15',
                'minutes' => 15,
                'description' => 'Kleinschreibung',
                'new_tags' => 'wartung',
            ])
            ->assertRedirect();

        $entry = TimeEntry::query()->where('description', 'Kleinschreibung')->firstOrFail();
        $this->assertSame(['Wartung'], $entry->tags()->pluck('name')->all());
        $this->assertSame(1, Tag::query()->count());
    }
}
