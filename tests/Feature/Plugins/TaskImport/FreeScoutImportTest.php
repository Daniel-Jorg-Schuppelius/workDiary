<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FreeScoutImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\TaskImport;

use App\Enums\Integration\TaskImportLinkStatus;
use App\Enums\Task\TaskStatus;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\User;
use App\Models\Project\{Project, Task};
use App\Plugins\FreeScout\FreeScoutPlugin;
use App\Plugins\FreeScout\Models\{FreeScoutConnection, FreeScoutProjectLink};
use App\Plugins\FreeScout\Services\FreeScoutImportService;
use App\Plugins\PluginManager;
use App\Plugins\Support\OAuthConnectionStatus;
use App\Plugins\Support\TaskSync\SelfHostedInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * FreeScout (MVP-1336): Postfächer als Quellen, Konversationen im HAL-Format
 * seitenweise bis `page.totalPages`, Erstlauf aktive und wartende, danach
 * `updatedSince`, Beschreibung aus der ersten Nachricht (keine Notiz),
 * Spam schließt.
 */
class FreeScoutImportTest extends TestCase {
    use RefreshDatabase;
    use TaskImportFixtures;
    use WithOrganization;
    use WithPluginSecrets;

    private const BASE = 'https://support.example.test';

    private const MAILBOX = '2';

