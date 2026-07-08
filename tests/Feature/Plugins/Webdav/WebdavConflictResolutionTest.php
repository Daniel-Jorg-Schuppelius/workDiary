<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebdavConflictResolutionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Webdav;

use App\Enums\Document\{DocumentStatus, DocumentType};
use App\Models\{AuditLog, Document, DocumentVersion, ExternalReference, IntegrationInboxItem, User, WebdavConnection};
use App\Plugins\Webdav\Contracts\{WebdavGateway, WebdavGatewayFactory};
use App\Plugins\Webdav\Services\DocumentMirrorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue, Storage};
use Tests\Concerns\WithOrganization;
use Tests\Support\RecordingWebdavGateway;
use Tests\TestCase;

/**
 * Feature 058, MVP-127, Rang 18: Konfliktauflösung eines WebDAV-Spiegelkonflikts
 * aus der Inbox — überschreiben / als neue Version importieren / Spiegelung
 * trennen. Jeweils auditiert und idempotent gegen erneutes Aufflammen.
 */
final class WebdavConflictResolutionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        Storage::fake('local');
        Queue::fake(); // Observer-Einreihung stellt keine echten Jobs zu
    }

    /** @return array{0: Document, 1: WebdavConnection, 2: IntegrationInboxItem} */
    private function provokeConflict(): array {
        $path = 'documents/2026/07/' . bin2hex(random_bytes(6)) . '.pdf';
        Storage::disk('local')->put($path, 'V1-LOCAL');

        $document = Document::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Prüfbericht',
            'document_type' => DocumentType::TestReport,
            'status' => DocumentStatus::Active,
            'created_by_user_id' => $this->admin->id,
        ]);
        $version = DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_no' => 1,
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'bericht.pdf',
            'mime' => 'application/pdf',
            'size' => 8,
            'uploaded_by_user_id' => $this->admin->id,
        ]);
        $document->forceFill(['current_version_id' => $version->id])->save();

        $connection = WebdavConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Nextcloud',
            'base_url' => 'https://cloud.example.com/remote.php/dav/files/svc/WorkDiary',
            'username' => 'svc',
            'app_password' => 'secret',
            'default_folder' => 'Dokumente',
            'folder_map' => ['testReport' => 'Pruefberichte'],
            'active' => true,
        ]);

        $service = new DocumentMirrorService();
        $service->mirror($document, $connection, new RecordingWebdavGateway(signature: 'etag-1'));
        Storage::disk('local')->put($path, 'V2-LOCAL'); // lokal geändert
        // Remote fremdverändert (abweichende Signatur) → Konflikt statt Überschreiben.
        $service->mirror($document->refresh(), $connection, new RecordingWebdavGateway(signature: 'etag-EXTERN'));

        $item = IntegrationInboxItem::query()
            ->where('plugin_id', DocumentMirrorService::PLUGIN_ID)
            ->where('case_type', IntegrationInboxItem::CASE_CONFLICT)
            ->firstOrFail();

        return [$document, $connection, $item];
    }

    private function bindGateway(string $signature = 'etag-EXTERN', string $downloadBody = 'REMOTE-V2'): RecordingWebdavGateway {
        $gateway = new RecordingWebdavGateway(signature: $signature, downloadBody: $downloadBody);

        $this->app->instance(WebdavGatewayFactory::class, new class($gateway) implements WebdavGatewayFactory {
            public function __construct(private WebdavGateway $gateway) {}

            public function for(WebdavConnection $connection): WebdavGateway {
                return $this->gateway;
            }
        });

        return $gateway;
    }

    private function auditExists(IntegrationInboxItem $item, string $event): bool {
        return AuditLog::query()->where('event', $event)->exists()
            && AuditLog::query()->where('event', 'integration.inbox_resolved')
                ->where('auditable_id', $item->getKey())->exists();
    }

    public function test_overwrite_forces_remote_and_resolves_local(): void {
        [$document, , $item] = $this->provokeConflict();
        $gateway = $this->bindGateway();

        $this->actingAs($this->admin)
            ->post(route('admin.webdav.conflict.overwrite', $item))
            ->assertRedirect();

        $this->assertNotEmpty($gateway->puts); // lokaler Stand wurde durchgesetzt
        $this->assertSame(IntegrationInboxItem::STATUS_RESOLVED_LOCAL, $item->fresh()->status);
        $this->assertTrue($this->auditExists($item, 'webdav.conflict.overwritten'));
        // Referenz auf die aktuelle Remote-Signatur nachgezogen.
        $ref = ExternalReference::query()->where('plugin_id', DocumentMirrorService::PLUGIN_ID)->firstOrFail();
        $this->assertSame('etag-EXTERN', $ref->payload['remote_sig']);
    }

    public function test_import_adds_remote_as_new_version(): void {
        [$document, , $item] = $this->provokeConflict();
        $this->bindGateway(downloadBody: 'REMOTE-V2');

        $this->actingAs($this->admin)
            ->post(route('admin.webdav.conflict.import', $item))
            ->assertRedirect();

        $document->refresh();
        $this->assertSame(2, $document->versions()->count());
        $current = $document->currentVersion;
        $this->assertNotNull($current);
        $this->assertSame('REMOTE-V2', Storage::disk('local')->get((string) $current->path));
        $this->assertSame(IntegrationInboxItem::STATUS_RESOLVED_REMOTE, $item->fresh()->status);
        $this->assertTrue($this->auditExists($item, 'webdav.conflict.imported'));

        // Referenz spiegelt den importierten Stand → kein sofortiger Neu-Konflikt.
        $ref = ExternalReference::query()->where('plugin_id', DocumentMirrorService::PLUGIN_ID)->firstOrFail();
        $this->assertSame(hash('sha256', 'REMOTE-V2'), $ref->payload['sha256']);
    }

    public function test_detach_removes_reference_and_stops_mirroring(): void {
        [$document, $connection, $item] = $this->provokeConflict();
        $this->bindGateway();

        $this->actingAs($this->admin)
            ->post(route('admin.webdav.conflict.detach', $item))
            ->assertRedirect();

        $this->assertSame(0, ExternalReference::query()->where('plugin_id', DocumentMirrorService::PLUGIN_ID)->count());
        $this->assertTrue($document->fresh()->webdav_mirror_detached);
        $this->assertSame(IntegrationInboxItem::STATUS_DISMISSED, $item->fresh()->status);
        $this->assertTrue($this->auditExists($item, 'webdav.mirror.detached'));

        // Nach dem Trennen spiegelt der Service nichts mehr (auch nicht per Command).
        $result = (new DocumentMirrorService())->mirror($document->fresh(), $connection, new RecordingWebdavGateway());
        $this->assertSame(DocumentMirrorService::RESULT_SKIPPED, $result);
    }

    public function test_non_admin_cannot_resolve(): void {
        [, , $item] = $this->provokeConflict();
        $this->bindGateway();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->post(route('admin.webdav.conflict.overwrite', $item))
            ->assertForbidden();
    }
}
