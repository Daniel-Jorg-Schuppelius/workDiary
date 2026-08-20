<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DropboxIntakeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CloudIntake;

use App\Models\CloudIntake\CloudDocumentConnection;
use App\Plugins\Dropbox\Api\DropboxClient;
use App\Plugins\Dropbox\DropboxPlugin;
use App\Plugins\Support\Intake\IntakeItem;
use App\Services\CloudIntake\StaleCheckpointException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Dropbox-Adapter (Feature 080, MVP-353): Cursor-Delta → IntakeChangePage
 * (Items, Pfad-Tombstones, hasMore), Stale-Cursor ⇒ Vollabgleich-Signal,
 * Download-Stream, Capability-Vertrag.
 */
class DropboxIntakeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private CloudDocumentConnection $connection;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        config(['plugins.dropbox.client_id' => 'key', 'plugins.dropbox.client_secret' => 'secret']);

        $this->connection = CloudDocumentConnection::factory()->create([
            'organization_id' => $this->organization->id,
            'root_folder_path' => '/WorkDiary',
        ]);
    }

    public function test_plugin_advertises_document_intake_capability(): void {
        $plugin = new DropboxPlugin();

        $this->assertContains(\App\Plugins\Contracts\PluginCapability::DocumentIntake, $plugin->capabilities());
        $this->assertInstanceOf(\App\Plugins\Contracts\DocumentIntakeSource::class, $plugin);
    }

    public function test_health_check_reports_platform_backup_target_attention(): void {
        // Backupziele sind plattformweit — ein Re-Auth-Ziel muss den Health
        // auf degraded ziehen, auch wenn der Org-Dokumentimport gesund ist.
        $plugin = new DropboxPlugin();

        $target = \App\Models\Backup\BackupTargetConnection::factory()->create([
            'status' => \App\Enums\Backup\BackupTargetStatus::ReauthRequired,
        ]);

        $health = $plugin->healthCheck();
        $this->assertSame(\App\Plugins\PluginHealth::STATUS_DEGRADED, $health->status);
        $this->assertSame('backup_grant', $health->code);

        $target->forceFill(['status' => \App\Enums\Backup\BackupTargetStatus::Active])->save();
        $this->assertSame(\App\Plugins\PluginHealth::STATUS_OK, $plugin->healthCheck()->status);
    }

    public function test_changes_maps_files_tombstones_and_cursor(): void {
        FakePluginHttp::fake([
            'https://api.dropboxapi.com/2/files/list_folder' => FakePluginHttp::response([
                'entries' => [
                    ['.tag' => 'file', 'id' => 'id:1', 'name' => 're-1.pdf', 'path_display' => '/WorkDiary/Eingangsrechnungen/re-1.pdf', 'rev' => '015', 'size' => 1234, 'server_modified' => '2026-07-14T10:00:00Z', 'content_hash' => 'abc'],
                    ['.tag' => 'folder', 'id' => 'id:2', 'name' => 'Ordner', 'path_display' => '/WorkDiary/Ordner'],
                    ['.tag' => 'deleted', 'name' => 'alt.pdf', 'path_display' => '/WorkDiary/Alt/alt.pdf'],
                ],
                'cursor' => 'cursor-1',
                'has_more' => true,
            ]),
        ]);

        $page = (new DropboxClient($this->connection->fresh()))->changes(null);

        $this->assertCount(1, $page->items);
        $item = $page->items[0];
        $this->assertSame('id:1', $item->itemId);
        $this->assertSame('Eingangsrechnungen/re-1.pdf', $item->path); // relativ zum Stammordner
        $this->assertSame('015', $item->revision);
        $this->assertSame(['path:Alt/alt.pdf'], $page->tombstones);
        $this->assertSame('cursor-1', $page->checkpoint);
        $this->assertTrue($page->hasMore);
    }

    public function test_continue_is_used_with_existing_checkpoint(): void {
        $fake = FakePluginHttp::fake([
            'https://api.dropboxapi.com/2/files/list_folder/continue' => FakePluginHttp::response([
                'entries' => [], 'cursor' => 'cursor-2', 'has_more' => false,
            ]),
        ]);

        $page = (new DropboxClient($this->connection->fresh()))->changes('cursor-1');

        $this->assertSame('cursor-2', $page->checkpoint);
        $fake->assertSent(fn ($request) => str_contains((string) $request->getUri(), 'list_folder/continue'));
    }

    public function test_reset_cursor_throws_stale_checkpoint_exception(): void {
        FakePluginHttp::fake([
            'https://api.dropboxapi.com/2/files/list_folder/continue' => FakePluginHttp::response([
                'error_summary' => 'reset/..', 'error' => ['.tag' => 'reset'],
            ], 409),
        ]);

        $this->expectException(StaleCheckpointException::class);
        (new DropboxClient($this->connection->fresh()))->changes('cursor-alt');
    }

    public function test_download_returns_stream_with_api_arg_header(): void {
        $fake = FakePluginHttp::fake([
            'https://content.dropboxapi.com/2/files/download' => FakePluginHttp::response('PDFDATEI-INHALT'),
        ]);

        $item = new IntakeItem(itemId: 'id:1', path: 'a.pdf', name: 'a.pdf', revision: '015', size: 15);
        $stream = (new DropboxClient($this->connection->fresh()))->download($item);

        $this->assertSame('PDFDATEI-INHALT', (string) $stream);
        $fake->assertSent(function ($request): bool {
            $arg = $request->getHeaderLine('Dropbox-API-Arg');

            return $arg !== '' && str_contains($arg, 'id:1');
        });
    }
}
