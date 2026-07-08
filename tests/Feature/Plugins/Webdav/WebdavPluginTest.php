<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebdavPluginTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Webdav;

use App\Enums\Document\{DocumentStatus, DocumentType};
use App\Jobs\Integration\IntegrationOutboxDeliveryJob;
use App\Models\{Document, DocumentVersion, ExternalReference, IntegrationOutboxEntry, User, WebdavConnection};
use App\Plugins\{PluginDiscovery, PluginHealth};
use App\Plugins\Webdav\Contracts\{WebdavGateway, WebdavGatewayFactory};
use App\Plugins\Webdav\Services\{DocumentMirrorService, WebdavOutboxDispatcher};
use App\Plugins\Webdav\WebdavPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue, Storage};
use Tests\Concerns\WithOrganization;
use Tests\Support\RecordingWebdavGateway;
use Tests\TestCase;

/**
 * Feature 058, MVP-127: Plugin-Verdrahtung. Auto-Discovery, per-Org-Health über
 * die (gefälschte) Gateway-Factory und der Freigabe→Outbox-Trigger des
 * Document-Observers (idempotent über den Idempotenzschlüssel).
 */
final class WebdavPluginTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        Queue::fake();
    }

    private function bindGateway(bool $pingOk = true): void {
        $gateway = new RecordingWebdavGateway(pingOk: $pingOk);

        $this->app->instance(WebdavGatewayFactory::class, new class($gateway) implements WebdavGatewayFactory {
            public function __construct(private WebdavGateway $gateway) {}

            public function for(WebdavConnection $connection): WebdavGateway {
                return $this->gateway;
            }
        });
    }

    private function connection(bool $active = true): WebdavConnection {
        return WebdavConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Nextcloud',
            'base_url' => 'https://cloud.example.com/remote.php/dav/files/svc/WorkDiary',
            'username' => 'svc',
            'app_password' => 'secret',
            'default_folder' => 'Dokumente',
            'active' => $active,
        ]);
    }

    private function activeDocument(): Document {
        $document = Document::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Prüfbericht',
            'document_type' => DocumentType::TestReport,
            'status' => DocumentStatus::Active,
            'created_by_user_id' => $this->user->id,
        ]);
        $version = DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_no' => 1,
            'disk' => 'local',
            'path' => 'documents/x.pdf',
            'original_name' => 'x.pdf',
            'mime' => 'application/pdf',
            'size' => 3,
            'uploaded_by_user_id' => $this->user->id,
        ]);
        $document->forceFill(['current_version_id' => $version->id])->save();

        return $document;
    }

    public function test_is_discovered_without_capability(): void {
        $this->assertContains(WebdavPlugin::class, PluginDiscovery::classes());

        $plugin = new WebdavPlugin();
        $this->assertSame([], $plugin->capabilities());
        $this->assertTrue($plugin->isPerOrganization());
    }

    public function test_releasing_document_enqueues_mirror(): void {
        $this->connection();
        $document = $this->activeDocument();

        $entry = IntegrationOutboxEntry::query()
            ->where('plugin_id', DocumentMirrorService::PLUGIN_ID)
            ->where('operation', WebdavOutboxDispatcher::OP_MIRROR)
            ->first();
        $this->assertNotNull($entry);
        $this->assertSame('mirror:doc-' . $document->id . ':v' . $document->current_version_id, $entry->idempotency_key);
        Queue::assertPushed(IntegrationOutboxDeliveryJob::class);
    }

    public function test_enqueue_is_idempotent_per_version(): void {
        $this->connection();
        $document = $this->activeDocument();

        // Reine Metadaten-Änderung (gleiche Version) → kein zweiter Eintrag.
        $document->forceFill(['title' => 'Prüfbericht (aktualisiert)'])->save();

        $this->assertSame(1, IntegrationOutboxEntry::query()
            ->where('plugin_id', DocumentMirrorService::PLUGIN_ID)
            ->count());
    }

    public function test_draft_document_is_not_enqueued(): void {
        $this->connection();
        Document::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Entwurf',
            'document_type' => DocumentType::Other,
            'status' => DocumentStatus::Draft,
            'created_by_user_id' => $this->user->id,
        ]);

        $this->assertSame(0, IntegrationOutboxEntry::query()->count());
    }

    public function test_no_enqueue_without_active_connection(): void {
        $this->activeDocument(); // keine WebDAV-Ablage vorhanden

        $this->assertSame(0, IntegrationOutboxEntry::query()->count());
    }

    public function test_health_reflects_ping(): void {
        $this->bindGateway(pingOk: true);
        $this->connection();
        $this->assertTrue((new WebdavPlugin())->healthCheck()->isOk());

        $this->bindGateway(pingOk: false);
        $this->assertTrue((new WebdavPlugin())->healthCheck()->isFailing());
    }

    public function test_health_degraded_without_connection(): void {
        $this->bindGateway();

        $this->assertSame(PluginHealth::STATUS_DEGRADED, (new WebdavPlugin())->healthCheck()->status);
    }

    public function test_dispatcher_mirrors_via_service(): void {
        Storage::fake('local');
        Storage::disk('local')->put('documents/x.pdf', 'CONTENT');
        $this->bindGateway();
        $this->connection();
        $this->activeDocument(); // Version zeigt auf documents/x.pdf

        $entry = IntegrationOutboxEntry::query()
            ->where('plugin_id', DocumentMirrorService::PLUGIN_ID)
            ->firstOrFail();

        $confirmed = (new WebdavOutboxDispatcher())->dispatch($entry);

        $this->assertTrue($confirmed);
        $this->assertSame(1, ExternalReference::query()
            ->where('plugin_id', DocumentMirrorService::PLUGIN_ID)
            ->where('external_type', DocumentMirrorService::EXTERNAL_TYPE)
            ->count());
    }
}
