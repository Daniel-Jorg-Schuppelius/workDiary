<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleDriveIntakeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CloudIntake;

use App\Enums\CloudIntake\CloudIntakeProvider;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Plugins\GoogleDrive\Api\GoogleDriveClient;
use App\Plugins\GoogleDrive\GoogleDrivePlugin;
use App\Plugins\Support\Intake\IntakeItem;
use App\Services\CloudIntake\StaleCheckpointException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Google-Drive-Intake (Feature 080, MVP-355): zweiphasiger Checkpoint
 * (files.list ⇒ changes.list), Pfadauflösung über die Ordnerkette,
 * Google-native Formate übersprungen, ungültiger pageToken ⇒
 * Vollabgleich-Signal.
 */
class GoogleDriveIntakeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private CloudDocumentConnection $connection;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        config(['plugins.google-drive.client_id' => 'cid', 'plugins.google-drive.client_secret' => 'sec']);

        $this->connection = CloudDocumentConnection::factory()->create([
            'organization_id' => $this->organization->id,
            'provider' => CloudIntakeProvider::Google,
            'container_id' => 'my-drive',
            'root_folder_id' => 'folder-root',
            'root_folder_path' => '/WorkDiary',
        ]);
    }

    public function test_plugin_advertises_document_intake_capability(): void {
        $plugin = new GoogleDrivePlugin();

        $this->assertContains(\App\Plugins\Contracts\PluginCapability::DocumentIntake, $plugin->capabilities());
        $this->assertInstanceOf(\App\Plugins\Contracts\DocumentIntakeSource::class, $plugin);
    }

    public function test_initial_run_freezes_start_token_lists_files_and_resolves_paths(): void {
        FakePluginHttp::fake([
            'https://www.googleapis.com/drive/v3/changes/startPageToken*' => FakePluginHttp::response(['startPageToken' => 'start-7']),
            'https://www.googleapis.com/drive/v3/files/sub-1*' => FakePluginHttp::response(['id' => 'sub-1', 'name' => 'Eingangsrechnungen', 'parents' => ['folder-root']]),
            'https://www.googleapis.com/drive/v3/files?*' => FakePluginHttp::response([
                'files' => [
                    ['id' => 'f-1', 'name' => 're-1.pdf', 'mimeType' => 'application/pdf', 'size' => '900',
                        'md5Checksum' => 'md5-1', 'headRevisionId' => 'rev-1', 'modifiedTime' => '2026-07-14T10:00:00Z',
                        'parents' => ['sub-1']],
                    ['id' => 'g-doc', 'name' => 'Notizen', 'mimeType' => 'application/vnd.google-apps.document', 'parents' => ['sub-1']],
                ],
                'nextPageToken' => 'page-2',
            ]),
        ]);

        $page = (new GoogleDriveClient($this->connection->fresh()))->changes(null);

        $this->assertCount(1, $page->items); // Google-natives Doc übersprungen
        $this->assertSame('Eingangsrechnungen/re-1.pdf', $page->items[0]->path);
        $this->assertSame('rev-1', $page->items[0]->revision);
        $this->assertTrue($page->hasMore);

        $checkpoint = json_decode($page->checkpoint, true);
        $this->assertSame('initial', $checkpoint['phase']);
        $this->assertSame('page-2', $checkpoint['pageToken']);
        $this->assertSame('start-7', $checkpoint['startPageToken']); // eingefroren
    }

    public function test_initial_completion_switches_to_delta_phase(): void {
        FakePluginHttp::fake([
            'https://www.googleapis.com/drive/v3/files?*' => FakePluginHttp::response(['files' => []]),
        ]);

        $checkpoint = (string) json_encode(['phase' => 'initial', 'pageToken' => 'page-9', 'startPageToken' => 'start-7']);
        $page = (new GoogleDriveClient($this->connection->fresh()))->changes($checkpoint);

        $this->assertFalse($page->hasMore);
        $decoded = json_decode($page->checkpoint, true);
        $this->assertSame(['phase' => 'delta', 'pageToken' => 'start-7'], $decoded);
    }

    public function test_delta_phase_maps_changes_and_tombstones(): void {
        FakePluginHttp::fake([
            'https://www.googleapis.com/drive/v3/files/sub-1*' => FakePluginHttp::response(['id' => 'sub-1', 'name' => 'Vertraege', 'parents' => ['folder-root']]),
            'https://www.googleapis.com/drive/v3/changes?*' => FakePluginHttp::response([
                'changes' => [
                    ['fileId' => 'f-2', 'file' => ['id' => 'f-2', 'name' => 'vertrag.pdf', 'mimeType' => 'application/pdf',
                        'size' => '1200', 'headRevisionId' => 'rev-9', 'parents' => ['sub-1'], 'trashed' => false]],
                    ['fileId' => 'f-gone', 'removed' => true],
                    ['fileId' => 'f-trash', 'file' => ['id' => 'f-trash', 'name' => 'alt.pdf', 'trashed' => true]],
                ],
                'newStartPageToken' => 'start-8',
            ]),
        ]);

        $checkpoint = (string) json_encode(['phase' => 'delta', 'pageToken' => 'start-7']);
        $page = (new GoogleDriveClient($this->connection->fresh()))->changes($checkpoint);

        $this->assertCount(1, $page->items);
        $this->assertSame('Vertraege/vertrag.pdf', $page->items[0]->path);
        $this->assertSame(['f-gone', 'f-trash'], $page->tombstones);
        $this->assertFalse($page->hasMore);
        $this->assertSame(['phase' => 'delta', 'pageToken' => 'start-8'], json_decode($page->checkpoint, true));
    }

    public function test_invalid_page_token_throws_stale_checkpoint_exception(): void {
        FakePluginHttp::fake([
            'https://www.googleapis.com/drive/v3/changes?*' => FakePluginHttp::response(['error' => ['message' => 'Invalid Value']], 400),
        ]);

        $this->expectException(StaleCheckpointException::class);
        (new GoogleDriveClient($this->connection->fresh()))
            ->changes((string) json_encode(['phase' => 'delta', 'pageToken' => 'kaputt']));
    }

    public function test_download_streams_media(): void {
        $fake = FakePluginHttp::fake([
            'https://www.googleapis.com/drive/v3/files/f-1*' => FakePluginHttp::response('DRIVE-INHALT'),
        ]);

        $item = new IntakeItem(itemId: 'f-1', path: 'a.pdf', name: 'a.pdf', revision: 'rev-1', size: 12);
        $stream = (new GoogleDriveClient($this->connection->fresh()))->download($item);

        $this->assertSame('DRIVE-INHALT', (string) $stream);
        $fake->assertSent(fn ($request) => str_contains((string) $request->getUri(), 'alt=media'));
    }
}
