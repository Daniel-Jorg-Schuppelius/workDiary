<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpdateCheckTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Operations;

use App\Models\{ComponentUpdate, User};
use App\Services\Updates\UpdateCheckService;
use App\Settings\SettingScope;
use App\Support\Setting;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, Http};
use Tests\TestCase;

class UpdateCheckTest extends TestCase {
    use RefreshDatabase;

    /** @var non-empty-string */
    private string $secretKey;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        User::factory()->admin()->create();

        $pair = sodium_crypto_sign_keypair();
        $this->secretKey = sodium_crypto_sign_secretkey($pair);
        config([
            'license.public_key' => base64_encode(sodium_crypto_sign_publickey($pair)),
            'app.version' => '1.2.0',
        ]);
    }

    /**
     * @param list<array<string, mixed>> $components
     * @return array<string, string>
     */
    private function signedDocument(array $components, ?string $tamper = null): array {
        $payload = json_encode(['generated_at' => now()->toIso8601String(), 'components' => $components], JSON_THROW_ON_ERROR);
        $signature = base64_encode(sodium_crypto_sign_detached($payload, $this->secretKey));
        if ($tamper !== null) {
            $payload = str_replace('1.3.0', $tamper, $payload);
        }

        return ['payload' => $payload, 'signature' => $signature, 'algorithm' => 'ed25519'];
    }

    /** @return array<string, mixed> */
    private function appComponent(string $version, string $classification = 'normal'): array {
        return [
            'key' => 'app',
            'type' => 'app',
            'channel' => 'stable',
            'version' => $version,
            'classification' => $classification,
            'min_app_version' => null,
            'max_app_version' => null,
            'changelog_url' => 'https://example.test/changelog',
            'requires' => ['backup' => true, 'migrations' => true],
        ];
    }

    public function test_valid_feed_creates_update_row_and_info_notification(): void {
        $service = app(UpdateCheckService::class);

        $open = $service->apply($this->signedDocument([$this->appComponent('1.3.0')]), 'offline_import');

        $this->assertSame(1, $open);
        $this->assertDatabaseHas('component_updates', [
            'component_key' => 'app',
            'installed_version' => '1.2.0',
            'available_version' => '1.3.0',
            'classification' => 'normal',
        ]);
        // Routine-Update: Meldung, aber keine Aufgabe.
        $this->assertDatabaseCount('operations_tasks', 0);
    }

    public function test_tampered_document_is_rejected(): void {
        $this->expectException(\RuntimeException::class);

        app(UpdateCheckService::class)->apply(
            $this->signedDocument([$this->appComponent('1.3.0')], tamper: '9.9.9'),
            'offline_import',
        );
    }

    public function test_security_update_creates_task_even_when_muted(): void {
        $service = app(UpdateCheckService::class);
        $service->apply($this->signedDocument([$this->appComponent('1.3.0')]), 'offline_import');

        // Admin schaltet stumm — dann kommt eine Security-Version.
        ComponentUpdate::query()->firstOrFail()->update(['acknowledged_at' => now()]);
        $service->apply($this->signedDocument([$this->appComponent('1.3.1', 'security')]), 'offline_import');

        $update = ComponentUpdate::query()->firstOrFail();
        $this->assertSame('1.3.1', $update->available_version);
        // Neue Version hebt Stummschaltung auf.
        $this->assertNull($update->acknowledged_at);
        $this->assertDatabaseHas('operations_tasks', [
            'dedupe_key' => 'update_security:app:app',
            'status' => 'open',
        ]);
    }

    public function test_installed_update_removes_row_and_resolves_task(): void {
        $service = app(UpdateCheckService::class);
        $service->apply($this->signedDocument([$this->appComponent('1.3.0', 'security')]), 'offline_import');
        $this->assertDatabaseCount('component_updates', 1);

        // Update wurde eingespielt.
        config(['app.version' => '1.3.0']);
        $service->apply($this->signedDocument([$this->appComponent('1.3.0', 'security')]), 'offline_import');

        $this->assertDatabaseCount('component_updates', 0);
        $this->assertDatabaseHas('operations_tasks', [
            'dedupe_key' => 'update_security:app:app',
            'status' => 'resolved',
        ]);
    }

    public function test_incompatible_update_is_flagged(): void {
        $component = $this->appComponent('2.0.0');
        $component['min_app_version'] = '1.9.0';

        app(UpdateCheckService::class)->apply($this->signedDocument([$component]), 'offline_import');

        $this->assertDatabaseHas('component_updates', [
            'component_key' => 'app',
            'compatible' => false,
        ]);
    }

    public function test_disabled_mode_never_calls_remote(): void {
        Http::fake();
        Setting::set('updates.check_mode', 'disabled', SettingScope::System);

        $exit = Artisan::call('updates:check');

        $this->assertSame(0, $exit);
        Http::assertNothingSent();
    }

    public function test_manual_mode_skips_scheduled_run_but_allows_force(): void {
        Setting::set('updates.feed_url', 'https://updates.example.test/feed.json', SettingScope::System);
        Http::fake([
            'updates.example.test/*' => Http::response($this->signedDocument([$this->appComponent('1.3.0')])),
        ]);

        // Scheduled (ohne --force): kein Abruf im manual-Modus (Default).
        Artisan::call('updates:check');
        Http::assertNothingSent();

        // Manueller Lauf.
        $exit = Artisan::call('updates:check', ['--force' => true]);
        $this->assertSame(0, $exit);
        $this->assertDatabaseHas('component_updates', ['available_version' => '1.3.0']);
    }

    public function test_admin_ui_shows_updates_and_offline_import_route_works(): void {
        $admin = User::factory()->admin()->create();
        app(UpdateCheckService::class)->apply($this->signedDocument([$this->appComponent('1.3.0')]), 'offline_import');

        $this->actingAs($admin)
            ->get(route('admin.components.index'))
            ->assertOk()
            ->assertSee(__('updates.title.section'))
            ->assertSee('1.3.0');
    }
}
