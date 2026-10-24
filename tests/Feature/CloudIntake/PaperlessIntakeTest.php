<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaperlessIntakeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\CloudIntake;

use App\Enums\CloudIntake\{CloudIntakeConnectionStatus, CloudIntakeItemStatus, CloudIntakeProvider, CloudIntakeRouteTarget};
use App\Models\CloudIntake\{CloudDocumentConnection, CloudDocumentItem, CloudDocumentRoute};
use App\Models\Document\Document;
use App\Models\Platform\User;
use App\Plugins\Paperless\Exceptions\PaperlessInstanceChangedException;
use App\Plugins\Paperless\PaperlessPlugin;
use App\Plugins\PluginManager;
use App\Plugins\Support\Intake\IntakeItem;
use App\Plugins\Support\TaskSync\SelfHostedInstance;
use App\Services\CloudIntake\{CloudIntakeRunner, StaleCheckpointException};
use App\Services\Integration\RecordOrigins;
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Paperless-ngx im Cloud-Dokumenteingang (MVP-1314–1317): Verbindung aus den
 * Plugin-Einstellungen mit festgehaltener Instanz, Zyklus über die ID-Liste
 * (auch nachträglich per Massenbearbeitung getaggte Dokumente ohne neues
 * `modified`), Prüfsumme als Revision, Stammordner als Pfadfilter,
 * Originaldownload, Herkunft am Beleg, Abgänge als Tombstones.
 */
class PaperlessIntakeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    private const BASE = 'https://paperless.example.test';

    /** @var array<int, array<string, mixed>> */
    private array $documents = [];

    private ?int $listCount = null;

    /** @var list<int> Beim Blättern übersprungene Dokumente (gleichzeitiges Löschen und Anlegen) */
    private array $skippedInListing = [];

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->pluginSecret(PaperlessPlugin::ID, ['base_url' => self::BASE . '/', 'api_token' => 'pl-token']);
        $this->admin = $this->orgAdmin();
        app()->instance('currentOrganization', $this->organization);

        $this->document(1, 5, 3, 're-1.pdf', 'aaa1');
        $this->document(2, 6, null, 'vertrag.pdf', 'bbb2');
        $this->document(3, 5, null, null, 'ccc3');
    }

    /** @param list<int> $tags */
    private function document(int $id, ?int $type, ?int $correspondent, ?string $file, string $checksum, string $modified = '2026-10-20T08:00:00.000000Z', array $tags = []): void {
        $this->documents[$id] = [
            'id' => $id,
            'title' => 'Beleg ' . $id,
            'document_type' => $type,
            'correspondent' => $correspondent,
            'tags' => $tags,
            'original_file_name' => $file,
            'modified' => $modified,
            'checksum' => $checksum,
        ];
    }

    private function connection(string $container = 'all', string $root = '/'): CloudDocumentConnection {
        return CloudDocumentConnection::factory()->active()->create([
            'organization_id' => $this->organization->id,
            'provider' => CloudIntakeProvider::Paperless,
            'name' => 'Paperless',
            'external_account_id' => SelfHostedInstance::key(self::BASE) . ':7',
            'container_id' => $container,
            'root_folder_path' => $root,
            'created_by_user_id' => $this->admin->id,
        ]);
    }

    private function route(CloudDocumentConnection $connection, string $pattern): CloudDocumentRoute {
        return CloudDocumentRoute::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'path_pattern' => $pattern,
            'target' => CloudIntakeRouteTarget::Document,
        ]);
    }

    /** Kleine Paperless-Instanz: Filter, Sortierung und Seiten wie die echte Liste. */
    private function serve(): FakePluginHttp {
        $names = static fn (array $rows): array => ['count' => count($rows), 'next' => null, 'previous' => null, 'results' => $rows];

        return FakePluginHttp::fake([
            self::BASE . '/api/ui_settings/' => FakePluginHttp::response(['user' => ['id' => 7, 'username' => 'erika'], 'settings' => []]),
            self::BASE . '/api/documents/*/metadata/' => function (RequestInterface $request) {
                $document = $this->documents[(int) explode('/', $request->getUri()->getPath())[3]] ?? null;

                return $document === null ? FakePluginHttp::response(['detail' => 'Not found'], 404) : FakePluginHttp::response([
                    'original_checksum' => $document['checksum'],
                    'original_size' => 20,
                    'original_mime_type' => 'application/pdf',
                    'original_filename' => $document['original_file_name'],
                    'media_filename' => sprintf('%07d.pdf', $document['id']),
                ]);
            },
            self::BASE . '/api/documents/*/download/*' => function (RequestInterface $request) {
                $document = $this->documents[(int) explode('/', $request->getUri()->getPath())[3]];

                return FakePluginHttp::response('%PDF-1.4 Inhalt ' . $document['checksum'], 200, ['Content-Type' => 'application/pdf']);
            },
            self::BASE . '/api/documents/*' => fn (RequestInterface $request) => FakePluginHttp::response($this->list($request)),
            self::BASE . '/api/correspondents/*' => FakePluginHttp::response($names([['id' => 3, 'name' => 'Muster/Söhne GmbH']])),
            self::BASE . '/api/document_types/*' => FakePluginHttp::response($names([['id' => 5, 'name' => 'Rechnung'], ['id' => 6, 'name' => 'Vertrag']])),
            self::BASE . '/api/tags/*' => FakePluginHttp::response($names([['id' => 9, 'name' => 'WorkDiary']])),
        ]);
    }

    /** @return array<string, mixed> */
    private function list(RequestInterface $request): array {
        parse_str($request->getUri()->getQuery(), $query);
        $skipped = isset($query['id__in']) ? [] : $this->skippedInListing;
        $rows = array_values(array_filter($this->documents, static function (array $document) use ($query, $skipped): bool {
            if (in_array($document['id'], $skipped, true)) {
                return false;
            }

            return (! isset($query['tags__id__all']) || in_array((int) $query['tags__id__all'], $document['tags'], true))
                && (! isset($query['correspondent__id']) || $document['correspondent'] === (int) $query['correspondent__id'])
                && (! isset($query['document_type__id']) || $document['document_type'] === (int) $query['document_type__id'])
                && (! is_string($query['modified__gt'] ?? null) || strcmp($document['modified'], $query['modified__gt']) > 0)
                && (! is_string($query['id__in'] ?? null) || in_array($document['id'], array_map('intval', explode(',', $query['id__in'])), true));
        }));
        usort($rows, ($query['ordering'] ?? 'id') === '-modified'
            ? static fn (array $a, array $b): int => strcmp($b['modified'], $a['modified'])
            : static fn (array $a, array $b): int => $a['id'] <=> $b['id']);
        $size = (int) ($query['page_size'] ?? 25);
        $page = (int) ($query['page'] ?? 1);
        $slice = array_slice($rows, ($page - 1) * $size, $size);

        return [
            'count' => $this->listCount ?? count($rows),
            'next' => $page * $size < count($rows) ? 'https://paperless.example.test/api/documents/?page=' . ($page + 1) : null,
            'previous' => null,
            'results' => array_map(static fn (array $document): array => array_diff_key($document, ['checksum' => true]), $slice),
        ];
    }

    private function plugin(): PaperlessPlugin {
        $plugin = app(PluginManager::class)->find(PaperlessPlugin::ID);
        $this->assertInstanceOf(PaperlessPlugin::class, $plugin);

        return $plugin;
    }

    /** @return array{status: string, pages: int, imported: int, inbox: int, rejected: int, duplicates: int, skipped: int, tombstones: int} */
    private function intake(CloudDocumentConnection $connection): array {
        return app(CloudIntakeRunner::class)->run($connection->refresh());
    }

    private function sentMetadataFor(FakePluginHttp $http, int $id): bool {
        foreach ($http->recorded() as $call) {
            if ($call['request']->getUri()->getPath() === '/api/documents/' . $id . '/metadata/') {
                return true;
            }
        }

        return false;
    }

    public function test_connect_checks_the_account_and_keeps_the_instance(): void {
        $this->serve();
        $this->actingAs($this->admin)->get(route('admin.cloud-intake.index'))->assertOk()
            ->assertSee(route('admin.cloud-intake.paperless.connect-form'), false);
        $this->get(route('admin.cloud-intake.paperless.connect-form'))->assertOk()
            ->assertSee('paperless.example.test')
            ->assertSee(route('admin.cloud-intake.paperless.connect'), false);

        $this->post(route('admin.cloud-intake.paperless.connect'), ['name' => 'Archiv'])
            ->assertRedirect(route('admin.cloud-intake.index'))->assertSessionHas('success');

        $connection = CloudDocumentConnection::query()->sole();
        $this->assertSame(CloudIntakeProvider::Paperless, $connection->provider);
        $this->assertSame(SelfHostedInstance::key(self::BASE) . ':7', $connection->external_account_id);
        $this->assertSame('erika @ paperless.example.test', $connection->external_account_label);
        $this->assertSame(CloudIntakeConnectionStatus::Draft, $connection->status, 'Ohne Quelle, Stammordner und Regel bleibt die Verbindung Entwurf.');
        $this->assertNull($connection->access_token, 'Der Token bleibt in den Plugin-Einstellungen.');

        $this->pluginSecret(PaperlessPlugin::ID, ['base_url' => 'https://anderes.example.test']);
        FakePluginHttp::fake(['*' => FakePluginHttp::response(['user' => ['id' => 7, 'username' => 'erika']])]);
        $this->post(route('admin.cloud-intake.paperless.connect'), ['name' => 'Archiv'])
            ->assertSessionHas('error', __('paperless::paperless.flash.instance_changed'));
        $this->assertSame(SelfHostedInstance::key(self::BASE) . ':7', $connection->refresh()->external_account_id);
        $this->assertSame(1, CloudDocumentConnection::query()->count(), 'Eine Paperless-Verbindung je Organisation.');
    }

    public function test_unconfigured_plugin_points_to_the_settings(): void {
        $this->pluginSecret(PaperlessPlugin::ID, ['base_url' => null, 'api_token' => null]);
        FakePluginHttp::fake([]);

        $this->actingAs($this->admin)->get(route('admin.cloud-intake.paperless.connect-form'))->assertOk()
            ->assertSee(route('admin.plugins.edit', PaperlessPlugin::ID), false)
            ->assertDontSee(route('admin.cloud-intake.paperless.connect'), false);
        $this->post(route('admin.cloud-intake.paperless.connect'), ['name' => 'Archiv'])
            ->assertSessionHas('error', __('paperless::paperless.flash.not_configured'));
        $this->assertSame(0, CloudDocumentConnection::query()->count());
    }

    public function test_cycle_builds_paths_filters_the_root_and_reads_metadata_only_inside(): void {
        $connection = $this->connection('all', '/Rechnung');
        $http = $this->serve();

        $page = $this->plugin()->intakeChanges($connection, null);

        $this->assertFalse($page->hasMore);
        $this->assertSame(['1', '3'], array_map(static fn (IntakeItem $item): string => $item->itemId, $page->items));
        [$first, $third] = $page->items;
        $this->assertSame('Muster-Söhne GmbH/re-1.pdf', $first->path, 'Schrägstriche im Namen trennen keine Segmente.');
        $this->assertSame('-/Beleg 3.pdf', $third->path, 'Ohne Dateinamen: Titel und Endung der abgelegten Datei.');
        $this->assertSame('aaa1', $first->revision);
        $this->assertSame(20, $first->size);
        $this->assertFalse($this->sentMetadataFor($http, 2), 'Außerhalb des Stammordners kein Metadatenabruf.');

        $tagged = $this->connection('tag:9');
        $http->assertNotSent(static fn (RequestInterface $request): bool => str_contains($request->getUri()->getQuery(), 'tags__id__all'));
        $this->plugin()->intakeChanges($tagged, null);
        $http->assertSent(static fn (RequestInterface $request): bool => str_contains($request->getUri()->getQuery(), 'tags__id__all=9'));
    }

    public function test_runner_imports_new_members_records_the_origin_and_marks_departures(): void {
        $connection = $this->connection();
        $this->route($connection, 'Rechnung/**');
        $http = $this->serve();

        $first = $this->intake($connection);

        $this->assertSame('ok', $first['status']);
        $this->assertSame(2, $first['imported']);
        $this->assertSame(1, $first['skipped'], 'Vertrag passt auf keine Regel.');
        $evidence = CloudDocumentItem::query()->where('external_item_id', '1')->sole();
        $this->assertSame('aaa1', $evidence->revision);
        $this->assertSame(MorphMap::alias(Document::class), $evidence->imported_type);
        $http->assertSent(static fn (RequestInterface $request): bool => str_ends_with($request->getUri()->getPath(), '/download/') && $request->getUri()->getQuery() === 'original=true');

        $document = Document::query()->findOrFail($evidence->imported_id);
        $origins = app(RecordOrigins::class)->for($document);
        $this->assertSame([self::BASE . '/documents/1/details'], array_column($origins, 'url'));
        $this->actingAs($this->admin)->get(route('documents.show', $document))->assertOk()
            ->assertSee(self::BASE . '/documents/1/details', false);

        // Unverändert: weder Metadaten noch neue Nachweise.
        $http = $this->serve();
        $second = $this->intake($connection);
        $this->assertSame(0, $second['imported']);
        $this->assertFalse($this->sentMetadataFor($http, 1));
        $this->assertFalse($this->sentMetadataFor($http, 2), 'Bereits ausgewertete Dokumente ohne Regeltreffer werden nicht erneut gelesen.');

        // Per Massenbearbeitung nachträglich aufgenommen: altes `modified`, trotzdem neu.
        $this->document(4, 5, 3, 're-4.pdf', 'ddd4', '2026-01-01T00:00:00.000000Z');
        $this->serve();
        $this->assertSame(1, $this->intake($connection)['imported']);
        $this->assertSame(1, CloudDocumentItem::query()->where('external_item_id', '4')->count());

        unset($this->documents[1]);
        $this->serve();
        $this->assertSame(1, $this->intake($connection)['tombstones']);
        $this->assertSame(CloudIntakeItemStatus::SourceGone, $evidence->fresh()?->status);
        $this->assertNotNull(Document::query()->find($document->id), 'Übernommene Belege bleiben.');
    }

    public function test_a_document_skipped_while_paging_is_not_reported_as_departed(): void {
        $connection = $this->connection();
        $this->route($connection, 'Rechnung/**');
        $this->serve();
        $this->intake($connection);

        // Gesamtzahl und gelesene Einträge stimmen überein, und doch fehlt Dokument 1 (Löschen und Anlegen zugleich).
        $this->skippedInListing = [1];
        $this->serve();
        $this->assertSame(0, $this->intake($connection)['tombstones'], 'Auf direkte Nachfrage führt Paperless das Dokument noch.');
        $this->assertNotSame(CloudIntakeItemStatus::SourceGone, CloudDocumentItem::query()->where('external_item_id', '1')->sole()->status);
    }

    public function test_changed_original_and_changed_rules_are_evaluated_again(): void {
        $connection = $this->connection();
        $route = $this->route($connection, 'Rechnung/**');
        $this->serve();
        $this->intake($connection);

        $this->documents[3]['checksum'] = 'ccc3-neu';
        $this->documents[3]['modified'] = '2026-10-22T09:00:00.000000Z';
        $http = $this->serve();
        $result = $this->intake($connection);

        $this->assertTrue($this->sentMetadataFor($http, 3));
        $this->assertFalse($this->sentMetadataFor($http, 1));
        $this->assertSame(1, $result['inbox'], 'Neue Revision eines übernommenen Dokuments: Versionsvorschlag.');
        $this->assertSame(['ccc3', 'ccc3-neu'], CloudDocumentItem::query()->where('external_item_id', '3')->orderBy('id')->pluck('revision')->all());

        $this->route($connection, 'Vertrag/**');
        $route->touch();
        $this->serve();
        $this->assertSame(1, $this->intake($connection)['imported'], 'Neue Regel: der Vertrag wird jetzt ausgewertet.');
    }

    public function test_incomplete_listing_and_another_instance_fail_without_moving_the_checkpoint(): void {
        $connection = $this->connection();
        $this->route($connection, 'Rechnung/**');
        $this->listCount = 5;
        $this->serve();

        $result = $this->intake($connection);

        $this->assertSame('failed', $result['status']);
        $this->assertNull($connection->refresh()->checkpoint);
        $this->assertSame('IncompleteRemoteReadException', $connection->refresh()->last_error);
        $this->assertSame(0, CloudDocumentItem::query()->count());

        $this->listCount = null;
        $this->pluginSecret(PaperlessPlugin::ID, ['base_url' => 'https://anderes.example.test']);
        $this->serve();
        $this->assertSame('failed', $this->intake($connection)['status']);
        $this->assertSame(class_basename(PaperlessInstanceChangedException::class), $connection->refresh()->last_error);

        $this->expectException(StaleCheckpointException::class);
        $this->pluginSecret(PaperlessPlugin::ID, ['base_url' => self::BASE]);
        $this->plugin()->intakeChanges($connection->refresh(), '{"v":0}');
    }

    public function test_health_reports_setup_and_reachability(): void {
        $plugin = $this->plugin();
        $this->serve();
        $this->assertSame('not_connected', $plugin->healthCheck()->code);

        $this->connection();
        $this->assertSame('ok', $plugin->healthCheck()->status);

        FakePluginHttp::fake(['*' => FakePluginHttp::response(['detail' => 'Invalid token.'], 401)]);
        $this->assertSame('auth', $plugin->healthCheck()->code);
    }

    public function test_download_rejects_foreign_item_ids(): void {
        $connection = $this->connection();
        $this->serve();

        $this->expectException(\RuntimeException::class);
        $this->plugin()->intakeDownload($connection, new IntakeItem('../etc', 'x', 'x', 'r', 1));
    }
}
