<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistOutboxTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\Integration\IntegrationOutboxStatus;
use App\Enums\Task\{TaskPriority, TaskStatus};
use App\Models\{ExternalReference, IntegrationInboxItem, IntegrationOutboxEntry, Task, TodoistConnection, TodoistProjectLink, User};
use App\Plugins\Todoist\Services\{TodoistImportService, TodoistOutboxDispatcher};
use App\Plugins\Todoist\TodoistPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Feature 055, MVP-114: bidirektionaler Abgleich — lokale Änderungen an
 * synchronisierten Feldern fließen als Outbox-Eintrag asynchron nach Todoist
 * (Reverse-Feldadapter, Done-Grenze über close/reopen), die Konfliktbasis
 * (`base`) wird nach Erfolg fortgeschrieben, Importe erzeugen kein
 * Export-Echo und Löschungen werden in KEINE Richtung weitergegeben.
 */
final class TodoistOutboxTest extends TestCase {
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
    private function importRemote(array $tasks): void {
        FakePluginHttp::fake([
            'https://api.todoist.com/api/v1/tasks*' => FakePluginHttp::response(['results' => $tasks, 'next_cursor' => null]),
        ]);
        $this->imports->syncLink($this->link, $this->connection);
    }

    /** @return array<string, mixed> */
    private function requestJson(RequestInterface $request): array {
        return (array) json_decode((string) $request->getBody(), true);
    }

    private function localTask(string $title): Task {
        return Task::query()->create([
            'organization_id' => $this->organization->id,
            'is_global' => true,
            'title' => $title,
            'status' => TaskStatus::Open->value,
            'priority' => TaskPriority::High->value,
            'due_date' => '2026-09-01',
        ]);
    }

    public function test_local_task_creation_exports_to_todoist(): void {
        $fake = FakePluginHttp::fake([
            'https://api.todoist.com/api/v1/tasks*' => FakePluginHttp::response(['id' => 't-new', 'content' => 'Neu lokal']),
        ]);

        $task = $this->localTask('Neu lokal');

        $entry = IntegrationOutboxEntry::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(TodoistOutboxDispatcher::OP_TASK_CREATE, $entry->operation);
        $this->assertSame(IntegrationOutboxStatus::Confirmed, $entry->status);

        $fake->assertSent(function (RequestInterface $r): bool {
            $body = $this->requestJson($r);

            return str_ends_with((string) $r->getUri(), '/tasks')
                && ($body['content'] ?? null) === 'Neu lokal'
                && ($body['project_id'] ?? null) === 'tp-1'
                && ($body['priority'] ?? null) === 3; // high = API-Wert 3
        });

        // Neue Referenz + initialer Konfliktbasis-Snapshot (kein Phantom-Update).
        $reference = ExternalReference::query()
            ->where('external_id', 't-new')
            ->where('external_type', TodoistPlugin::EXT_TYPE_TASK)
            ->firstOrFail();
        $this->assertSame($task->id, (int) $reference->referenceable_id);
        $base = (array) ((array) $reference->payload)['base'];
        $this->assertSame('Neu lokal', $base['title']);
        $this->assertSame(TaskPriority::High->value, $base['priority']);
    }

    public function test_imported_task_does_not_echo_create(): void {
        $this->importRemote([['id' => 't-1', 'content' => 'Importiert', 'priority' => 1]]);

        // Der Import legt die Aufgabe in suppressed() an → kein task.create-Echo.
        $this->assertSame(0, IntegrationOutboxEntry::withoutGlobalScopes()->count());
        $this->assertSame(1, Task::query()->count());
    }

    public function test_no_create_export_in_import_only_mode(): void {
        $this->link->forceFill(['sync_mode' => TodoistProjectLink::MODE_TODOIST_TO_WORKDIARY])->save();

        $fake = FakePluginHttp::fake();
        $this->localTask('Nur lokal');

        $this->assertSame(0, IntegrationOutboxEntry::withoutGlobalScopes()->count());
        $fake->assertNothingSent();
    }

    public function test_status_change_moves_task_to_mapped_section(): void {
        \App\Models\TodoistSectionLink::query()->create([
            'organization_id' => $this->organization->id,
            'todoist_project_link_id' => $this->link->id,
            'todoist_section_id' => 'sec-progress',
            'name' => 'In Arbeit',
            'task_status' => TaskStatus::InProgress->value,
        ]);
        $this->importRemote([['id' => 't-1', 'content' => 'A', 'priority' => 1]]);

        $fake = FakePluginHttp::fake();
        Task::query()->firstOrFail()->forceFill(['status' => TaskStatus::InProgress->value])->save();

        $fake->assertSent(fn (RequestInterface $r): bool => str_ends_with((string) $r->getUri(), '/tasks/t-1/move')
            && ($this->requestJson($r)['section_id'] ?? null) === 'sec-progress');

        // Konfliktbasis fortgeschrieben → kein Ping-Pong beim nächsten Import.
        $reference = ExternalReference::query()->where('external_id', 't-1')->firstOrFail();
        $this->assertSame(TaskStatus::InProgress->value, ((array) $reference->payload)['base']['status']);
    }

