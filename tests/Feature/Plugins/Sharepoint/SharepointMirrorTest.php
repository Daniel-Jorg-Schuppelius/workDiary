<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SharepointMirrorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Sharepoint;

use App\Enums\Document\{DocumentStatus, DocumentType};
use App\Models\{Document, DocumentVersion, ExternalReference, IntegrationInboxItem, IntegrationOutboxEntry, Organization, SharepointConnection, User, WebdavConnection};
use App\Plugins\Sharepoint\Api\SharepointDriveClient;
use App\Plugins\Sharepoint\{SharepointMirrorTarget, SharepointPlugin};
use App\Plugins\Support\Mirror\MirrorOutboxDispatcher;
use App\Plugins\Webdav\WebdavPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue, Storage};
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-330 (Bauturbo A10): SharePoint-Spiegelzweig über den gemeinsamen
 * Spiegel-Kern — Freigabe→Outbox (plugin-präfixierter Idempotenzschlüssel,
 * parallel zu WebDAV), Upload klein (PUT :/content) und groß
 * (createUploadSession + Chunk-PUTs ohne Authorization-Header), Konflikt →
 * Inbox statt Überschreiben (cTag), Idempotenz beim Replay, Health-Zählung
 * mit Auto-Disable-Skip und Org-Isolation.
 */
