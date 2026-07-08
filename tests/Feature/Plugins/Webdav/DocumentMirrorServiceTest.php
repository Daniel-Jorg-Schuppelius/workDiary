<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentMirrorServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Webdav;

use App\Enums\Document\{DocumentStatus, DocumentType};
use App\Models\{Document, DocumentVersion, ExternalReference, IntegrationInboxItem, User, WebdavConnection};
use App\Plugins\Webdav\Services\DocumentMirrorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue, Storage};
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\Support\RecordingWebdavGateway;
use Tests\TestCase;

/**
 * Feature 058, MVP-127: Spiegel-Kernlogik. Prüft Übergabenachweis + Idempotenz
 * (SHA-256 in ExternalReference), Überspringen bei unverändertem Inhalt, den
 * Konflikt bei externer Änderung (kein Überschreiben) und den transienten
 * Zustellfehler (wirft → Outbox-Retry).
 */
final class DocumentMirrorServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        Storage::fake('local');
        Queue::fake(); // etwaige Observer-Einreihung zustellt keine echten Jobs
    }

    /** @return array{0: Document, 1: string} */
    private function makeDocument(string $contents = 'CONTENT-A', string $name = 'pruefbericht.pdf'): array {
        $path = 'documents/2026/07/' . bin2hex(random_bytes(6)) . '.pdf';
        Storage::disk('local')->put($path, $contents);

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
            'path' => $path,
            'original_name' => $name,
            'mime' => 'application/pdf',
            'size' => strlen($contents),
            'uploaded_by_user_id' => $this->user->id,
        ]);
        $document->forceFill(['current_version_id' => $version->id])->save();

        return [$document, $path];
    }

    private function connection(): WebdavConnection {
        return WebdavConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Nextcloud',
            'base_url' => 'https://cloud.example.com/remote.php/dav/files/svc/WorkDiary',
            'username' => 'svc',
            'app_password' => 'secret',
            'default_folder' => 'Dokumente',
            'folder_map' => ['testReport' => 'Pruefberichte'],
            'active' => true,
        ]);
    }

    public function test_mirrors_new_document_with_transfer_proof(): void {
        [$document] = $this->makeDocument('CONTENT-A');
        $connection = $this->connection();
        $gateway = new RecordingWebdavGateway();

        $result = (new DocumentMirrorService())->mirror($document, $connection, $gateway);

        $this->assertSame(DocumentMirrorService::RESULT_MIRRORED, $result);
        $expectedPath = 'Pruefberichte/document-' . $document->id . '.pdf';
        $this->assertSame([$expectedPath], $gateway->puts);
        $this->assertContains('Pruefberichte', $gateway->collections);

        $ref = ExternalReference::query()
            ->where('plugin_id', DocumentMirrorService::PLUGIN_ID)
            ->where('external_type', DocumentMirrorService::EXTERNAL_TYPE)
            ->first();
        $this->assertNotNull($ref);
        $payload = $ref->payload;
        $this->assertIsArray($payload);
        $this->assertSame(hash('sha256', 'CONTENT-A'), $payload['sha256']);
        $this->assertSame($expectedPath, $payload['remote_path']);
        $connection->refresh();
        $this->assertNotNull($connection->last_mirrored_at);
    }

    public function test_unchanged_content_is_skipped_on_replay(): void {
        [$document] = $this->makeDocument('SAME');
        $connection = $this->connection();
        $service = new DocumentMirrorService();

        $service->mirror($document, $connection, new RecordingWebdavGateway());
        $gateway = new RecordingWebdavGateway();
        $result = $service->mirror($document->refresh(), $connection, $gateway);

        $this->assertSame(DocumentMirrorService::RESULT_UNCHANGED, $result);
        $this->assertSame([], $gateway->puts);
    }

    public function test_changed_content_is_mirrored_again_without_divergence(): void {
        [$document, $path] = $this->makeDocument('V1');
        $connection = $this->connection();
        $service = new DocumentMirrorService();

        $service->mirror($document, $connection, new RecordingWebdavGateway(signature: 'etag-1'));
        Storage::disk('local')->put($path, 'V2'); // Inhalt geändert, Remote unverändert (gleiche Signatur)
        $gateway = new RecordingWebdavGateway(signature: 'etag-1');
        $result = $service->mirror($document->refresh(), $connection, $gateway);

        $this->assertSame(DocumentMirrorService::RESULT_MIRRORED, $result);
        $this->assertNotEmpty($gateway->puts);
    }

    public function test_external_change_raises_visible_conflict_without_overwrite(): void {
        [$document, $path] = $this->makeDocument('V1');
        $connection = $this->connection();
        $service = new DocumentMirrorService();

        $service->mirror($document, $connection, new RecordingWebdavGateway(signature: 'etag-1'));
        Storage::disk('local')->put($path, 'V2'); // lokal geändert
        // Remote wurde fremdverändert → aktuelle Signatur weicht von der aufgezeichneten ab.
        $gateway = new RecordingWebdavGateway(signature: 'etag-EXTERN');
        $result = $service->mirror($document->refresh(), $connection, $gateway);

        $this->assertSame(DocumentMirrorService::RESULT_CONFLICT, $result);
        $this->assertSame([], $gateway->puts); // NIE stilles Überschreiben
        $this->assertSame(1, IntegrationInboxItem::query()
            ->where('plugin_id', DocumentMirrorService::PLUGIN_ID)
            ->where('case_type', IntegrationInboxItem::CASE_CONFLICT)
            ->count());
    }

    public function test_transient_delivery_failure_throws_for_retry(): void {
        [$document] = $this->makeDocument('CONTENT-A');
        $connection = $this->connection();
        $gateway = new RecordingWebdavGateway(putOk: false);

        $this->expectException(RuntimeException::class);
        (new DocumentMirrorService())->mirror($document, $connection, $gateway);
    }

    public function test_skips_when_local_file_missing(): void {
        $document = Document::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Ohne Datei',
            'document_type' => DocumentType::Other,
            'status' => DocumentStatus::Active,
            'created_by_user_id' => $this->user->id,
        ]);
        $version = DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_no' => 1,
            'disk' => 'local',
            'path' => 'documents/missing.pdf',
            'original_name' => 'missing.pdf',
            'mime' => 'application/pdf',
            'size' => 10,
            'uploaded_by_user_id' => $this->user->id,
        ]);
        $document->forceFill(['current_version_id' => $version->id])->save();

        $result = (new DocumentMirrorService())->mirror($document, $this->connection(), new RecordingWebdavGateway());

        $this->assertSame(DocumentMirrorService::RESULT_SKIPPED, $result);
    }
}
