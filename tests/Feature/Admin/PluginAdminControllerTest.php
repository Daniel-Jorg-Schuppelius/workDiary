<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginAdminControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Admin;

use App\Models\{PluginError, PluginSetting, PluginState, User};
use App\Plugins\Contracts\{Plugin, PluginCapability};
use App\Plugins\{PluginDefaults, PluginHealth, PluginManager};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class PluginAdminControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $manager = new PluginManager;
        $manager->register(new AdminTestPlugin);
        $manager->register(new AdminFailingPlugin);
        $manager->register(new AdminBarePlugin);
        $manager->register(new AdminPanelPlugin);
        $this->app->instance(PluginManager::class, $manager);
    }

    private function enablePlugin(string $pluginId): PluginSetting {
        return PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => $pluginId,
            'enabled' => true,
            'settings' => [],
        ]);
    }

    public function test_index_renders_with_plugins_and_states(): void {
        $this->actingAs($this->admin)
            ->get(route('admin.plugins.index'))
            ->assertOk()
            ->assertSee('AdminTest');
    }

    public function test_toggle_switches_setting_enabled(): void {
        $this->actingAs($this->admin)
            ->post(route('admin.plugins.toggle', 'admintest'))
            ->assertRedirect();

        $row = PluginSetting::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->where('plugin_id', 'admintest')
            ->firstOrFail();
        $this->assertTrue((bool) $row->enabled);

        $this->actingAs($this->admin)
            ->post(route('admin.plugins.toggle', 'admintest'));
        $row->refresh();
        $this->assertFalse((bool) $row->enabled);
    }

    /**
     * MVP-327 (Datenschutzseite-Konzept §4): Aktivieren/Deaktivieren einer
     * Integration schreibt das Audit-Event `integration.changed` mit
     * `{ integration, from, to }` über den Hash-Ketten-Schreibweg.
     */
    public function test_toggle_writes_integration_changed_audit_event(): void {
        $this->actingAs($this->admin)
            ->post(route('admin.plugins.toggle', 'admintest'))
            ->assertRedirect();

        /** @var \App\Models\AuditLog|null $log */
        $log = \App\Models\AuditLog::query()
            ->withoutGlobalScopes()
            ->where('event', 'integration.changed')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame((int) $this->organization->id, (int) $log->organization_id);
        $this->assertSame((int) $this->admin->id, (int) $log->user_id);

        $changes = (array) $log->getAttribute('changes');
        $this->assertSame('admintest', $changes['integration'] ?? null);
        $this->assertSame('disabled', $changes['from'] ?? null);
        $this->assertSame('enabled', $changes['to'] ?? null);
    }

    public function test_health_check_endpoint_returns_status_and_persists(): void {
        $this->enablePlugin('admintest');

        $this->actingAs($this->admin)
            ->postJson(route('admin.plugins.health-check', 'admintest'))
            ->assertOk()
            ->assertJson(['status' => PluginHealth::STATUS_OK]);

        $state = PluginState::query()->where('plugin_id', 'admintest')->firstOrFail();
        $this->assertSame(PluginHealth::STATUS_OK, $state->last_health_status);
    }

    /** W0e: Deaktivierte Plugins werden nicht geprüft — kein Pseudo-Fehler-Check. */
    public function test_health_check_on_disabled_plugin_is_rejected(): void {
        $this->actingAs($this->admin)
            ->postJson(route('admin.plugins.health-check', 'admintest'))
            ->assertStatus(422)
            ->assertJson(['status' => 'disabled']);

        $this->assertSame(0, PluginState::query()->where('plugin_id', 'admintest')->count());
    }

    /**
     * E-1: Manuelle Admin-Checks landen als Phase `manual` in der Inbox, zählen
     * aber nie für den Auto-Disable — wiederholte Klicks legen kein Plugin still.
     */
    public function test_failing_manual_check_records_manual_phase_without_counting(): void {
        $this->enablePlugin('adminbroken');

        $this->actingAs($this->admin)
            ->postJson(route('admin.plugins.health-check', 'adminbroken'))
            ->assertOk()
            ->assertJson(['status' => PluginHealth::STATUS_FAILING]);

        $error = PluginError::query()->where('plugin_id', 'adminbroken')->firstOrFail();
        $this->assertSame(PluginError::PHASE_MANUAL, $error->phase);

        $state = PluginState::query()->where('plugin_id', 'adminbroken')->firstOrFail();
        $this->assertSame(PluginHealth::STATUS_FAILING, $state->last_health_status);
        $this->assertSame(0, (int) $state->failure_count);
        $this->assertNull($state->disabled_reason);
    }

    /** W0c: Deaktivieren invalidiert den persistierten Health-Zustand. */
    public function test_toggle_off_clears_health_state(): void {
        $this->enablePlugin('admintest');
        $this->actingAs($this->admin)
            ->postJson(route('admin.plugins.health-check', 'admintest'))
            ->assertOk();
        $this->assertNotNull(PluginState::query()->where('plugin_id', 'admintest')->firstOrFail()->last_health_status);

        $this->actingAs($this->admin)
            ->post(route('admin.plugins.toggle', 'admintest'))
            ->assertRedirect();

        $state = PluginState::query()->where('plugin_id', 'admintest')->firstOrFail();
        $this->assertNull($state->last_health_status);
        $this->assertNull($state->last_health_message);
        $this->assertNull($state->last_health_check_at);
    }

    public function test_reset_errors_clears_disabled_reason(): void {
        // Org-Zeile: der UI-Reset wirkt seit W1b nur auf die eigene Organisation
        // (globaler Kill-Switch → CLI plugin:reset, s. PluginErrorInboxScopeTest).
        PluginState::create([
            'plugin_id' => 'admintest',
            'organization_id' => $this->organization->id,
            'failure_count' => 9,
            'disabled_reason' => 'auto',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.plugins.reset-errors', 'admintest'))
            ->assertRedirect();

        $state = PluginState::query()->where('plugin_id', 'admintest')->firstOrFail();
        $this->assertNull($state->disabled_reason);
        $this->assertSame(0, (int) $state->failure_count);
    }

    /** W1d/B5: Settings-Änderung schreibt ein Audit-Event (nur Feldnamen, nie Werte). */
    public function test_settings_change_writes_audit_event(): void {
        $this->enablePlugin('admintest');

        $this->actingAs($this->admin)
            ->put(route('admin.plugins.update', 'admintest'), [
                'enabled' => 1,
                'settings' => ['api_key' => 'geheim-123'],
            ])
            ->assertRedirect();

        $log = \App\Models\AuditLog::query()
            ->withoutGlobalScopes()
            ->where('event', 'integration.settings_changed')
            ->firstOrFail();
        $changes = (array) $log->getAttribute('changes');
        $this->assertSame(['api_key'], $changes['fields'] ?? null);
        $this->assertStringNotContainsString('geheim-123', json_encode($changes) ?: '');
    }

    /** W1d/B6: leeres Secret-Feld lässt den gespeicherten Wert unangetastet. */
    public function test_empty_secret_input_keeps_existing_value(): void {
        $row = $this->enablePlugin('admintest');
        $row->settings = ['api_key' => 'bestehender-key'];
        $row->save();

        $this->actingAs($this->admin)
            ->put(route('admin.plugins.update', 'admintest'), [
                'enabled' => 1,
                'settings' => ['api_key' => ''],
            ])
            ->assertRedirect();

        $row->refresh();
        $this->assertSame('bestehender-key', $row->settings['api_key'] ?? null);
    }

    public function test_secret_reset_removes_stored_value(): void {
        $row = $this->enablePlugin('admintest');
        $row->settings = ['api_key' => 'bestehender-key'];
        $row->save();

        $this->actingAs($this->admin)
            ->put(route('admin.plugins.update', 'admintest'), [
                'enabled' => 1,
                'settings' => ['api_key' => ''],
                'settings_reset' => ['api_key' => '1'],
            ])
            ->assertRedirect();

        $row->refresh();
        // Org-Wert entfernt → Config/ENV-Fallback greift wieder.
        $this->assertArrayNotHasKey('api_key', $row->settings ?? []);
    }

    public function test_secret_reset_ignored_when_new_value_entered(): void {
        $row = $this->enablePlugin('admintest');
        $row->settings = ['api_key' => 'alt'];
        $row->save();

        // Neuer Wert UND Reset-Haken: der eingegebene Wert gewinnt.
        $this->actingAs($this->admin)
            ->put(route('admin.plugins.update', 'admintest'), [
                'enabled' => 1,
                'settings' => ['api_key' => 'neu-123'],
                'settings_reset' => ['api_key' => '1'],
            ])
            ->assertRedirect();

        $row->refresh();
        $this->assertSame('neu-123', $row->settings['api_key'] ?? null);
    }

    /** A12: Reset quittiert die offenen Fehler des Plugins mit — die Inbox bleibt nicht rot. */
    public function test_reset_errors_acknowledges_open_errors(): void {
        PluginError::create([
            'plugin_id' => 'admintest',
            'phase' => 'runtime',
            'exception_class' => 'X',
            'message' => 'kaputt',
            'occurred_at' => now(),
        ]);
        $foreign = PluginError::create([
            'plugin_id' => 'other-plugin',
            'phase' => 'runtime',
            'exception_class' => 'X',
            'message' => 'anderes plugin',
            'occurred_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.plugins.reset-errors', 'admintest'))
            ->assertRedirect();

        $this->assertSame(0, PluginError::query()->where('plugin_id', 'admintest')->whereNull('acknowledged_at')->count());
        $this->assertNull($foreign->refresh()->acknowledged_at);
    }

    public function test_plugin_errors_inbox_lists_and_acknowledges(): void {
        $err = PluginError::create([
            'plugin_id' => 'admintest',
            'phase' => 'runtime',
            'exception_class' => 'X',
            'message' => 'something broke',
            'occurred_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.plugin-errors.index'))
            ->assertOk()
            ->assertSee('something broke');

        $this->actingAs($this->admin)
            ->post(route('admin.plugin-errors.acknowledge', $err))
            ->assertRedirect();

        $err->refresh();
        $this->assertNotNull($err->acknowledged_at);
        $this->assertSame($this->admin->id, $err->acknowledged_by);
    }

    public function test_non_admin_is_forbidden(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($user)
            ->get(route('admin.plugins.index'))
            ->assertForbidden();
    }

    // ── Dialog-Fallback ohne Schema/View: Konfigurationsorte verlinken ──

    public function test_dialog_without_schema_links_capability_pages(): void {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.plugins.edit', 'adminbare'))
            ->assertOk()
            ->assertSee(__('Dieses Plugin wird auf eigenen Seiten konfiguriert:'))
            ->assertSee(route('admin.cloud-intake.index'));

        // Backupziele sind Plattform-Admin-Sache — für Org-Admins kein Link.
        $response->assertDontSee(route('admin.backup-targets.index'));
    }

    public function test_dialog_without_schema_links_admin_panel(): void {
        $this->actingAs($this->admin)
            ->get(route('admin.plugins.edit', 'adminpanel'))
            ->assertOk()
            ->assertSee(__('Dieses Plugin wird auf eigenen Seiten konfiguriert:'))
            ->assertSee('AdminPanelZiel');
    }

    public function test_dialog_without_schema_and_links_shows_plain_hint(): void {
        $this->actingAs($this->admin)
            ->get(route('admin.plugins.edit', 'adminbroken'))
            ->assertOk()
            ->assertSee(__('Dieses Plugin hat keine dialogbasierten Einstellungen.'));
    }

    public function test_dialog_with_schema_shows_fields_instead_of_hint(): void {
        $this->actingAs($this->admin)
            ->get(route('admin.plugins.edit', 'admintest'))
            ->assertOk()
            ->assertDontSee(__('Dieses Plugin wird auf eigenen Seiten konfiguriert:'))
            ->assertDontSee(__('Dieses Plugin hat keine dialogbasierten Einstellungen.'));
    }
}

final class AdminTestPlugin implements Plugin {
    use PluginDefaults;

    public function id(): string {
        return 'admintest';
    }
    public function name(): string {
        return 'AdminTest';
    }
    public function version(): string {
        return '1.0.0';
    }
    public function description(): string {
        return '';
    }
    public function isEnabled(): bool {
        return true;
    }
    public function capabilities(): array {
        return [PluginCapability::ContactSync];
    }
    public function adminPanel(): ?array {
        return null;
    }
    public function serviceProvider(): ?string {
        return null;
    }
    public function settingsSchema(): array {
        return [
            ['key' => 'api_key', 'label' => 'API', 'type' => 'password'],
        ];
    }
    public function healthCheck(): PluginHealth {
        return PluginHealth::ok('reachable');
    }
}

/** Schema-los ohne Panel, aber mit Intake-/Backup-Capabilities (Muster Nextcloud/Dropbox/GoogleDrive). */
final class AdminBarePlugin implements Plugin {
    use PluginDefaults;

    public function id(): string {
        return 'adminbare';
    }
    public function name(): string {
        return 'AdminBare';
    }
    public function version(): string {
        return '1.0.0';
    }
    public function description(): string {
        return '';
    }
    public function isEnabled(): bool {
        return true;
    }
    public function capabilities(): array {
        return [PluginCapability::DocumentIntake, PluginCapability::BackupTarget];
    }
    public function adminPanel(): ?array {
        return null;
    }
    public function serviceProvider(): ?string {
        return null;
    }
    public function settingsSchema(): array {
        return [];
    }
    public function healthCheck(): PluginHealth {
        return PluginHealth::ok('reachable');
    }
}

/** Schema-los mit eigenem Admin-Panel (Muster CalDav/Todoist/Zammad …). */
final class AdminPanelPlugin implements Plugin {
    use PluginDefaults;

    public function id(): string {
        return 'adminpanel';
    }
    public function name(): string {
        return 'AdminPanel';
    }
    public function version(): string {
        return '1.0.0';
    }
    public function description(): string {
        return '';
    }
    public function isEnabled(): bool {
        return true;
    }
    public function capabilities(): array {
        return [PluginCapability::ContactSync];
    }
    public function adminPanel(): array {
        return ['route' => 'admin.plugins.index', 'label' => 'AdminPanelZiel', 'icon' => 'extension'];
    }
    public function serviceProvider(): ?string {
        return null;
    }
    public function settingsSchema(): array {
        return [];
    }
    public function healthCheck(): PluginHealth {
        return PluginHealth::ok('reachable');
    }
}

final class AdminFailingPlugin implements Plugin {
    use PluginDefaults;

    public function id(): string {
        return 'adminbroken';
    }
    public function name(): string {
        return 'AdminBroken';
    }
    public function version(): string {
        return '1.0.0';
    }
    public function description(): string {
        return '';
    }
    public function isEnabled(): bool {
        return true;
    }
    public function capabilities(): array {
        return [PluginCapability::ContactSync];
    }
    public function adminPanel(): ?array {
        return null;
    }
    public function serviceProvider(): ?string {
        return null;
    }
    public function settingsSchema(): array {
        return [];
    }
    public function healthCheck(): PluginHealth {
        return PluginHealth::failing('api down');
    }
}