final class SharepointMirrorTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const GRAPH = 'https://graph.microsoft.com/v1.0';

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        Storage::fake('local');
        Queue::fake(); // Observer-Einreihung stellt keine echten Jobs zu
    }

    /** @param  array<string, mixed>  $attributes */
    private function connection(array $attributes = []): SharepointConnection {
        return SharepointConnection::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'access_token' => 'secret-token',
            'status' => SharepointConnection::STATUS_ACTIVE,
            'site_id' => 'site-1',
            'site_name' => 'Bau-Projekte',
            'drive_id' => 'drive-1',
            'drive_name' => 'Dokumente',
            'default_folder' => 'Dokumente',
            'folder_map' => ['testReport' => 'Pruefberichte'],
            'active' => true,
        ]);
    }

    /** @return array{0: Document, 1: string} */
    private function makeDocument(string $contents = 'CONTENT-A', ?int $organizationId = null): array {
        $path = 'documents/2026/07/' . bin2hex(random_bytes(6)) . '.pdf';
        Storage::disk('local')->put($path, $contents);

        $document = Document::query()->create([
            'organization_id' => $organizationId ?? $this->organization->id,
            'title' => 'Prüfbericht',
            'document_type' => DocumentType::TestReport,
            'status' => DocumentStatus::Active,
            'created_by_user_id' => $this->user->id,
        ]);
        $version = DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_no' => 1,
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'bericht.pdf',
            'mime' => 'application/pdf',
            'size' => strlen($contents),
            'uploaded_by_user_id' => $this->user->id,
        ]);
        $document->forceFill(['current_version_id' => $version->id])->save();

        return [$document, $path];
    }

    /**
     * Standard-Stubs eines erfolgreichen Spiegel-Laufs (Ordner + Upload + Signatur).
     *
     * @param  array<string, mixed>  $extra  zusätzliche/übersteuernde Stubs (zuerst gematcht)
     */
    private function fakeGraph(array $extra = [], string $cTag = 'ctag-1'): FakePluginHttp {
        return FakePluginHttp::fake($extra + [
            self::GRAPH . '/drives/drive-1/root/children' => FakePluginHttp::response(['id' => 'folder-1'], 201),
            self::GRAPH . '/drives/drive-1/root:/*:/content' => FakePluginHttp::response(['id' => 'item-1'], 201),
            self::GRAPH . '/drives/drive-1/root:/*' => FakePluginHttp::response(['cTag' => $cTag]),
        ]);
    }

    private function pendingEntry(): IntegrationOutboxEntry {
        return IntegrationOutboxEntry::query()
            ->where('plugin_id', SharepointPlugin::ID)
            ->where('operation', MirrorOutboxDispatcher::OP_MIRROR)
            ->firstOrFail();
    }

    private function dispatch(): bool {
        return (new MirrorOutboxDispatcher(new SharepointMirrorTarget()))->dispatch($this->pendingEntry());
    }

    public function test_releasing_document_enqueues_with_plugin_prefixed_key(): void {
        $this->connection();
        [$document] = $this->makeDocument();

        $entry = $this->pendingEntry();
        // Präfix trennt den Schlüsselraum vom WebDAV-Ziel (Outbox-Unique ohne plugin_id).
        $this->assertSame('sharepoint:mirror:doc-' . $document->id . ':v' . $document->current_version_id, $entry->idempotency_key);
    }

    public function test_enqueue_is_idempotent_per_version(): void {
        $this->connection();
        [$document] = $this->makeDocument();

        // Reine Metadaten-Änderung (gleiche Version) → kein zweiter Eintrag.
        $document->forceFill(['title' => 'Prüfbericht (aktualisiert)'])->save();

        $this->assertSame(1, IntegrationOutboxEntry::query()->where('plugin_id', SharepointPlugin::ID)->count());
    }

    public function test_webdav_and_sharepoint_mirror_side_by_side(): void {
        $this->connection();
        WebdavConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Nextcloud',
            'base_url' => 'https://cloud.example.com/remote.php/dav/files/svc/WorkDiary',
            'username' => 'svc',
            'app_password' => 'secret',
            'default_folder' => 'Dokumente',
            'active' => true,
        ]);

        $this->makeDocument();

        // Beide Ablage-Ziele erhalten je einen eigenen Outbox-Eintrag.
        $this->assertSame(1, IntegrationOutboxEntry::query()->where('plugin_id', SharepointPlugin::ID)->count());
        $this->assertSame(1, IntegrationOutboxEntry::query()->where('plugin_id', WebdavPlugin::ID)->count());
    }

    public function test_dispatcher_uploads_small_file_via_put_content(): void {
        $this->connection();
        [$document] = $this->makeDocument('SMALL-CONTENT');
        $fake = $this->fakeGraph();

        $this->assertTrue($this->dispatch());

        // Ordner nach Typ-Regel + PUT auf den Pfad (Segment-kodiert).
        $expectedPath = 'Pruefberichte/document-' . $document->id . '.pdf';
        $fake->assertSent(fn (RequestInterface $r): bool => $r->getMethod() === 'PUT'
            && str_contains((string) $r->getUri(), '/drives/drive-1/root:/' . $expectedPath . ':/content'));

        $ref = ExternalReference::query()
            ->where('plugin_id', SharepointPlugin::ID)
            ->where('external_type', 'dms_object')
            ->firstOrFail();
        $this->assertSame($expectedPath, $ref->payload['remote_path']);
        $this->assertSame('ctag-1', $ref->payload['remote_sig']);
        $this->assertSame(hash('sha256', 'SMALL-CONTENT'), $ref->payload['sha256']);

        $connection = SharepointConnection::query()->firstOrFail();
        $this->assertNotNull($connection->last_mirrored_at);
    }

    public function test_second_dispatch_without_change_is_unchanged_without_duplicate(): void {
        $this->connection();
        $this->makeDocument('SAME');
        $this->fakeGraph();
        $this->assertTrue($this->dispatch());

        // Replay: unveränderter Inhalt → SHA-Kurzschluss, kein einziger HTTP-Request.
        $fake = FakePluginHttp::fake();
        $this->assertTrue($this->dispatch());

        $fake->assertNothingSent();
        $this->assertSame(1, ExternalReference::query()->where('plugin_id', SharepointPlugin::ID)->count());
    }

    public function test_large_file_uses_upload_session_chunks_without_auth_header(): void {
        $this->connection();
        $total = SharepointDriveClient::SIMPLE_UPLOAD_LIMIT + 500_000; // > 4 MB → Session
        $this->makeDocument(str_repeat('A', $total));

        $fake = $this->fakeGraph([
            self::GRAPH . '/drives/drive-1/root:/*:/createUploadSession' => FakePluginHttp::response(['uploadUrl' => 'https://upload.example.com/session-1']),
            'https://upload.example.com/session-1*' => [
                FakePluginHttp::response([], 202),
                FakePluginHttp::response(['id' => 'item-1'], 201),
            ],
        ]);

        $this->assertTrue($this->dispatch());

        $chunks = array_values(array_filter($fake->recorded(), fn (array $entry): bool => str_starts_with((string) $entry['request']->getUri(), 'https://upload.example.com/')));
        $this->assertCount(2, $chunks);

        $chunkSize = SharepointDriveClient::CHUNK_SIZE;
        $this->assertSame(sprintf('bytes 0-%d/%d', $chunkSize - 1, $total), $chunks[0]['request']->getHeader('Content-Range')[0] ?? '');
        $this->assertSame(sprintf('bytes %d-%d/%d', $chunkSize, $total - 1, $total), $chunks[1]['request']->getHeader('Content-Range')[0] ?? '');
        // Graph-Vorgabe: Chunk-PUTs an die Session-URL OHNE Authorization-Header.
        $this->assertSame([], $chunks[0]['request']->getHeader('Authorization'));
        $this->assertSame([], $chunks[1]['request']->getHeader('Authorization'));

        // Session-Anlage selbst läuft authentifiziert.
        $fake->assertSent(fn (RequestInterface $r): bool => str_contains((string) $r->getUri(), ':/createUploadSession')
            && ($r->getHeader('Authorization')[0] ?? '') === 'Bearer secret-token');
    }

    public function test_external_change_raises_conflict_into_inbox_without_overwrite(): void {
        $this->connection();
        [, $path] = $this->makeDocument('V1');
        $this->fakeGraph(cTag: 'ctag-1');
        $this->assertTrue($this->dispatch());

        // Lokal geändert UND remote fremdverändert (cTag weicht ab) → Konflikt.
        Storage::disk('local')->put($path, 'V2');
        $fake = $this->fakeGraph(cTag: 'ctag-EXTERN');
        $this->assertTrue($this->dispatch());

        $fake->assertNotSent(fn (RequestInterface $r): bool => $r->getMethod() === 'PUT'); // NIE stilles Überschreiben
        $item = IntegrationInboxItem::query()
            ->where('plugin_id', SharepointPlugin::ID)
            ->where('case_type', IntegrationInboxItem::CASE_CONFLICT)
            ->firstOrFail();
        $this->assertSame(IntegrationInboxItem::STATUS_OPEN, $item->status);
    }

    public function test_transient_failure_counts_health_and_rethrows(): void {
        $this->connection();
        $this->makeDocument();
        $this->fakeGraph([
            self::GRAPH . '/drives/drive-1/root:/*:/content' => FakePluginHttp::response(['error' => ['code' => 'serviceNotAvailable']], 500),
        ]);

        try {
            $this->dispatch();
            $this->fail('Transienter Zustellfehler muss werfen (Outbox-Retry).');
        } catch (RuntimeException) {
            // erwartet — Outbox wiederholt.
        }

        $connection = SharepointConnection::query()->firstOrFail();
        $this->assertSame(1, $connection->consecutive_failures);
        $this->assertNotNull($connection->last_error);
    }

    public function test_auto_disabled_connection_is_skipped(): void {
        $connection = $this->connection();
        $this->makeDocument();
        $connection->forceFill(['disabled_at' => now()])->save(); // Auto-Disable (MVP-178)

        $fake = FakePluginHttp::fake();
        $this->assertTrue($this->dispatch()); // bestätigt ohne Zustellversuch

        $fake->assertNothingSent();
        $this->assertSame(0, ExternalReference::query()->where('plugin_id', SharepointPlugin::ID)->count());
    }

    public function test_no_enqueue_for_other_organization(): void {
        // Verbindung gehört einer FREMDEN Organisation → hiesige Freigabe reiht nichts ein.
        $other = Organization::factory()->create();
        SharepointConnection::query()->create([
            'organization_id' => $other->id,
            'access_token' => 'secret-token',
            'status' => SharepointConnection::STATUS_ACTIVE,
            'site_id' => 'site-1',
            'drive_id' => 'drive-1',
            'active' => true,
        ]);

        $this->makeDocument();

        $this->assertSame(0, IntegrationOutboxEntry::query()
            ->where('organization_id', $this->organization->id)
            ->where('plugin_id', SharepointPlugin::ID)
            ->count());
    }

    public function test_detach_via_conflict_resolution_stops_mirroring(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->connection();
        [$document, $path] = $this->makeDocument('V1');
        $this->fakeGraph(cTag: 'ctag-1');
        $this->assertTrue($this->dispatch());

        Storage::disk('local')->put($path, 'V2');
        $this->fakeGraph(cTag: 'ctag-EXTERN');
        $this->assertTrue($this->dispatch()); // provoziert den Konflikt

        $item = IntegrationInboxItem::query()->where('plugin_id', SharepointPlugin::ID)->firstOrFail();
        $this->actingAs($admin)
            ->post(route('admin.sharepoint.conflict.detach', $item))
            ->assertRedirect();

        $document->refresh();
        $this->assertTrue($document->sharepoint_mirror_detached);
        $this->assertFalse((bool) $document->webdav_mirror_detached); // nur DIESER Zweig getrennt
        $this->assertSame(0, ExternalReference::query()->where('plugin_id', SharepointPlugin::ID)->count());
        $this->assertSame(IntegrationInboxItem::STATUS_DISMISSED, $item->fresh()->status);

        // Getrennt → Dispatcher bestätigt ohne Upload (RESULT_SKIPPED).
        $fake = FakePluginHttp::fake();
        $this->assertTrue($this->dispatch());
        $fake->assertNothingSent();
    }
}