    public function test_unmapped_status_does_not_move(): void {
        $this->importRemote([['id' => 't-1', 'content' => 'A', 'priority' => 1]]);

        $fake = FakePluginHttp::fake();
        Task::query()->firstOrFail()->forceFill(['status' => TaskStatus::InProgress->value])->save();

        $fake->assertNotSent(fn (RequestInterface $r): bool => str_contains((string) $r->getUri(), '/move'));
    }

    public function test_local_change_exports_and_advances_base(): void {
        $this->importRemote([['id' => 't-1', 'content' => 'Alt', 'priority' => 1]]);

        $fake = FakePluginHttp::fake([
            'https://api.todoist.com/api/v1/tasks/t-1' => FakePluginHttp::response(['id' => 't-1']),
        ]);
        Task::query()->firstOrFail()->forceFill([
            'title' => 'Neu lokal',
            'priority' => TaskPriority::Urgent->value,
            'due_date' => '2026-08-15',
        ])->save();

        $entry = IntegrationOutboxEntry::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(IntegrationOutboxStatus::Confirmed, $entry->status);
        $this->assertSame('task.update', $entry->operation);

        $fake->assertSent(function (RequestInterface $r): bool {
            $body = $this->requestJson($r);

            return str_ends_with((string) $r->getUri(), '/tasks/t-1')
                && ($body['content'] ?? null) === 'Neu lokal'
                && ($body['priority'] ?? null) === 4 // urgent = API-Wert 4
                && ($body['due_date'] ?? null) === '2026-08-15';
        });

        // Konfliktbasis fortgeschrieben → nächster Import sieht keinen Phantom-Konflikt.
        $reference = ExternalReference::query()->where('external_id', 't-1')->firstOrFail();
        $base = (array) ((array) $reference->payload)['base'];
        $this->assertSame('Neu lokal', $base['title']);
        $this->assertSame(TaskPriority::Urgent->value, $base['priority']);
        $this->assertSame('2026-08-15', $base['due_date']);
    }

    public function test_done_boundary_exports_close_and_reopen(): void {
        $this->importRemote([['id' => 't-1', 'content' => 'A', 'priority' => 1]]);

        $fake = FakePluginHttp::fake();
        Task::query()->firstOrFail()->forceFill(['status' => TaskStatus::Done->value])->save();

        $fake->assertSent(fn (RequestInterface $r): bool => str_ends_with((string) $r->getUri(), '/tasks/t-1/close'));
        $reference = ExternalReference::query()->where('external_id', 't-1')->firstOrFail();
        $this->assertSame(TaskStatus::Done->value, ((array) $reference->payload)['base']['status']);
        $this->assertNull(((array) $reference->payload)['done_origin'], 'done ist lokal geführt');

        $fake = FakePluginHttp::fake();
        Task::query()->firstOrFail()->forceFill(['status' => TaskStatus::Open->value])->save();

        $fake->assertSent(fn (RequestInterface $r): bool => str_ends_with((string) $r->getUri(), '/tasks/t-1/reopen'));
        $reference->refresh();
        $this->assertSame(TaskStatus::Open->value, ((array) $reference->payload)['base']['status']);
    }

    public function test_import_only_mode_does_not_enqueue(): void {
        $this->importRemote([['id' => 't-1', 'content' => 'A', 'priority' => 1]]);
        $this->link->forceFill(['sync_mode' => TodoistProjectLink::MODE_TODOIST_TO_WORKDIARY])->save();

        $fake = FakePluginHttp::fake();
        Task::query()->firstOrFail()->forceFill(['title' => 'Nur lokal'])->save();

        $this->assertSame(0, IntegrationOutboxEntry::withoutGlobalScopes()->count());
        $fake->assertNothingSent();
    }

    public function test_import_does_not_echo_export(): void {
        $this->importRemote([['id' => 't-1', 'content' => 'Alt', 'priority' => 1]]);

        // Remote-Änderung wird übernommen — darf aber keinen Export auslösen.
        $this->importRemote([['id' => 't-1', 'content' => 'Remote neu', 'priority' => 3]]);

        $this->assertSame('Remote neu', Task::query()->firstOrFail()->title);
        $this->assertSame(0, IntegrationOutboxEntry::withoutGlobalScopes()->count());
    }

    public function test_local_delete_is_not_propagated(): void {
        $this->importRemote([['id' => 't-1', 'content' => 'A', 'priority' => 1]]);

        $fake = FakePluginHttp::fake();
        Task::query()->firstOrFail()->delete();

        $this->assertSame(0, IntegrationOutboxEntry::withoutGlobalScopes()->count());
        $fake->assertNothingSent();

        // Nächster Import: sichtbarer Inbox-Fall statt stiller Neuanlage/Löschung.
        $this->importRemote([['id' => 't-1', 'content' => 'A', 'priority' => 1]]);
        $this->assertDatabaseHas('integration_inbox_items', [
            'plugin_id' => TodoistPlugin::ID,
            'dedupe_key' => 'task:t-1:local_deleted',
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
        ]);
        $this->assertSame(0, Task::query()->count(), 'Keine stille Neuanlage');
    }

