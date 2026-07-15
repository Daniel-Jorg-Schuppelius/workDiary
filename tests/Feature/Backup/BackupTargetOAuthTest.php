<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupTargetOAuthTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Backup;

use App\Enums\Backup\{BackupProvider, BackupTargetStatus};
use App\Models\Backup\BackupTargetConnection;
use App\Models\User;
use App\Plugins\Dropbox\Api\DropboxBackupOAuth;
use GuzzleHttp\{Client as GuzzleClient, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Systemweiter OAuth-Flow der Backupziele (Feature 017 Phase 32, MVP-363):
 * Plattform-Gating (Org-Admin 403), state nutzergebunden + einmalig,
 * PKCE + offline-Access, Kontobestätigung + Quota + Pseudonym-Stammordner,
 * Scope-Lücke ⇒ blocked, Secret-Redaction.
 */
class BackupTargetOAuthTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $platformAdmin;

    private User $orgAdmin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->platformAdmin = User::factory()->platformAdmin()->create(['organization_id' => $this->organization->id]);
        $this->orgAdmin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        config(['plugins.dropbox.client_id' => 'key', 'plugins.dropbox.client_secret' => 'secret']);
    }

    /** @param array<string, mixed> $tokenResponse */
    private function fakeTokenEndpoint(array $tokenResponse): void {
        $mock = new MockHandler([
            new Psr7Response(200, ['Content-Type' => 'application/json'], (string) json_encode($tokenResponse)),
        ]);
        app()->instance(DropboxBackupOAuth::class, new DropboxBackupOAuth(new GuzzleClient(['handler' => HandlerStack::create($mock)])));
    }

    /** Erfolgs-Stubs für Konto + Quota + Stammordner nach dem Token-Tausch. */
    private function fakeProviderApis(): void {
        FakePluginHttp::fake([
            'https://api.dropboxapi.com/2/users/get_current_account' => FakePluginHttp::response([
                'account_id' => 'dbid:backup-1', 'email' => 'backup@example.org', 'name' => ['display_name' => 'Backup Konto'],
            ]),
            'https://api.dropboxapi.com/2/users/get_space_usage' => FakePluginHttp::response([
                'used' => 1000, 'allocation' => ['allocated' => 5000],
            ]),
            'https://api.dropboxapi.com/2/files/create_folder_v2' => FakePluginHttp::response([
                'metadata' => ['id' => 'id:root', 'path_display' => '/wd-abc'],
            ]),
        ]);
    }

    private function startFlowAndGetState(): string {
        $response = $this->actingAs($this->platformAdmin)->post(route('admin.backup-targets.dropbox.oauth.start'));
        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame('offline', $query['token_access_type'] ?? null);
        $this->assertStringContainsString('files.content.write', is_string($query['scope'] ?? null) ? $query['scope'] : '');
        $this->assertSame('S256', $query['code_challenge_method'] ?? null);
        $state = is_string($query['state'] ?? null) ? $query['state'] : '';
        $this->assertNotSame('', $state);

        return $state;
    }

    public function test_org_admin_cannot_start_or_view(): void {
        $this->actingAs($this->orgAdmin)->post(route('admin.backup-targets.dropbox.oauth.start'))->assertForbidden();
        $this->actingAs($this->orgAdmin)->get(route('admin.backup-targets.index'))->assertForbidden();
    }

    public function test_platform_admin_sees_overview_with_recovery_warning(): void {
        config(['backup_targets.master_key' => base64_encode(str_repeat("\x42", 32)), 'backup_targets.recovery_public_key' => null]);

        $this->actingAs($this->platformAdmin)
            ->get(route('admin.backup-targets.index'))
            ->assertOk()
            ->assertSee(__('backup_targets.recovery_key_missing'));
    }

    public function test_callback_confirms_account_quota_and_activates(): void {
        $this->fakeTokenEndpoint([
            'access_token' => 'at-1', 'refresh_token' => 'rt-1', 'expires_in' => 14400,
            'scope' => 'account_info.read files.metadata.read files.content.read files.content.write',
        ]);
        $this->fakeProviderApis();
        $state = $this->startFlowAndGetState();

        $this->actingAs($this->platformAdmin)
            ->get(route('admin.backup-targets.dropbox.oauth.callback', ['state' => $state, 'code' => 'auth-code']))
            ->assertRedirect(route('admin.backup-targets.index'));

        $connection = BackupTargetConnection::query()->sole();
        $this->assertSame(BackupProvider::Dropbox, $connection->provider);
        $this->assertSame(BackupTargetStatus::Active, $connection->status);
        $this->assertSame('dbid:backup-1', $connection->external_account_id);
        $this->assertSame(5000, $connection->quota_total);
        $this->assertSame(1000, $connection->quota_used);
        $this->assertNotNull($connection->root_folder_ref);
        // Tokens verschlüsselt at-rest + nie in Serialisierungen.
        $this->assertSame('at-1', $connection->access_token);
        $this->assertArrayNotHasKey('access_token', $connection->toArray());
        $raw = \Illuminate\Support\Facades\DB::table('backup_target_connections')->value('access_token');
        $this->assertIsString($raw);
        $this->assertStringNotContainsString('at-1', $raw);
    }

    public function test_missing_write_scope_blocks_target(): void {
        $this->fakeTokenEndpoint([
            'access_token' => 'at-1', 'refresh_token' => 'rt-1', 'expires_in' => 14400,
            'scope' => 'account_info.read files.metadata.read files.content.read',
        ]);
        $state = $this->startFlowAndGetState();

        $this->actingAs($this->platformAdmin)
            ->get(route('admin.backup-targets.dropbox.oauth.callback', ['state' => $state, 'code' => 'auth-code']));

        $this->assertSame(BackupTargetStatus::Blocked, BackupTargetConnection::query()->sole()->status);
    }

    public function test_state_is_single_use_and_user_bound(): void {
        $this->fakeTokenEndpoint([
            'access_token' => 'at-1', 'refresh_token' => 'rt-1', 'expires_in' => 14400,
            'scope' => 'account_info.read files.metadata.read files.content.read files.content.write',
        ]);
        $this->fakeProviderApis();
        $state = $this->startFlowAndGetState();

        // Anderer Plattform-Admin darf den state NICHT einlösen (nutzergebunden).
        $other = User::factory()->platformAdmin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($other)
            ->get(route('admin.backup-targets.dropbox.oauth.callback', ['state' => $state, 'code' => 'auth-code']))
            ->assertSessionHas('error');
        $this->assertSame(0, BackupTargetConnection::query()->count());

        // state ist nach dem Fehlversuch verbraucht (Cache::pull) — Replay scheitert auch beim Eigentümer.
        $this->actingAs($this->platformAdmin)
            ->get(route('admin.backup-targets.dropbox.oauth.callback', ['state' => $state, 'code' => 'auth-code']))
            ->assertSessionHas('error');
        $this->assertSame(0, BackupTargetConnection::query()->count());
    }

    public function test_disconnect_keeps_generation_evidence(): void {
        $connection = BackupTargetConnection::factory()->active()->create();
        $generation = \App\Models\Backup\BackupGeneration::factory()->committed()->create(['connection_id' => $connection->id]);

        $this->actingAs($this->platformAdmin)
            ->delete(route('admin.backup-targets.disconnect', $connection))
            ->assertRedirect(route('admin.backup-targets.index'));

        $this->assertDatabaseMissing('backup_target_connections', ['id' => $connection->id]);
        $this->assertDatabaseHas('backup_generations', ['id' => $generation->id, 'connection_id' => null]);
    }
}
