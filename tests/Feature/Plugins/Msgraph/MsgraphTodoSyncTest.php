<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphTodoSyncTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Msgraph;

use App\Enums\Task\{TaskPriority, TaskStatus};
use App\Models\{ExternalReference, IntegrationInboxItem, IntegrationOutboxEntry, MsgraphTaskConnection, MsgraphTaskListLink, Project, Task, User};
use App\Plugins\Contracts\{PluginCapability, TaskSyncer};
use App\Plugins\Msgraph\MsgraphPlugin;
use App\Plugins\Msgraph\Observers\MsgraphTodoTaskObserver;
use App\Plugins\Msgraph\Services\MsgraphOutboxDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Microsoft-To-Do-Sync (Feature 102, Schnitt E — Todoist-Muster): Import mit
 * 3-Wege-Abgleich (Konflikt ⇒ Inbox, nie Last-write-wins), Lösch-Markierung
 * statt Löschung, Export mit linkedResource und Basis-Fortschreibung,
 * sechster OAuth-Grant.
 */
final class MsgraphTodoSyncTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        config()->set('plugins.msgraph.enabled', true);
        config()->set('plugins.msgraph.client_id', 'test-client');
        config()->set('plugins.msgraph.client_secret', 'test-secret');
    }

    private function connection(): MsgraphTaskConnection {
        return MsgraphTaskConnection::query()->create([
            'organization_id' => $this->organization->id,
            'access_token' => 'secret-token-1',
            'status' => MsgraphTaskConnection::STATUS_ACTIVE,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function link(Project $project, array $attributes = []): MsgraphTaskListLink {
        return MsgraphTaskListLink::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'todo_list_id' => 'list-1',
            'todo_list_name' => 'WorkDiary-Liste',
            'target_kind' => MsgraphTaskListLink::KIND_PROJECT,
            'project_id' => $project->id,
            'sync_mode' => MsgraphTaskListLink::MODE_TODO_TO_WORKDIARY,
            'status' => MsgraphTaskListLink::STATUS_ACTIVE,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function remoteTask(array $overrides = []): array {
        return $overrides + [
            'id' => 'todo-1',
            'title' => 'Server patchen',
            'status' => 'notStarted',
            'importance' => 'high',
            'body' => ['contentType' => 'text', 'content' => 'Wartungsfenster beachten'],
            'dueDateTime' => ['dateTime' => '2026-08-15T00:00:00.0000000', 'timeZone' => 'UTC'],
        ];
    }

    public function test_plugin_advertises_task_sync_capability(): void {
        $plugin = new MsgraphPlugin();

        $this->assertContains(PluginCapability::TaskSync, $plugin->capabilities());
        $this->assertInstanceOf(TaskSyncer::class, $plugin);
    }

    public function test_oauth_start_requests_tasks_scope(): void {
        $response = $this->actingAs($this->admin)->post(route('admin.msgraph.tasks.oauth.start'));
        $response->assertRedirect();

        $this->assertStringContainsString('Tasks.ReadWrite', urldecode((string) $response->headers->get('Location')));
    }

    public function test_import_creates_task_with_reference_then_unchanged(): void {
        $this->connection();
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $this->link($project);

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/todo/lists/list-1/tasks*' => FakePluginHttp::response(['value' => [$this->remoteTask()]]),
        ]);

        $first = (new MsgraphPlugin())->syncTasks($this->organization);
        $this->assertSame(1, $first['created']);

        $task = Task::query()->firstOrFail();
        $this->assertSame('Server patchen', $task->title);
        $this->assertSame('Wartungsfenster beachten', $task->description);
        $this->assertSame(TaskPriority::High, $task->priority);
        $this->assertSame('2026-08-15', $task->due_date?->format('Y-m-d'));
        $this->assertSame($project->id, $task->project_id);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => MsgraphPlugin::ID,
            'external_type' => MsgraphPlugin::EXT_TYPE_TODO_TASK,
            'external_id' => 'todo-1',
        ]);

        // Replay ohne Änderung → unverändert, keine Dublette.
        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/todo/lists/list-1/tasks*' => FakePluginHttp::response(['value' => [$this->remoteTask()]]),
        ]);
        $second = (new MsgraphPlugin())->syncTasks($this->organization);
        $this->assertSame(1, $second['unchanged']);
        $this->assertSame(1, Task::query()->count());
    }

    public function test_both_sides_changed_stages_conflict_instead_of_overwrite(): void {
        $this->connection();
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $this->link($project);

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/todo/lists/list-1/tasks*' => FakePluginHttp::response(['value' => [$this->remoteTask()]]),
        ]);
        (new MsgraphPlugin())->syncTasks($this->organization);

        // Lokal UND remote unterschiedlich geändert → Konflikt, kein Überschreiben.
        $task = Task::query()->firstOrFail();
        $task->forceFill(['title' => 'Server patchen (lokal umbenannt)'])->save();

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/todo/lists/list-1/tasks*' => FakePluginHttp::response(['value' => [$this->remoteTask(['title' => 'Server patchen (remote umbenannt)'])]]),
        ]);
        $result = (new MsgraphPlugin())->syncTasks($this->organization);

        $this->assertSame(1, $result['conflicts']);
        $this->assertSame('Server patchen (lokal umbenannt)', Task::query()->firstOrFail()->title);
        $this->assertDatabaseHas('integration_inbox_items', [
            'plugin_id' => MsgraphPlugin::ID,
            'case_type' => IntegrationInboxItem::CASE_CONFLICT,
            'dedupe_key' => 'todo-task-conflict:todo-1',
        ]);
    }

    public function test_remote_vanished_task_is_flagged_not_deleted(): void {
        $this->connection();
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $this->link($project);

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/todo/lists/list-1/tasks*' => FakePluginHttp::response(['value' => [$this->remoteTask()]]),
        ]);
        (new MsgraphPlugin())->syncTasks($this->organization);

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/todo/lists/list-1/tasks*' => FakePluginHttp::response(['value' => []]),
        ]);
        $result = (new MsgraphPlugin())->syncTasks($this->organization);

        $this->assertSame(1, $result['inbox']);
        $this->assertSame(1, Task::query()->count()); // nie löschen
        $reference = ExternalReference::query()->where('external_id', 'todo-1')->firstOrFail();
        $this->assertArrayHasKey('remote_deleted_at', (array) $reference->payload);
    }

    public function test_export_creates_remote_task_with_linked_resource_and_updates_on_change(): void {
        $this->connection();
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $this->link($project, ['sync_mode' => MsgraphTaskListLink::MODE_WORKDIARY_TO_TODO]);

        // Ohne Live-Observer anlegen — dieser Test prüft den BATCH-Export des
        // Sync-Laufs; der Observer-Pfad ist in test_local_changes_… abgedeckt.
        $task = MsgraphTodoTaskObserver::suppressed(fn (): Task => Task::query()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'title' => 'Angebot schreiben',
            'status' => TaskStatus::Open->value,
            'priority' => TaskPriority::Urgent->value,
        ]));

        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/todo/lists/list-1/tasks' => FakePluginHttp::response(['id' => 'todo-neu'], 201),
        ]);

        $result = (new MsgraphPlugin())->syncTasks($this->organization);
        $this->assertSame(1, $result['created']);

        $fake->assertSent(function ($request): bool {
            /** @var array{title?: string, importance?: string, linkedResources?: list<array{applicationName?: string}>} $payload */
            $payload = (array) json_decode((string) $request->getBody(), true);

            return $request->getMethod() === 'POST'
                && ($payload['title'] ?? null) === 'Angebot schreiben'
                && ($payload['importance'] ?? null) === 'high'
                && (($payload['linkedResources'][0]['applicationName'] ?? null) === 'WorkDiary');
        });
        $this->assertDatabaseHas('external_references', ['external_id' => 'todo-neu', 'referenceable_id' => $task->id]);

        // Lokale Änderung → PATCH statt Neuanlage (Observer wieder unterdrückt,
        // der Batch-Lauf soll die Änderung übertragen).
        MsgraphTodoTaskObserver::suppressed(fn () => $task->forceFill(['title' => 'Angebot schreiben und senden'])->save());
        $patch = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/todo/lists/list-1/tasks/todo-neu' => FakePluginHttp::response(['id' => 'todo-neu']),
        ]);

        $second = (new MsgraphPlugin())->syncTasks($this->organization);
        $this->assertSame(1, $second['updated']);
        $patch->assertSent(fn ($request): bool => $request->getMethod() === 'PATCH'
            && str_ends_with((string) $request->getUri(), '/tasks/todo-neu'));
    }

    public function test_command_runs_and_reports(): void {
        $this->connection();
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $this->link($project);

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/todo/lists/list-1/tasks*' => FakePluginHttp::response(['value' => []]),
        ]);

        $this->artisan('msgraph:todo-sync')->assertExitCode(0);
    }

    // ── Folgeausbau: Delta-Queries ──────────────────────────────────────

    public function test_import_uses_delta_checkpoint_and_flags_only_reported_removals(): void {
        $this->connection();
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $link = $this->link($project);

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/todo/lists/list-1/tasks/delta*' => FakePluginHttp::response([
                'value' => [$this->remoteTask(), $this->remoteTask(['id' => 'todo-2', 'title' => 'Backup prüfen'])],
                '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/me/todo/lists/list-1/tasks/delta?$deltatoken=chk-1',
            ]),
        ]);
        $first = (new MsgraphPlugin())->syncTasks($this->organization);

        $this->assertSame(2, $first['created']);
        $this->assertStringContainsString('chk-1', (string) $link->fresh()?->delta_link);

        // Folgelauf über den Checkpoint: NUR gemeldete Änderungen — die
        // Teilsicht flaggt nicht alles Fehlende, sondern nur @removed.
        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/todo/lists/list-1/tasks/delta?$deltatoken=chk-1' => FakePluginHttp::response([
                'value' => [['id' => 'todo-1', '@removed' => ['reason' => 'deleted']]],
                '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/me/todo/lists/list-1/tasks/delta?$deltatoken=chk-2',
            ]),
        ]);
        $second = (new MsgraphPlugin())->syncTasks($this->organization);

        $this->assertSame(1, $second['inbox']);
        $fake->assertSent(fn ($request): bool => str_contains(urldecode((string) $request->getUri()), 'chk-1'));
        $this->assertSame(2, Task::query()->count()); // nie löschen
        $this->assertArrayHasKey('remote_deleted_at', (array) ExternalReference::query()->where('external_id', 'todo-1')->firstOrFail()->payload);
        $this->assertArrayNotHasKey('remote_deleted_at', (array) ExternalReference::query()->where('external_id', 'todo-2')->firstOrFail()->payload);
        $this->assertStringContainsString('chk-2', (string) $link->fresh()?->delta_link);
    }

    public function test_stale_delta_checkpoint_restarts_with_full_view(): void {
        $this->connection();
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $link = $this->link($project);
        $link->forceFill(['delta_link' => 'https://graph.microsoft.com/v1.0/me/todo/lists/list-1/tasks/delta?$deltatoken=stale'])->save();

        // Exaktes Muster ZUERST — die Wildcard fängt sonst auch die stale-URL.
        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/todo/lists/list-1/tasks/delta?$deltatoken=stale' => FakePluginHttp::response(null, 410),
            'https://graph.microsoft.com/v1.0/me/todo/lists/list-1/tasks/delta*' => FakePluginHttp::response([
                'value' => [$this->remoteTask()],
                '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/me/todo/lists/list-1/tasks/delta?$deltatoken=frisch',
            ]),
        ]);

        $result = (new MsgraphPlugin())->syncTasks($this->organization);

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['failed']);
        $this->assertStringContainsString('frisch', (string) $link->fresh()?->delta_link);
    }

    // ── Folgeausbau: Live-Export (Observer → Outbox → Dispatcher) ───────

    public function test_local_changes_enqueue_outbox_and_dispatcher_exports(): void {
        $this->connection();
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $this->link($project, ['sync_mode' => MsgraphTaskListLink::MODE_WORKDIARY_TO_TODO]);

        // Anlage → todo-task.create in der Outbox (Observer enqueued nur).
        // Queue::fake hält die Sync-Queue an — sonst würde der Delivery-Job
        // sofort zustellen, bevor der Test den HTTP-Fake registriert hat;
        // die Zustellung erfolgt unten bewusst manuell (Todoist-Muster).
        Queue::fake();
        $task = Task::query()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'title' => 'Firewall-Regeln prüfen',
            'status' => TaskStatus::Open->value,
            'priority' => TaskPriority::High->value,
        ]);

        $createEntry = IntegrationOutboxEntry::query()->withoutGlobalScopes()
            ->where('plugin_id', MsgraphPlugin::ID)
            ->where('operation', MsgraphOutboxDispatcher::OP_TODO_TASK_CREATE)
            ->firstOrFail();

        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/todo/lists/list-1/tasks' => FakePluginHttp::response(['id' => 'todo-live'], 201),
        ]);
        $this->assertTrue((new MsgraphOutboxDispatcher())->dispatch($createEntry));

        $fake->assertSent(function ($request): bool {
            /** @var array{title?: string, linkedResources?: list<array{applicationName?: string}>} $payload */
            $payload = (array) json_decode((string) $request->getBody(), true);

            return $request->getMethod() === 'POST'
                && ($payload['title'] ?? null) === 'Firewall-Regeln prüfen'
                && (($payload['linkedResources'][0]['applicationName'] ?? null) === 'WorkDiary');
        });
        $this->assertDatabaseHas('external_references', ['external_id' => 'todo-live', 'referenceable_id' => $task->id]);

        // Änderung an verknüpfter Aufgabe → todo-task.update, PATCH + Basis-Fortschreibung.
        $task->refresh()->forceFill(['title' => 'Firewall-Regeln prüfen und dokumentieren'])->save();

        $updateEntry = IntegrationOutboxEntry::query()->withoutGlobalScopes()
            ->where('plugin_id', MsgraphPlugin::ID)
            ->where('operation', MsgraphOutboxDispatcher::OP_TODO_TASK_UPDATE)
            ->firstOrFail();

        $patch = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/todo/lists/list-1/tasks/todo-live' => FakePluginHttp::response(['id' => 'todo-live']),
        ]);
        $this->assertTrue((new MsgraphOutboxDispatcher())->dispatch($updateEntry));

        $patch->assertSent(fn ($request): bool => $request->getMethod() === 'PATCH'
            && str_ends_with((string) $request->getUri(), '/tasks/todo-live'));
        $reference = ExternalReference::query()->where('external_id', 'todo-live')->firstOrFail();
        $this->assertSame('Firewall-Regeln prüfen und dokumentieren', $reference->payload['base']['title'] ?? null);
    }

    public function test_import_writes_do_not_enqueue_export_echo(): void {
        $this->connection();
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $this->link($project, ['sync_mode' => MsgraphTaskListLink::MODE_BIDIRECTIONAL]);

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/todo/lists/list-1/tasks/delta*' => FakePluginHttp::response(['value' => [$this->remoteTask()]]),
        ]);
        (new MsgraphPlugin())->syncTasks($this->organization);
        $this->assertSame(1, Task::query()->count());

        // Remote-Änderung übernehmen (3-Wege-Update) — ebenfalls kein Echo.
        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/todo/lists/list-1/tasks/delta*' => FakePluginHttp::response(['value' => [$this->remoteTask(['title' => 'Server patchen (remote)'])]]),
        ]);
        (new MsgraphPlugin())->syncTasks($this->organization);

        $this->assertSame('Server patchen (remote)', Task::query()->firstOrFail()->title);
        $this->assertSame(0, IntegrationOutboxEntry::query()->withoutGlobalScopes()->where('plugin_id', MsgraphPlugin::ID)->count());
    }
}
