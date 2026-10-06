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

use App\Enums\Integration\IntegrationInboxStatus;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem, IntegrationOutboxEntry};
use App\Models\Platform\User;
use App\Models\Project\Task;
use App\Plugins\Todoist\Enums\{TodoistConnectionStatus, TodoistProjectLinkStatus};
use App\Plugins\Todoist\Models\{TodoistConnection, TodoistProjectLink};
use App\Plugins\Todoist\Services\TodoistImportService;
use App\Plugins\Todoist\TodoistPlugin;
use App\Services\Integration\InboxActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
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
    use WithPluginSecrets;

    private User $admin;
    private TodoistConnection $connection;
    private TodoistProjectLink $link;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        config()->set('plugins.todoist.enabled', true);
        config()->set('plugins.todoist.client_id', 'cid');
        config()->set('plugins.todoist.client_secret', 'sec');
        $this->connection = TodoistConnection::query()->create([
            'organization_id' => $this->organization->id,
            'access_token' => 'secret-token',
            'status' => TodoistConnectionStatus::Active,
        ]);
        $this->link = TodoistProjectLink::query()->create([
            'organization_id' => $this->organization->id,
            'todoist_project_id' => 'tp-1',
            'todoist_project_name' => 'Sync-Projekt',
            'target_kind' => TodoistProjectLink::KIND_GLOBAL_KANBAN,
            'sync_mode' => TodoistProjectLink::MODE_BIDIRECTIONAL,
            'status' => TodoistProjectLinkStatus::Active,
        ]);
    }

    /** @param list<array<string, mixed>> $tasks */
    private function importRemote(array $tasks): void {
        FakePluginHttp::fake([
            'https://api.todoist.com/api/v1/tasks*' => FakePluginHttp::response(['results' => $tasks, 'next_cursor' => null]),
        ]);
        app(TodoistImportService::class)->syncLink($this->link, $this->connection);
    }

    /** Stand und Aktion je Zuordnung verglichen den Status früher mit Zeichenketten. */
    public function test_index_shows_status_badges_and_matching_link_action(): void {
        FakePluginHttp::fake([
            'https://api.todoist.com/api/v1/projects*' => FakePluginHttp::response(['results' => [], 'next_cursor' => null]),
        ]);
        $draft = TodoistProjectLink::query()->create([
            'organization_id' => $this->organization->id,
            'todoist_project_id' => 'tp-2',
            'todoist_project_name' => 'Entwurfs-Projekt',
            'target_kind' => TodoistProjectLink::KIND_GLOBAL_KANBAN,
            'sync_mode' => TodoistProjectLink::MODE_BIDIRECTIONAL,
            'status' => TodoistProjectLinkStatus::Draft,
        ]);

        $html = (string) $this->actingAs($this->admin)->get(route('admin.todoist.index'))->assertOk()->getContent();

        // Verbindung und aktive Zuordnung grün, der Entwurf ohne Ton.
        $this->assertSame(2, preg_match_all('/class="badge badge-sm badge-success">\s*Aktiv\s*</', $html));
        $this->assertMatchesRegularExpression('/class="badge badge-sm">\s*Entwurf\s*</', $html);
        $this->assertSame('paused', $this->offeredLinkStatus($html, $this->link));
        $this->assertSame('active', $this->offeredLinkStatus($html, $draft));

        $this->link->forceFill(['status' => TodoistProjectLinkStatus::Paused])->save();
        $html = (string) $this->actingAs($this->admin)->get(route('admin.todoist.index'))->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('/class="badge badge-sm badge-warning">\s*Pausiert\s*</', $html));
        $this->assertSame('active', $this->offeredLinkStatus($html, $this->link));

        // Eine pausierte Verbindung zeigt ihren Stand, aber keine Zuordnungen mehr.
        $this->connection->forceFill(['status' => TodoistConnectionStatus::Paused])->save();
        $html = (string) $this->actingAs($this->admin)->get(route('admin.todoist.index'))->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('/class="badge badge-sm badge-warning">\s*Pausiert\s*</', $html));
        $this->assertNull($this->offeredLinkStatus($html, $this->link));
    }

    public function test_project_links_page_by_name(): void {
        FakePluginHttp::fake([
            'https://api.todoist.com/api/v1/projects*' => FakePluginHttp::response(['results' => [], 'next_cursor' => null]),
        ]);
        foreach (range(1, 26) as $i) {
            TodoistProjectLink::query()->create([
                'organization_id' => $this->organization->id,
                'todoist_project_id' => 'tp-x' . $i,
                'todoist_project_name' => sprintf('Zuordnung %02d', $i),
                'target_kind' => TodoistProjectLink::KIND_GLOBAL_KANBAN,
                'sync_mode' => TodoistProjectLink::MODE_BIDIRECTIONAL,
                'status' => TodoistProjectLinkStatus::Active,
            ]);
        }

        $first = $this->actingAs($this->admin)->get(route('admin.todoist.index'))->assertOk();
        $this->assertSame(27, $first->viewData('links')->total());
        $this->assertCount(25, $first->viewData('links')->items());
        $first->assertSee('Sync-Projekt')->assertDontSee('Zuordnung 26');

        $second = $this->actingAs($this->admin)->get(route('admin.todoist.index', ['page' => 2]))->assertOk();
        $this->assertSame(['Zuordnung 25', 'Zuordnung 26'], $second->viewData('links')->pluck('todoist_project_name')->all());
        // Das Formular für neue Zuordnungen steht auf jeder Seite unter der Tabelle.
        $second->assertSee(route('admin.todoist.links.store'));
    }

    /** Wert, den der Statusknopf einer Zuordnung absendet. */
    private function offeredLinkStatus(string $html, TodoistProjectLink $link): ?string {
        $action = preg_quote(route('admin.todoist.links.status', $link), '#');

        return preg_match('#action="' . $action . '"[^>]*>(?:(?!</form>).)*?name="status" value="(\w+)"#s', $html, $m) === 1 ? $m[1] : null;
    }

    public function test_link_status_is_set_and_audited_with_its_value(): void {
        $this->actingAs($this->admin)
            ->post(route('admin.todoist.links.status', $this->link), ['status' => 'paused'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(TodoistProjectLinkStatus::Paused, $this->link->refresh()->status);
        $audit = \App\Models\Audit\AuditLog::query()->where('event', 'todoist.link_status')->latest('id')->firstOrFail();
        $this->assertSame('paused', $audit->changes['status'] ?? null);

        // Der Entwurf ist kein wählbares Ziel.
        $this->actingAs($this->admin)
            ->post(route('admin.todoist.links.status', $this->link), ['status' => 'draft'])
            ->assertSessionHasErrors('status');
        $this->assertSame(TodoistProjectLinkStatus::Paused, $this->link->refresh()->status);
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

        $this->assertSame(IntegrationInboxStatus::ResolvedLocal, $item->fresh()?->status);
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