    private User $admin;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'Europe/Berlin']);
        $this->pluginSecret(FreeScoutPlugin::ID, ['base_url' => self::BASE, 'api_key' => 'fs-key']);
        $this->admin = $this->orgAdmin();
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function connect(): FreeScoutConnection {
        return FreeScoutConnection::query()->forceCreate([
            'organization_id' => $this->organization->id,
            'source_id' => SelfHostedInstance::key(self::BASE),
            'source_name' => 'support.example.test',
            'source_url' => self::BASE,
            'status' => OAuthConnectionStatus::Active,
        ]);
    }

    private function link(bool $confirmed = true): FreeScoutProjectLink {
        return FreeScoutProjectLink::query()->forceCreate([
            'organization_id' => $this->organization->id,
            'source_project_id' => self::MAILBOX,
            'source_project_key' => 'hilfe@example.test',
            'source_project_name' => 'Hilfe',
            'project_id' => $this->project->id,
            'status' => $confirmed ? TaskImportLinkStatus::Active : TaskImportLinkStatus::Pending,
            'import_confirmed_at' => $confirmed ? now() : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function conversation(int $id, string $subject, string $status = 'active', array $overrides = []): array {
        return $overrides + [
            'id' => $id,
            'number' => $id + 100,
            'subject' => $subject,
            'status' => $status,
            'state' => 'published',
            'mailboxId' => (int) self::MAILBOX,
            'updatedAt' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            '_embedded' => ['threads' => [
                ['id' => $id * 10, 'type' => 'message', 'state' => 'draft', 'body' => '<p>Antwortentwurf</p>'],
                ['id' => $id * 10 + 3, 'type' => 'note', 'body' => '<p>Interne Notiz</p>'],
                ['id' => $id * 10 + 2, 'type' => 'message', 'body' => '<p>Antwort</p>'],
                ['id' => $id * 10 + 1, 'type' => 'customer', 'body' => '<p>Anfrage zu ' . $subject . '</p>'],
            ]],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private static function page(string $embedded, array $items, int $number = 1, int $totalPages = 1): array {
        return ['_embedded' => [$embedded => $items], 'page' => ['size' => 50, 'totalElements' => count($items), 'totalPages' => $totalPages, 'number' => $number]];
    }

    /** @param list<array<string, mixed>> $conversations */
    private function serve(array $conversations): FakePluginHttp {
        $single = [];
        foreach ($conversations as $conversation) {
            $single[self::BASE . '/api/conversations/' . $conversation['id'] . '*'] = FakePluginHttp::response($conversation);
        }

        return FakePluginHttp::fake($single + [
            self::BASE . '/api/conversations/*' => FakePluginHttp::response(['message' => 'Not found'], 404),
            self::BASE . '/api/conversations*' => FakePluginHttp::response(self::page('conversations', $conversations)),
            self::BASE . '/api/mailboxes*' => FakePluginHttp::response(self::page('mailboxes', [
                ['id' => 2, 'name' => 'Hilfe', 'email' => 'hilfe@example.test'],
                ['id' => 3, 'name' => 'Vertrieb', 'email' => 'vertrieb@example.test'],
            ])),
        ]);
    }

    /** @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int} */
    private function sync(bool $full = false): array {
        return app(FreeScoutImportService::class)->run($this->organization, $full);
    }

    private function task(int $remoteId): ?Task {
        $task = ExternalReference::query()->forPlugin($this->organization->id, FreeScoutPlugin::ID, FreeScoutPlugin::EXT_TYPE_CONVERSATION)
            ->forExternalId(SelfHostedInstance::key(self::BASE) . ':' . $remoteId)->first()?->referenceable;

        return $task instanceof Task ? $task : null;
    }

    public function test_verify_and_mailboxes_as_sources(): void {
        $http = $this->serve([]);

        $this->actingAs($this->admin)->post(route('admin.freescout.verify'))->assertSessionHas('success');
        $this->assertSame(SelfHostedInstance::key(self::BASE), FreeScoutConnection::query()->sole()->source_id);
        $http->assertSent(fn (RequestInterface $request): bool => $request->getHeaderLine('X-FreeScout-API-Key') === 'fs-key');

        $this->get(route('admin.freescout.links.create'))->assertOk()->assertSee('Hilfe')->assertSee('Vertrieb');
        $this->post(route('admin.freescout.links.store'), ['source_project_id' => '2', 'project' => $this->project->sqid])->assertRedirect();
        $this->assertSame('hilfe@example.test', FreeScoutProjectLink::query()->sole()->source_project_key);
    }

    public function test_first_run_reads_open_conversations_over_pages_with_the_first_message(): void {
        $this->connect();
        $this->link();
        $pages = [
            1 => [$this->conversation(71, 'Drucker'), $this->conversation(72, 'Fremdes Postfach', 'active', ['mailboxId' => 9])],
            2 => [$this->conversation(73, 'Warten auf Kunde', 'pending')],
        ];
        $http = FakePluginHttp::fake([
            self::BASE . '/api/conversations*' => function (RequestInterface $request) use ($pages) {
                parse_str($request->getUri()->getQuery(), $query);
                $page = (int) ($query['page'] ?? 1);

                return FakePluginHttp::response(self::page('conversations', $pages[$page] ?? [], $page, 2));
            },
        ]);

        $this->assertSame(2, $this->sync()['created']);

        $this->assertSame('Drucker', $this->task(71)?->title);
        $this->assertSame('Anfrage zu Drucker', $this->task(71)?->description, 'Erste veröffentlichte Nachricht, keine Notiz, kein Entwurf.');
        $this->assertSame(TaskStatus::Open, $this->task(71)?->status);
        $this->assertSame(TaskStatus::InProgress, $this->task(73)?->status);
        $this->assertNull($this->task(72));
        $http->assertSent(function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return ($query['page'] ?? null) === '2' && ($query['status'] ?? null) === 'active,pending' && ($query['mailboxId'] ?? null) === '2' && ($query['state'] ?? null) === 'published'
                && ($query['embed'] ?? null) === 'threads' && ($query['sortField'] ?? null) === 'createdAt' && ($query['sortOrder'] ?? null) === 'asc';
        });
        $plugin = app(PluginManager::class)->find(FreeScoutPlugin::ID);
        $this->assertStringContainsString('href="' . self::BASE . '/conversation/71"', (string) $plugin?->renderActions('task-dialog.links', $this->task(71)));
    }

    public function test_later_runs_use_updated_since_and_spam_closes(): void {
        $this->freezeSecond();
        $this->connect();
        $this->link();
        $this->serve([$this->conversation(71, 'A'), $this->conversation(74, 'B')]);
        $this->sync();

        $this->travel(20)->minutes();
        $http = $this->serve([$this->conversation(71, 'A', 'closed'), $this->conversation(74, 'B', 'spam')]);
        $this->sync();

        $this->assertSame(TaskStatus::Done, $this->task(71)?->status);
        $this->assertSame(TaskStatus::Done, $this->task(74)?->status, 'Spam gilt als erledigt.');
        $http->assertSent(function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return ($query['status'] ?? null) === 'active,pending,closed,spam'
                && ($query['updatedSince'] ?? null) === now()->subMinutes(30)->utc()->format('Y-m-d\TH:i:s\Z');
        });
    }

    public function test_attach_reads_the_conversation_and_refuses_another_mailbox(): void {
        $this->connect();
        $link = $this->link(confirmed: false);
        $task = Task::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $this->project->id, 'title' => 'Drucker']);
        $this->serve([$this->conversation(71, 'Drucker'), $this->conversation(72, 'Fremd', 'active', ['mailboxId' => 9])]);

        $this->actingAs($this->admin)->post(route('admin.freescout.links.attach', $link), ['remote_id' => '72', 'task' => $task->sqid])->assertSessionHas('error');
        $this->post(route('admin.freescout.links.attach', $link), ['remote_id' => '71', 'task' => $task->sqid])->assertSessionHas('success');
        $this->assertSame('Anfrage zu Drucker', $task->fresh()?->description);
    }

    public function test_sync_command_runs_the_confirmed_mappings(): void {
        $this->connect();
        $this->link();
        $this->serve([$this->conversation(71, 'Drucker')]);

        $this->artisan('freescout:sync', ['--full' => true])->expectsOutputToContain('created 1')->assertSuccessful();
    }
}
