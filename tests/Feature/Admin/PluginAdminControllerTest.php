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
        $this->app->instance(PluginManager::class, $manager);
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

    public function test_health_check_endpoint_returns_status_and_persists(): void {
        $this->actingAs($this->admin)
            ->post(route('admin.plugins.health-check', 'admintest'))
            ->assertOk()
            ->assertJson(['status' => PluginHealth::STATUS_OK]);

        $state = PluginState::query()->where('plugin_id', 'admintest')->firstOrFail();
        $this->assertSame(PluginHealth::STATUS_OK, $state->last_health_status);
    }

    public function test_reset_errors_clears_disabled_reason(): void {
        PluginState::create([
            'plugin_id' => 'admintest',
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
}
