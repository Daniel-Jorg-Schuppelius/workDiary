<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistAdminUxTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{ExternalReference, IntegrationInboxItem, IntegrationOutboxEntry, Task, TodoistConnection, TodoistProjectLink, User};
use App\Plugins\Todoist\Services\TodoistImportService;
use App\Plugins\Todoist\TodoistPlugin;
use App\Services\Integration\InboxActionService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Feature 055, MVP-116: Admin-UX + Audit — manueller Vollabgleich als
 * auditierter Admin-Vorgang, „lokal behalten" setzt den lokalen Stand auch
 * extern durch (kein Konflikt-Pingpong), Konfliktentscheidungen landen im
 * Audit-Log, Task-Deep-Link nur bei gültiger Fremd-ID.
 */
final class TodoistAdminUxTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private TodoistConnection $connection;
    private TodoistProjectLink $link;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        config()->set('plugins.todoist.client_id', 'cid');
        config()->set('plugins.todoist.client_secret', 'sec');
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
    }

    /** @param list<array<string, mixed>> $tasks */
    private function importRemote(array $tasks): void {
        FakePluginHttp::fake([
            'https://api.todoist.com/api/v1/tasks*' => FakePluginHttp::response(['results' => $tasks, 'next_cursor' => null]),
        ]);
        app(TodoistImportService::class)->syncLink($this->link, $this->connection);
    }

    public function test_manual_full_sync_imports_and_is_audited(): void {
        FakePluginHttp::fake([
            'https://api.todoist.com/api/v1/sync' => FakePluginHttp::response(['sync_token' => 'tok-1']),
            'https://api.todoist.com/api/v1/tasks*' => FakePluginHttp::response([
                'results' => [['id' => 't-1', 'content' => 'Manuell', 'priority' => 1]],
                'next_cursor' => null,
            ]),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.todoist.sync'));

        $response->assertRedirect()->assertSessionHas('success');
        $this->assertSame('Manuell', Task::query()->firstOrFail()->title);
        $this->assertDatabaseHas('audit_logs', ['event' => 'todoist.sync_manual']);
    }

    public function test_keep_local_pushes_local_state_and_is_audited(): void {
        $this->importRemote([['id' => 't-1', 'content' => 'Basis', 'priority' => 1]]);

        // Asynchrones Fenster: lokale Änderung ohne (bereits erfolgte)
        // Zustellung — der Import erkennt die beidseitige Änderung → Konflikt.
        \App\Plugins\Todoist\Observers\TodoistTaskObserver::suppressed(
            fn () => Task::query()->firstOrFail()->forceFill(['title' => 'Lokal gewinnt'])->save(),
        );
        $this->importRemote([['id' => 't-1', 'content' => 'Remote anders', 'priority' => 1]]);
        $item = IntegrationInboxItem::query()->where('case_type', 'conflict')->firstOrFail();

        // Entscheidung „lokal behalten": Wert bleibt UND wird exportiert.
        $this->actingAs($this->admin);
        $fake = FakePluginHttp::fake();
        app(InboxActionService::class)->keepLocal($item);

        $this->assertSame(IntegrationInboxItem::STATUS_RESOLVED_LOCAL, $item->fresh()?->status);
        $this->assertSame('Lokal gewinnt', Task::query()->firstOrFail()->title);

        $fake->assertSent(function (RequestInterface $r): bool {
            $body = (array) json_decode((string) $r->getBody(), true);

            return str_ends_with((string) $r->getUri(), '/tasks/t-1')
                && ($body['content'] ?? null) === 'Lokal gewinnt';
        });

        // Basis fortgeschrieben → kein Konflikt-Pingpong beim nächsten Import.
        $reference = ExternalReference::query()->where('external_id', 't-1')->firstOrFail();
        $this->assertSame('Lokal gewinnt', ((array) $reference->payload)['base']['title']);

        $this->assertSame(1, IntegrationOutboxEntry::withoutGlobalScopes()->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'integration.inbox_resolved']);
    }

    public function test_task_url_only_for_valid_external_id(): void {
        $this->importRemote([['id' => 't-1', 'content' => 'A', 'priority' => 1]]);
        $task = Task::query()->firstOrFail();

        $this->assertSame('https://app.todoist.com/app/task/t-1', TodoistPlugin::taskUrl($task));

        // Ungültige Zeichen in der Fremd-ID → kein Link (keine URL-Injektion).
        ExternalReference::query()->where('external_id', 't-1')->update(['external_id' => 't-1/../evil']);
        $this->assertNull(TodoistPlugin::taskUrl($task->fresh() ?? $task));

        // Nicht verknüpfte Aufgabe → kein Link. Observer unterdrücken, sonst
        // würde die Anlage sofort als task.create exportiert (MVP-114).
        $plain = \App\Plugins\Todoist\Observers\TodoistTaskObserver::suppressed(
            fn (): Task => Task::query()->create([
                'organization_id' => $this->organization->id,
                'is_global' => true,
                'title' => 'Ohne Referenz',
            ]),
        );
        $this->assertNull(TodoistPlugin::taskUrl($plain));
    }
}
