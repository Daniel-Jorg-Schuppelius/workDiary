<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\Task\{TaskPriority, TaskStatus};
use App\Models\{ExternalReference, IntegrationInboxItem, Task, TodoistConnection, TodoistProjectLink, User};
use App\Plugins\Todoist\Services\TodoistImportService;
use App\Plugins\Todoist\TodoistPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Feature 055, MVP-113: Import mit stabilen Fremdreferenzen — Feldadapter,
 * Idempotenz (zweiter Lauf = 0 Änderungen), Reopen-Regel, Unteraufgaben nur
 * innerhalb derselben Zuordnung, 3-Wege-Feldkonflikt → Integrations-Inbox,
 * kein Last-write-wins.
 */
final class TodoistImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private TodoistConnection $connection;
    private TodoistProjectLink $link;
    private TodoistImportService $imports;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->connection = TodoistConnection::query()->create([
            'organization_id' => $this->organization->id,
            'access_token' => 'secret-token',
            'status' => TodoistConnection::STATUS_ACTIVE,
        ]);
        $this->link = TodoistProjectLink::query()->create([
            'organization_id' => $this->organization->id,
            'todoist_project_id' => 'tp-1',
            'todoist_project_name' => 'Sync-Projekt',
            'target_kind' => TodoistProjectLink::KIND_GLOBAL_KANBAN,
            'sync_mode' => TodoistProjectLink::MODE_BIDIRECTIONAL,
            'status' => TodoistProjectLink::STATUS_ACTIVE,
        ]);
        $this->imports = app(TodoistImportService::class);
        config()->set('plugins.todoist.client_id', 'cid');
        config()->set('plugins.todoist.client_secret', 'sec');
    }

    /** @param list<array<string, mixed>> $tasks */
    private function fakeTasks(array $tasks): void {
        FakePluginHttp::fake([
            'https://api.todoist.com/api/v1/tasks*' => FakePluginHttp::response(['results' => $tasks, 'next_cursor' => null]),
        ]);
    }

    public function test_import_creates_tasks_with_field_adapter(): void {
        $this->fakeTasks([[
            'id' => 't-1', 'content' => 'Angebot schreiben', 'description' => 'Details',
            'priority' => 4, 'due' => ['date' => '2026-08-01'], 'duration' => ['amount' => 90, 'unit' => 'minute'],
        ]]);

        $counters = $this->imports->syncLink($this->link, $this->connection);

        $this->assertSame(1, $counters['created']);
        $task = Task::query()->firstOrFail();
        $this->assertSame('Angebot schreiben', $task->title);
        $this->assertSame(TaskPriority::Urgent, $task->priority); // API 4 = höchste
        $this->assertSame('2026-08-01', $task->due_date?->format('Y-m-d'));
        $this->assertTrue((bool) $task->is_global);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TodoistPlugin::ID,
            'external_type' => TodoistPlugin::EXT_TYPE_TASK,
            'external_id' => 't-1',
            'referenceable_id' => $task->id,
        ]);
    }

    public function test_second_run_is_idempotent(): void {
        $payload = [['id' => 't-1', 'content' => 'A', 'priority' => 1]];
        $this->fakeTasks($payload);
        $this->imports->syncLink($this->link, $this->connection);

        $this->fakeTasks($payload);
        $counters = $this->imports->syncLink($this->link, $this->connection);

        $this->assertSame(0, $counters['created']);
        $this->assertSame(0, $counters['updated']);
        $this->assertSame(1, $counters['unchanged']);
        $this->assertSame(1, Task::query()->count());
    }

    public function test_remote_change_updates_local_task(): void {
        $this->fakeTasks([['id' => 't-1', 'content' => 'Alt', 'priority' => 1]]);
        $this->imports->syncLink($this->link, $this->connection);

        $this->fakeTasks([['id' => 't-1', 'content' => 'Neu', 'priority' => 3]]);
        $counters = $this->imports->syncLink($this->link, $this->connection);

        $this->assertSame(1, $counters['updated']);
        $task = Task::query()->firstOrFail();
        $this->assertSame('Neu', $task->title);
        $this->assertSame(TaskPriority::High, $task->priority);
    }

    public function test_bilateral_field_change_becomes_conflict_not_overwrite(): void {
        $this->fakeTasks([['id' => 't-1', 'content' => 'Basis', 'priority' => 1]]);
        $this->imports->syncLink($this->link, $this->connection);

        // Lokal UND remote geändert (unterschiedlich) → Konflikt, kein Überschreiben.
        // Queue::fake() hält den P4-Export zurück (asynchrones Fenster): die
        // lokale Änderung ist noch nicht übertragen, wenn der Import läuft.
        \Illuminate\Support\Facades\Queue::fake();
        Task::query()->firstOrFail()->forceFill(['title' => 'Lokal geändert'])->save();
        $this->fakeTasks([['id' => 't-1', 'content' => 'Remote geändert', 'priority' => 1]]);
        $counters = $this->imports->syncLink($this->link, $this->connection);

        $this->assertSame(1, $counters['conflicts']);
        $this->assertSame('Lokal geändert', Task::query()->firstOrFail()->title, 'Kein Last-write-wins');

        $item = IntegrationInboxItem::query()->where('case_type', 'conflict')->firstOrFail();
        $this->assertContains('title', (array) $item->diff_fields);

        // Konfliktbasis bleibt erhalten → derselbe Konflikt erzeugt kein zweites Item.
        $this->fakeTasks([['id' => 't-1', 'content' => 'Remote geändert', 'priority' => 1]]);
        $this->imports->syncLink($this->link, $this->connection);
        $this->assertSame(1, IntegrationInboxItem::query()->where('case_type', 'conflict')->count());
    }

    public function test_reopen_only_when_done_came_from_todoist(): void {
        // done stammt aus Todoist → Reopen erlaubt.
        $this->fakeTasks([['id' => 't-1', 'content' => 'A', 'checked' => true, 'priority' => 1]]);
        $this->imports->syncLink($this->link, $this->connection);
        $this->assertSame(TaskStatus::Done, Task::query()->firstOrFail()->status);

        $this->fakeTasks([['id' => 't-1', 'content' => 'A', 'checked' => false, 'priority' => 1]]);
        $this->imports->syncLink($this->link, $this->connection);
        $this->assertSame(TaskStatus::Open, Task::query()->firstOrFail()->status);

        // done stammt aus WorkDiary → Todoist-Reopen setzt NICHT zurück.
        $task = Task::query()->firstOrFail();
        $task->forceFill(['status' => TaskStatus::Done->value])->save();
        $reference = ExternalReference::query()->where('external_id', 't-1')->firstOrFail();
        $payload = (array) $reference->payload;
        $payload['base']['status'] = TaskStatus::Done->value; // lokal geführt, Basis fortgeschrieben (P4-Export)
        $payload['done_origin'] = null;
        $reference->forceFill(['payload' => $payload])->save();

        $this->fakeTasks([['id' => 't-1', 'content' => 'A', 'checked' => false, 'priority' => 1]]);
        $this->imports->syncLink($this->link, $this->connection);
        $this->assertSame(TaskStatus::Done, Task::query()->firstOrFail()->status, 'Reopen nur bei done_origin=todoist');
    }

    public function test_orphan_subtask_goes_to_inbox(): void {
        // Kind verweist auf Eltern-Task, der nicht in dieser Zuordnung liegt.
        $this->fakeTasks([['id' => 't-child', 'content' => 'Kind', 'parent_id' => 't-foreign', 'priority' => 1]]);
        $counters = $this->imports->syncLink($this->link, $this->connection);

        $this->assertSame(1, $counters['inbox']);
        $this->assertSame(0, Task::query()->count());
        $this->assertDatabaseHas('integration_inbox_items', [
            'plugin_id' => TodoistPlugin::ID,
            'case_type' => 'unmatched',
            'external_id' => 't-child',
        ]);
    }

    public function test_subtask_within_same_link_resolves_parent(): void {
        $this->fakeTasks([
            ['id' => 't-child', 'content' => 'Kind', 'parent_id' => 't-parent', 'priority' => 1],
            ['id' => 't-parent', 'content' => 'Eltern', 'priority' => 1],
        ]);
        $counters = $this->imports->syncLink($this->link, $this->connection);

        $this->assertSame(2, $counters['created']);
        $parent = Task::query()->where('title', 'Eltern')->firstOrFail();
        $child = Task::query()->where('title', 'Kind')->firstOrFail();
        $this->assertSame($parent->id, (int) $child->parent_task_id);
    }

    public function test_assignee_only_after_explicit_mapping(): void {
        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TodoistPlugin::ID,
            'external_type' => TodoistPlugin::EXT_TYPE_COLLABORATOR,
            'external_id' => 'c-1',
            'referenceable_type' => $member->getMorphClass(),
            'referenceable_id' => $member->getKey(),
            'synced_at' => now(),
        ]);

        $this->fakeTasks([
            ['id' => 't-1', 'content' => 'Gemappt', 'responsible_uid' => 'c-1', 'priority' => 1],
            ['id' => 't-2', 'content' => 'Ungemappt', 'responsible_uid' => 'c-x', 'priority' => 1],
        ]);
        $this->imports->syncLink($this->link, $this->connection);

        $this->assertSame($member->id, (int) Task::query()->where('title', 'Gemappt')->firstOrFail()->assigned_to);
        $this->assertNull(Task::query()->where('title', 'Ungemappt')->firstOrFail()->assigned_to);
    }

    public function test_failed_task_does_not_abort_run(): void {
        // Zweiter Datensatz ohne id/content provoziert keinen Abbruch des Laufs.
        $this->fakeTasks([
            ['id' => 't-1', 'content' => 'OK', 'priority' => 1],
            ['content' => null, 'priority' => 'kaputt'],
        ]);
        $counters = $this->imports->syncLink($this->link, $this->connection);

        $this->assertSame(1, $counters['created']);
        $this->assertSame(1, Task::query()->count());
    }
}
