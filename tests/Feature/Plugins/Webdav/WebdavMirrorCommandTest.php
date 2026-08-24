<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebdavMirrorCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Webdav;

use App\Enums\Document\{DocumentStatus, DocumentType};
use App\Models\{Document, DocumentVersion, IntegrationOutboxEntry, User, WebdavConnection};
use App\Plugins\Webdav\WebdavPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue, Storage};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Vollscan 2026-08-23, D7: webdav:mirror reiht freigegebene Dokumente
 * idempotent in die Integrations-Outbox ein — kein HTTP im Spiel, der
 * eigentliche Transfer läuft erst im Outbox-Dispatcher.
 */
class WebdavMirrorCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        Storage::fake('local');
        Queue::fake(); // Outbox-Zustellung bleibt liegen — hier zählt nur das Einreihen.
    }

    private function makeActiveDocument(): Document {
        $path = 'documents/2026/08/' . bin2hex(random_bytes(6)) . '.pdf';
        Storage::disk('local')->put($path, 'INHALT');

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
            'original_name' => 'pruefbericht.pdf',
            'mime' => 'application/pdf',
            'size' => 6,
            'uploaded_by_user_id' => $this->user->id,
        ]);
        $document->forceFill(['current_version_id' => $version->id])->save();

        return $document;
    }

    private function connection(): WebdavConnection {
        return WebdavConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Nextcloud',
            'base_url' => 'https://cloud.example.com/remote.php/dav/files/svc/WorkDiary',
            'username' => 'svc',
            'app_password' => 'secret',
            'default_folder' => 'Dokumente',
            'active' => true,
        ]);
    }

    public function test_queues_released_documents_into_the_outbox(): void {
        $document = $this->makeActiveDocument();
        $this->connection();

        $this->artisan('webdav:mirror', ['--organization' => (string) $this->organization->id])
            ->expectsOutputToContain('1 Dokumente eingereiht.')
            ->assertExitCode(0);

        $entry = IntegrationOutboxEntry::query()
            ->where('organization_id', $this->organization->id)
            ->where('plugin_id', WebdavPlugin::ID)
            ->first();
        $this->assertNotNull($entry);
        $this->assertSame('mirror:doc-' . $document->id . ':v' . $document->current_version_id, $entry->idempotency_key);
    }

    public function test_rerun_is_idempotent_over_the_dedupe_key(): void {
        $this->makeActiveDocument();
        $this->connection();

        $this->artisan('webdav:mirror', ['--organization' => (string) $this->organization->id])->assertExitCode(0);
        $this->artisan('webdav:mirror', ['--organization' => (string) $this->organization->id])->assertExitCode(0);

        $this->assertSame(1, IntegrationOutboxEntry::query()
            ->where('organization_id', $this->organization->id)
            ->count(), 'Wiederholter Voll-Spiegellauf darf keine Duplikate einreihen.');
    }

    public function test_without_active_connection_nothing_is_queued(): void {
        $this->makeActiveDocument();

        $this->artisan('webdav:mirror', ['--organization' => (string) $this->organization->id])
            ->assertExitCode(0);

        $this->assertSame(0, IntegrationOutboxEntry::query()->count());
    }
}