    public function test_remote_deletion_is_flagged_not_deleted(): void {
        $this->importRemote([['id' => 't-1', 'content' => 'A', 'priority' => 1]]);

        // Remote verschwunden → Marker + Inbox-Fall, lokale Aufgabe bleibt.
        $this->importRemote([]);

        $this->assertSame(1, Task::query()->count(), 'Nie Auto-Löschung');
        $reference = ExternalReference::query()->where('external_id', 't-1')->firstOrFail();
        $this->assertArrayHasKey('remote_deleted_at', (array) $reference->payload);
        $this->assertDatabaseHas('integration_inbox_items', [
            'plugin_id' => TodoistPlugin::ID,
            'dedupe_key' => 'task:t-1:remote_deleted',
        ]);

        // Wiederholter Lauf bleibt idempotent.
        $this->importRemote([]);
        $this->assertSame(1, IntegrationInboxItem::query()->where('dedupe_key', 'task:t-1:remote_deleted')->count());

        // Taucht die Aufgabe wieder auf (z. B. reaktiviert), verschwindet der Marker.
        $this->importRemote([['id' => 't-1', 'content' => 'A2', 'priority' => 1]]);
        $reference->refresh();
        $this->assertArrayNotHasKey('remote_deleted_at', (array) $reference->payload);
    }

    public function test_conflicted_field_is_not_exported(): void {
        $this->importRemote([['id' => 't-1', 'content' => 'Basis', 'priority' => 1]]);

        // Asynchrones Fenster: lokale Änderung enqueued, aber noch nicht zugestellt.
        \Illuminate\Support\Facades\Queue::fake();
        Task::query()->firstOrFail()->forceFill(['title' => 'Lokal geändert'])->save();
        $entry = IntegrationOutboxEntry::withoutGlobalScopes()->firstOrFail();

        // Import erkennt die beidseitige Änderung → offener Konfliktfall.
        $this->importRemote([['id' => 't-1', 'content' => 'Remote geändert', 'priority' => 1]]);
        $this->assertSame(1, IntegrationInboxItem::query()->where('case_type', 'conflict')->count());

        // Verspätete Zustellung darf das konfliktierte Feld NICHT exportieren
        // (kein Last-write-wins durch die Hintertür) — Auflösung nur via Inbox.
        $fake = FakePluginHttp::fake();
        (new \App\Jobs\Integration\IntegrationOutboxDeliveryJob($entry->id))->handle(
            app(\App\Services\Integration\IntegrationOutboxService::class),
            app(\App\Services\Integration\IntegrationOutboxDispatcherResolver::class),
        );

        $fake->assertNothingSent();
        $this->assertSame(IntegrationOutboxStatus::Confirmed, $entry->refresh()->status);
        $reference = ExternalReference::query()->where('external_id', 't-1')->firstOrFail();
        $this->assertSame('Basis', ((array) $reference->payload)['base']['title'], 'Konfliktbasis bleibt erhalten');
    }

    public function test_unmapped_assignee_is_not_exported(): void {
        $this->importRemote([['id' => 't-1', 'content' => 'A', 'priority' => 1]]);
        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $fake = FakePluginHttp::fake();
        Task::query()->firstOrFail()->forceFill(['assigned_to' => $member->id])->save();

        // Kein Kollaborator-Mapping → Feld wird nie geraten, kein API-Call.
        $entry = IntegrationOutboxEntry::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(IntegrationOutboxStatus::Confirmed, $entry->status);
        $fake->assertNothingSent();

        $reference = ExternalReference::query()->where('external_id', 't-1')->firstOrFail();
        $this->assertNull(((array) $reference->payload)['base']['assigned_to'], 'Basis unverändert');
    }

    public function test_mapped_assignee_is_exported(): void {
        $this->importRemote([['id' => 't-1', 'content' => 'A', 'priority' => 1]]);
        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TodoistPlugin::ID,
            'external_type' => TodoistPlugin::EXT_TYPE_COLLABORATOR,
            'external_id' => 'c-9',
            'referenceable_type' => $member->getMorphClass(),
            'referenceable_id' => $member->getKey(),
            'synced_at' => now(),
        ]);

        $fake = FakePluginHttp::fake();
        Task::query()->firstOrFail()->forceFill(['assigned_to' => $member->id])->save();

        $fake->assertSent(function (RequestInterface $r): bool {
            return str_ends_with((string) $r->getUri(), '/tasks/t-1')
                && ($this->requestJson($r)['responsible_uid'] ?? null) === 'c-9';
        });
        $reference = ExternalReference::query()->where('external_id', 't-1')->where('external_type', TodoistPlugin::EXT_TYPE_TASK)->firstOrFail();
        $this->assertSame($member->id, (int) ((array) $reference->payload)['base']['assigned_to']);
    }
}
