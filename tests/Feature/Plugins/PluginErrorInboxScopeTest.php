<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginErrorInboxScopeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{Organization, PluginError, PluginState, User};
use App\Plugins\Contracts\{Plugin, PluginCapability};
use App\Plugins\{PluginDefaults, PluginManager};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Mandantentrennung + Triage der Fehler-Inbox (Review 2026-08, W1/W4c):
 * sichtbar sind nur eigene + globale Fehler; der globale Kill-Switch ist
 * über die Org-UI nicht aufhebbar; Bulk-Quittierung und Reopen.
 */
class PluginErrorInboxScopeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected User $admin;

    protected Organization $otherOrg;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->otherOrg = Organization::factory()->create();

        $manager = new PluginManager;
        $manager->register(new ScopeTestPlugin);
        $this->app->instance(PluginManager::class, $manager);
    }

    private function makeError(array $attributes = []): PluginError {
        return PluginError::create(array_merge([
            'plugin_id' => 'scopetest',
            'phase' => 'runtime',
            'exception_class' => 'X',
            'message' => 'kaputt',
            'occurred_at' => now(),
        ], $attributes));
    }

    public function test_index_hides_foreign_org_errors_but_shows_global(): void {
        $this->makeError(['message' => 'eigener fehler', 'organization_id' => $this->organization->id]);
        $this->makeError(['message' => 'fremder fehler', 'organization_id' => $this->otherOrg->id]);
        $this->makeError(['message' => 'globaler fehler', 'organization_id' => null]);

        $this->actingAs($this->admin)
            ->get(route('admin.plugin-errors.index'))
            ->assertOk()
            ->assertSee('eigener fehler')
            ->assertSee('globaler fehler')
            ->assertDontSee('fremder fehler');
    }

    public function test_show_of_foreign_org_error_is_not_found(): void {
        $foreign = $this->makeError(['organization_id' => $this->otherOrg->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.plugin-errors.show', $foreign))
            ->assertNotFound();
    }

    /** E-5: Öffnen quittiert automatisch; Reopen macht es rückgängig. */
    public function test_show_auto_acknowledges_and_reopen_reverts(): void {
        $err = $this->makeError(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.plugin-errors.show', $err))
            ->assertOk();
        $this->assertNotNull($err->refresh()->acknowledged_at);

        $this->actingAs($this->admin)
            ->post(route('admin.plugin-errors.reopen', $err))
            ->assertRedirect();
        $this->assertNull($err->refresh()->acknowledged_at);
    }

    public function test_bulk_acknowledge_by_ids_and_by_filter(): void {
        $a = $this->makeError();
        $b = $this->makeError();
        $other = $this->makeError(['plugin_id' => 'other-plugin']);

        $this->actingAs($this->admin)
            ->post(route('admin.plugin-errors.bulk-acknowledge'), ['ids' => [$a->id]])
            ->assertRedirect();
        $this->assertNotNull($a->refresh()->acknowledged_at);
        $this->assertNull($b->refresh()->acknowledged_at);

        $this->actingAs($this->admin)
            ->post(route('admin.plugin-errors.bulk-acknowledge'), ['all_filtered' => 1, 'plugin' => 'scopetest'])
            ->assertRedirect();
        $this->assertNotNull($b->refresh()->acknowledged_at);
        $this->assertNull($other->refresh()->acknowledged_at, 'Filterfremde Fehler bleiben offen.');
    }

    /** W1b: der globale Kill-Switch bleibt — Aufhebung nur per CLI plugin:reset. */
    public function test_ui_reset_keeps_global_auto_disable(): void {
        PluginState::create(['plugin_id' => 'scopetest', 'organization_id' => null, 'failure_count' => 9, 'disabled_reason' => 'global-boot']);
        PluginState::create(['plugin_id' => 'scopetest', 'organization_id' => $this->organization->id, 'failure_count' => 3, 'disabled_reason' => 'org-fehler']);

        $this->actingAs($this->admin)
            ->post(route('admin.plugins.reset-errors', 'scopetest'))
            ->assertRedirect();

        $global = PluginState::query()->where('plugin_id', 'scopetest')->whereNull('organization_id')->firstOrFail();
        $this->assertSame('global-boot', $global->disabled_reason, 'Globaler Kill-Switch bleibt bestehen.');

        $org = PluginState::query()->where('plugin_id', 'scopetest')->where('organization_id', $this->organization->id)->firstOrFail();
        $this->assertNull($org->disabled_reason);
        $this->assertSame(0, (int) $org->failure_count);
    }

    public function test_cli_reset_clears_global_state(): void {
        PluginState::create(['plugin_id' => 'scopetest', 'organization_id' => null, 'failure_count' => 9, 'disabled_reason' => 'global-boot']);

        $this->artisan('plugin:reset scopetest')->assertExitCode(0);

        $global = PluginState::query()->where('plugin_id', 'scopetest')->whereNull('organization_id')->firstOrFail();
        $this->assertNull($global->disabled_reason);
        $this->assertSame(0, (int) $global->failure_count);
    }

    public function test_message_search_filter(): void {
        $this->makeError(['message' => 'Verbindung abgelehnt: token ungültig']);
        $this->makeError(['message' => 'irgendwas anderes']);

        $this->actingAs($this->admin)
            ->get(route('admin.plugin-errors.index', ['q' => 'token']))
            ->assertOk()
            ->assertSee('token ungültig')
            ->assertDontSee('irgendwas anderes');
    }
}

final class ScopeTestPlugin implements Plugin {
    use PluginDefaults;

    public function id(): string {
        return 'scopetest';
    }
    public function name(): string {
        return 'ScopeTest';
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
}
