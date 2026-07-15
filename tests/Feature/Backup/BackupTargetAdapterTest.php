<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupTargetAdapterTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Backup;

use App\Enums\Backup\BackupProvider;
use App\Models\Backup\BackupTargetConnection;
use App\Plugins\Contracts\{BackupTarget, PluginCapability};
use App\Plugins\Dropbox\Api\DropboxBackupClient;
use App\Plugins\Dropbox\DropboxPlugin;
use App\Plugins\GoogleDrive\Api\GoogleDriveBackupClient;
use App\Plugins\GoogleDrive\GoogleDrivePlugin;
use App\Plugins\Msgraph\Api\MsgraphBackupClient;
use App\Plugins\Msgraph\MsgraphPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Provider-Adapter der Cloud-Backupziele (Feature 017 Phase 32, MVP-363):
 * Capability-Vertrag ×3, resumable Uploads mit Remote-Größen-Verifikation,
 * idempotente Löschung, Listing nur im eigenen Bereich.
 */
class BackupTargetAdapterTest extends TestCase {
    use RefreshDatabase;

    private string $partFile;

    protected function setUp(): void {
        parent::setUp();
        config([
            'plugins.dropbox.client_id' => 'key', 'plugins.dropbox.client_secret' => 'secret',
            'plugins.msgraph.client_id' => 'key', 'plugins.msgraph.client_secret' => 'secret',
            'plugins.google-drive.client_id' => 'key', 'plugins.google-drive.client_secret' => 'secret',
        ]);
        $this->partFile = tempnam(sys_get_temp_dir(), 'wd-part-') ?: '';
        file_put_contents($this->partFile, 'encrypted-part-payload');
    }

    protected function tearDown(): void {
        @unlink($this->partFile);
        parent::tearDown();
    }

    private function connection(BackupProvider $provider): BackupTargetConnection {
        return BackupTargetConnection::factory()->active()->create(['provider' => $provider]);
    }

    public function test_all_three_plugins_announce_backup_target_capability(): void {
        foreach ([new DropboxPlugin(), new MsgraphPlugin(), new GoogleDrivePlugin()] as $plugin) {
            $this->assertContains(PluginCapability::BackupTarget, $plugin->capabilities(), $plugin::class);
            $this->assertInstanceOf(BackupTarget::class, $plugin, $plugin::class);
        }
    }

    // ── Dropbox ─────────────────────────────────────────────────────────

    public function test_dropbox_upload_runs_session_and_verifies_size(): void {
        $size = filesize($this->partFile);
        $fake = FakePluginHttp::fake([
            'https://content.dropboxapi.com/2/files/upload_session/start' => FakePluginHttp::response(['session_id' => 'sess-1']),
            'https://content.dropboxapi.com/2/files/upload_session/append_v2' => FakePluginHttp::response([]),
            'https://content.dropboxapi.com/2/files/upload_session/finish' => FakePluginHttp::response([
                'id' => 'id:99', 'path_display' => '/wd-abc/uuid-1/part-1', 'size' => $size,
            ]),
        ]);

        $ref = (new DropboxBackupClient($this->connection(BackupProvider::Dropbox)))
            ->uploadPart($this->partFile, 'wd-abc/uuid-1/part-1');

        $this->assertSame('/wd-abc/uuid-1/part-1', $ref);
        $fake->assertSent(fn (RequestInterface $r) => str_contains((string) $r->getUri(), 'upload_session/finish'));
    }

