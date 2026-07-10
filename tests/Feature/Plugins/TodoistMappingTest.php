<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistMappingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{ExternalReference, Project, TodoistConnection, TodoistProjectLink, User};
use App\Plugins\Todoist\Services\TodoistPreflightService;
use App\Plugins\Todoist\TodoistPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Feature 055, MVP-112: Projekt-/Abschnitts-/Benutzerzuordnung + Preflight —
 * org-gescopt, Unique je (Org, Todoist-Projekt), E-Mail-Gleichheit nur als
 * Vorschlag, deterministische Preflight-Zähler über MockHandler-Fixtures.
 */
final class TodoistMappingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private TodoistConnection $connection;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->connection = TodoistConnection::query()->create([
            'organization_id' => $this->organization->id,
            'access_token' => 'secret-token',
            'status' => TodoistConnection::STATUS_ACTIVE,
        ]);
        config()->set('plugins.todoist.client_id', 'cid');
        config()->set('plugins.todoist.client_secret', 'sec');
    }

    public function test_store_link_creates_draft_and_requires_project_for_project_kind(): void {
        FakePluginHttp::fake();
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);

        // Ohne WorkDiary-Projekt bei target_kind=project → Fehler.
        $this->actingAs($this->admin)->post(route('admin.todoist.links.store'), [
            'todoist_project_id' => 'tp-1', 'target_kind' => 'project', 'sync_mode' => 'todoist_to_workdiary',
        ])->assertSessionHas('error');

        $this->actingAs($this->admin)->post(route('admin.todoist.links.store'), [
            'todoist_project_id' => 'tp-1', 'todoist_project_name' => 'Kundenprojekt',
            'target_kind' => 'project', 'project' => $project->sqid,
            'sync_mode' => 'bidirectional',
        ])->assertSessionHas('success');

        $link = TodoistProjectLink::query()->firstOrFail();
        $this->assertSame(TodoistProjectLink::STATUS_DRAFT, $link->status);
        $this->assertSame($project->id, (int) $link->project_id);
        $this->assertTrue($link->importsFromTodoist());
        $this->assertTrue($link->exportsToTodoist());
    }

    public function test_link_is_unique_per_org_and_remote_project(): void {
        FakePluginHttp::fake();

        $this->actingAs($this->admin)->post(route('admin.todoist.links.store'), [
            'todoist_project_id' => 'tp-1', 'target_kind' => 'global_kanban', 'sync_mode' => 'todoist_to_workdiary',
        ])->assertSessionHas('success');
        // Zweites Speichern derselben Remote-ID aktualisiert statt zu duplizieren.
        $this->actingAs($this->admin)->post(route('admin.todoist.links.store'), [
            'todoist_project_id' => 'tp-1', 'target_kind' => 'global_kanban', 'sync_mode' => 'bidirectional',
        ])->assertSessionHas('success');

        $this->assertSame(1, TodoistProjectLink::query()->count());
        $this->assertSame('bidirectional', TodoistProjectLink::query()->firstOrFail()->sync_mode);
    }

    public function test_preflight_counts_special_cases_deterministically(): void {
        $mappedUser = User::factory()->user()->create(['organization_id' => $this->organization->id, 'email' => 'mapped@example.com']);
        User::factory()->user()->create(['organization_id' => $this->organization->id, 'email' => 'suggest@example.com']);
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TodoistPlugin::ID,
            'external_type' => TodoistPlugin::EXT_TYPE_COLLABORATOR,
            'external_id' => 'c-1',
            'referenceable_type' => $mappedUser->getMorphClass(),
            'referenceable_id' => $mappedUser->getKey(),
            'synced_at' => now(),
        ]);

        FakePluginHttp::fake([
            'https://api.todoist.com/api/v1/tasks*' => FakePluginHttp::response([
                'results' => [
                    ['id' => 't-1', 'content' => 'A', 'responsible_uid' => 'c-1'],
                    ['id' => 't-2', 'content' => 'B', 'parent_id' => 't-1', 'responsible_uid' => 'c-unknown'],
                    ['id' => 't-3', 'content' => 'C', 'due' => ['date' => '2026-08-01', 'is_recurring' => true]],
                    ['id' => 't-4', 'content' => 'D', 'due' => ['date' => '2026-08-01', 'datetime' => '2026-08-01T09:00:00Z']],
                ],
                'next_cursor' => null,
            ]),
            'https://api.todoist.com/api/v1/projects/tp-1/collaborators*' => FakePluginHttp::response([
                'results' => [
                    ['id' => 'c-1', 'name' => 'Gemappt', 'email' => 'mapped@example.com'],
                    ['id' => 'c-2', 'name' => 'Vorschlag', 'email' => 'suggest@example.com'],
                    ['id' => 'c-3', 'name' => 'Fremd', 'email' => 'extern@example.com'],
                ],
                'next_cursor' => null,
            ]),
        ]);

        $result = app(TodoistPreflightService::class)->forProject($this->organization, $this->connection, 'tp-1');

        $this->assertSame(4, $result['tasks']);
        $this->assertSame(1, $result['subtasks']);
        $this->assertSame(1, $result['recurring']);
        $this->assertSame(1, $result['timed_due']);
        $this->assertSame(1, $result['unassignable']); // c-unknown; c-1 ist gemappt

        $byId = collect($result['collaborators'])->keyBy('id');
        $this->assertNotNull($byId->get('c-1')['mapped_user']);
        $this->assertNotNull($byId->get('c-2')['suggestion'], 'E-Mail-Gleichheit muss einen Vorschlag erzeugen');
        $this->assertNull($byId->get('c-3')['suggestion'], 'Fremde E-Mail darf keinen Vorschlag erzeugen');
    }

    public function test_collaborator_assignment_is_org_scoped(): void {
        $orgB = \App\Models\Organization::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($orgB->id);
        $stranger = User::factory()->user()->create(['organization_id' => $orgB->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        // Org-fremder Benutzer wird abgelehnt.
        $this->actingAs($this->admin)->post(route('admin.todoist.collaborators.assign'), [
            'collaborator_id' => 'c-9', 'user' => $stranger->sqid,
        ])->assertSessionHas('error');

        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin)->post(route('admin.todoist.collaborators.assign'), [
            'collaborator_id' => 'c-9', 'user' => $member->sqid,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TodoistPlugin::ID,
            'external_type' => TodoistPlugin::EXT_TYPE_COLLABORATOR,
            'external_id' => 'c-9',
            'referenceable_id' => $member->id,
        ]);

        // Zuordnung lösen.
        $this->actingAs($this->admin)->post(route('admin.todoist.collaborators.assign'), [
            'collaborator_id' => 'c-9', 'user' => '',
        ])->assertSessionHas('success');
        $this->assertDatabaseMissing('external_references', ['external_id' => 'c-9']);
    }

    public function test_section_links_are_optional_and_replaceable(): void {
        $link = TodoistProjectLink::query()->create([
            'organization_id' => $this->organization->id,
            'todoist_project_id' => 'tp-1',
            'target_kind' => TodoistProjectLink::KIND_GLOBAL_KANBAN,
            'sync_mode' => TodoistProjectLink::MODE_TODOIST_TO_WORKDIARY,
        ]);

        $this->actingAs($this->admin)->post(route('admin.todoist.links.sections', $link), [
            'sections' => [
                's-1' => ['status' => 'in_progress', 'name' => 'Doing'],
                's-2' => ['status' => '', 'name' => 'Ignored'],
            ],
        ])->assertSessionHas('success');

        $this->assertSame(1, $link->sectionLinks()->count());
        $this->assertSame('in_progress', $link->sectionLinks()->firstOrFail()->task_status);

        // Entfernen durch Leer-Auswahl.
        $this->actingAs($this->admin)->post(route('admin.todoist.links.sections', $link), [
            'sections' => ['s-1' => ['status' => '', 'name' => 'Doing']],
        ])->assertSessionHas('success');
        $this->assertSame(0, $link->sectionLinks()->count());
    }

    public function test_link_of_other_org_is_not_bindable(): void {
        $orgB = \App\Models\Organization::factory()->create();
        $foreign = TodoistProjectLink::query()->create([
            'organization_id' => $orgB->id,
            'todoist_project_id' => 'tp-x',
            'target_kind' => TodoistProjectLink::KIND_GLOBAL_KANBAN,
            'sync_mode' => TodoistProjectLink::MODE_TODOIST_TO_WORKDIARY,
        ]);

        $this->actingAs($this->admin)->post(route('admin.todoist.links.status', $foreign), [
            'status' => 'active',
        ])->assertNotFound();
    }
}
