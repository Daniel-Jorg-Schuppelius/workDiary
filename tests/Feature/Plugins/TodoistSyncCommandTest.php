<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistSyncCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\Task\TaskStatus;
use App\Models\{ExternalReference, IntegrationInboxItem, Task, TodoistConnection, TodoistProjectLink};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Feature 055, MVP-115: Polling als verlässliche Quelle — `todoist:sync` mit
 * cursor-basiertem Delta (`sync_cursor`), `--full`-Vollabgleich, gezieltem
 * Abgleich nur geänderter Projekte, sauberem Wiederanlauf am Cursor nach
 * Rate-Limit-Abbruch (429) und Delta-Semantik für checked/is_deleted.
 */
final class TodoistSyncCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private TodoistConnection $connection;
    private TodoistProjectLink $link;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
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

    private function cursor(): ?string {
        return $this->connection->fresh()?->getAttribute('sync_cursor');
    }

    public function test_first_run_does_full_sync_and_stores_cursor(): void {
        FakePluginHttp::fake([
            'https://api.todoist.com/api/v1/sync' => FakePluginHttp::response(['sync_token' => 'tok-1']),
            'https://api.todoist.com/api/v1/tasks*' => FakePluginHttp::response([
                'results' => [['id' => 't-1', 'content' => 'Voll', 'priority' => 1]],
                'next_cursor' => null,
            ]),
        ]);

        $this->artisan('todoist:sync')->assertExitCode(0);

        $this->assertSame('Voll', Task::query()->firstOrFail()->title);
        $this->assertSame('tok-1', $this->cursor());
    }

    public function test_delta_run_syncs_only_changed_linked_projects(): void {
        $this->connection->forceFill(['sync_cursor' => 'tok-1'])->save();
        $fake = FakePluginHttp::fake([
            'https://api.todoist.com/api/v1/sync' => FakePluginHttp::response([
                'sync_token' => 'tok-2',
                'full_sync' => false,
                'items' => [
                    ['id' => 't-2', 'content' => 'Delta', 'priority' => 2, 'project_id' => 'tp-1'],
                    ['id' => 't-9', 'content' => 'Fremd', 'priority' => 1, 'project_id' => 'tp-ohne-link'],
                ],
            ]),
        ]);

        $this->artisan('todoist:sync')->assertExitCode(0);

        $this->assertSame('Delta', Task::query()->firstOrFail()->title, 'Nur verknüpftes Projekt verarbeitet');
        $this->assertSame('tok-2', $this->cursor());
        // Delta braucht keine Projekt-Volllisten.
        $fake->assertNotSent(fn (RequestInterface $r): bool => str_contains((string) $r->getUri(), '/tasks'));
    }

    public function test_empty_delta_only_advances_cursor(): void {
        $this->connection->forceFill(['sync_cursor' => 'tok-1'])->save();
        FakePluginHttp::fake([
            'https://api.todoist.com/api/v1/sync' => FakePluginHttp::response(['sync_token' => 'tok-2', 'full_sync' => false, 'items' => []]),
        ]);

        $this->artisan('todoist:sync')->assertExitCode(0);

        $this->assertSame(0, Task::query()->count());
        $this->assertSame('tok-2', $this->cursor());
    }

    public function test_rate_limit_abort_keeps_cursor_for_restart(): void {
        $this->connection->forceFill(['sync_cursor' => 'tok-1'])->save();
        FakePluginHttp::fake([
            'https://api.todoist.com/api/v1/sync' => FakePluginHttp::response(['error' => 'rate limited'], 429, ['Retry-After' => '0']),
        ]);

        $this->artisan('todoist:sync')->assertExitCode(0);

        $this->assertSame('tok-1', $this->cursor(), 'Cursor unverändert — Wiederanlauf am selben Stand');
    }

    public function test_delta_checked_item_completes_local_task(): void {
        $this->importInitialTask();

        $this->connection->forceFill(['sync_cursor' => 'tok-1'])->save();
        FakePluginHttp::fake([
            'https://api.todoist.com/api/v1/sync' => FakePluginHttp::response([
                'sync_token' => 'tok-2',
                'full_sync' => false,
                'items' => [['id' => 't-1', 'content' => 'Aufgabe', 'priority' => 1, 'project_id' => 'tp-1', 'checked' => true]],
            ]),
        ]);

        $this->artisan('todoist:sync')->assertExitCode(0);

        $this->assertSame(TaskStatus::Done, Task::query()->firstOrFail()->status);
    }

    public function test_delta_deleted_item_is_flagged_not_deleted(): void {
        $this->importInitialTask();

        $this->connection->forceFill(['sync_cursor' => 'tok-1'])->save();
        FakePluginHttp::fake([
            'https://api.todoist.com/api/v1/sync' => FakePluginHttp::response([
                'sync_token' => 'tok-2',
                'full_sync' => false,
                'items' => [['id' => 't-1', 'project_id' => 'tp-1', 'is_deleted' => true]],
            ]),
        ]);

        $this->artisan('todoist:sync')->assertExitCode(0);

        $this->assertSame(1, Task::query()->count(), 'Nie Auto-Löschung');
        $reference = ExternalReference::query()->where('external_id', 't-1')->firstOrFail();
        $this->assertArrayHasKey('remote_deleted_at', (array) $reference->payload);
        $this->assertSame(1, IntegrationInboxItem::query()->where('dedupe_key', 'task:t-1:remote_deleted')->count());
    }

    /** Erstimport von t-1 über den Voll-Sync (setzt Cursor zurück auf tok-0). */
    private function importInitialTask(): void {
        FakePluginHttp::fake([
            'https://api.todoist.com/api/v1/sync' => FakePluginHttp::response(['sync_token' => 'tok-0']),
            'https://api.todoist.com/api/v1/tasks*' => FakePluginHttp::response([
                'results' => [['id' => 't-1', 'content' => 'Aufgabe', 'priority' => 1]],
                'next_cursor' => null,
            ]),
        ]);
        $this->artisan('todoist:sync', ['--full' => true])->assertExitCode(0);
        $this->assertSame(1, Task::query()->count());
    }
}