    public function test_dropbox_upload_size_mismatch_throws(): void {
        FakePluginHttp::fake([
            'https://content.dropboxapi.com/2/files/upload_session/start' => FakePluginHttp::response(['session_id' => 'sess-1']),
            'https://content.dropboxapi.com/2/files/upload_session/append_v2' => FakePluginHttp::response([]),
            'https://content.dropboxapi.com/2/files/upload_session/finish' => FakePluginHttp::response(['id' => 'id:99', 'size' => 5]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/unvollständig/');
        (new DropboxBackupClient($this->connection(BackupProvider::Dropbox)))
            ->uploadPart($this->partFile, 'wd-abc/uuid-1/part-1');
    }

    public function test_dropbox_delete_is_idempotent_on_not_found(): void {
        FakePluginHttp::fake([
            'https://api.dropboxapi.com/2/files/delete_v2' => FakePluginHttp::response(['error_summary' => 'path_lookup/not_found/'], 409),
        ]);

        $this->assertTrue(
            (new DropboxBackupClient($this->connection(BackupProvider::Dropbox)))->delete('/wd-abc/uuid-1'),
        );
    }

    public function test_dropbox_list_returns_empty_for_missing_prefix(): void {
        FakePluginHttp::fake([
            'https://api.dropboxapi.com/2/files/list_folder' => FakePluginHttp::response(['error_summary' => 'path/not_found/'], 409),
        ]);

        $this->assertSame(
            [],
            (new DropboxBackupClient($this->connection(BackupProvider::Dropbox)))->listObjects('wd-abc'),
        );
    }

    // ── Microsoft Graph ─────────────────────────────────────────────────

    public function test_graph_upload_uses_session_url_without_auth_header(): void {
        $size = filesize($this->partFile);
        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/drive/root:/wd-abc/uuid-1/part-1:/createUploadSession' => FakePluginHttp::response(['uploadUrl' => 'https://upload.example/session-1']),
            'https://upload.example/session-1' => FakePluginHttp::response(['id' => 'item-9', 'size' => $size], 201),
        ]);

        $ref = (new MsgraphBackupClient($this->connection(BackupProvider::Microsoft)))
            ->uploadPart($this->partFile, 'wd-abc/uuid-1/part-1');

        $this->assertSame('item-9', $ref);
        $fake->assertSent(function (RequestInterface $r): bool {
            return str_contains((string) $r->getUri(), 'upload.example/session-1')
                && !$r->hasHeader('Authorization')
                && str_starts_with((string) $r->getHeaderLine('Content-Range'), 'bytes 0-');
        });
    }

    public function test_graph_ensure_folder_creates_missing_segments(): void {
        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/drive/root:/wd-abc?*' => FakePluginHttp::response(['error' => ['code' => 'itemNotFound']], 404),
            'https://graph.microsoft.com/v1.0/me/drive/root/children' => FakePluginHttp::response(['id' => 'folder-1'], 201),
            'https://graph.microsoft.com/v1.0/me/drive/root:/wd-abc/uuid-1?*' => FakePluginHttp::response(['error' => ['code' => 'itemNotFound']], 404),
            'https://graph.microsoft.com/v1.0/me/drive/root:/wd-abc:/children' => FakePluginHttp::response(['id' => 'folder-2'], 201),
        ]);

        $ref = (new MsgraphBackupClient($this->connection(BackupProvider::Microsoft)))->ensureFolder('wd-abc/uuid-1');

        $this->assertSame('folder-2', $ref);
        $fake->assertSent(fn (RequestInterface $r) => str_contains((string) $r->getUri(), 'root/children'));
    }

    public function test_graph_delete_is_idempotent_on_404(): void {
        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/drive/items/*' => FakePluginHttp::response(['error' => ['code' => 'itemNotFound']], 404),
        ]);

        $this->assertTrue(
            (new MsgraphBackupClient($this->connection(BackupProvider::Microsoft)))->delete('item-9'),
        );
    }

    // ── Google Drive ────────────────────────────────────────────────────

    public function test_drive_upload_uses_resumable_session_and_verifies_size(): void {
        $size = filesize($this->partFile);
        FakePluginHttp::fake([
            // Ordner-Auflösung: wd-abc + uuid-1 existieren bereits.
            'https://www.googleapis.com/drive/v3/files?*' => FakePluginHttp::response(['files' => [['id' => 'folder-1']]]),
            'https://www.googleapis.com/drive/v3/files' => FakePluginHttp::response(['files' => [['id' => 'folder-1']]]),
            'https://www.googleapis.com/upload/drive/v3/files?*' => FakePluginHttp::response(null, 200, ['Location' => 'https://upload.example/drive-session']),
            'https://upload.example/drive-session' => FakePluginHttp::response(['id' => 'file-7', 'size' => (string) $size]),
        ]);

        $ref = (new GoogleDriveBackupClient($this->connection(BackupProvider::Google)))
            ->uploadPart($this->partFile, 'wd-abc/uuid-1/part-1');

        $this->assertSame('file-7', $ref);
    }

    public function test_drive_list_returns_empty_when_folder_missing(): void {
        FakePluginHttp::fake([
            'https://www.googleapis.com/drive/v3/files?*' => FakePluginHttp::response(['files' => []]),
            'https://www.googleapis.com/drive/v3/files' => FakePluginHttp::response(['files' => []]),
        ]);

        $this->assertSame(
            [],
            (new GoogleDriveBackupClient($this->connection(BackupProvider::Google)))->listObjects('wd-abc/uuid-1'),
        );
    }

    public function test_drive_delete_is_idempotent_on_404(): void {
        FakePluginHttp::fake([
            'https://www.googleapis.com/drive/v3/files/*' => FakePluginHttp::response(['error' => ['code' => 404]], 404),
        ]);

        $this->assertTrue(
            (new GoogleDriveBackupClient($this->connection(BackupProvider::Google)))->delete('file-7'),
        );
    }
}
