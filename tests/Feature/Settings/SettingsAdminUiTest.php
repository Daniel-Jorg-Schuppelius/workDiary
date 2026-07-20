<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SettingsAdminUiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Settings\SettingsRegistry;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsAdminUiTest extends TestCase {
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_index_requires_permission_and_lists_registry_keys(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->admin->organization_id]);
        $this->actingAs($user)->get(route('admin.settings.index'))->assertForbidden();

        $this->actingAs($this->admin)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('pagination.customers')
            ->assertSee('archive.schedule_at');
    }

    public function test_search_filters_keys(): void {
        $this->actingAs($this->admin)->get(route('admin.settings.index', ['q' => 'archive']))
            ->assertOk()
            ->assertSee('archive.schedule_at')
            ->assertDontSee('pagination.customers');
    }

    public function test_update_and_reset_system_override_via_ui(): void {
        $this->actingAs($this->admin)->put(route('admin.settings.update', ['key' => 'pagination.customers']), [
            'scope' => 'system',
            'value' => '60',
        ])->assertRedirect();

        $this->assertSame(60, Setting::get('pagination.customers'));
        $this->assertDatabaseHas('system_settings', ['key' => 'pagination.customers']);

        $this->actingAs($this->admin)->delete(route('admin.settings.reset', ['key' => 'pagination.customers']), [
            'scope' => 'system',
        ])->assertRedirect();

        $this->assertDatabaseCount('system_settings', 0);
    }

    /** Vollaudit 2026-07 (N20): Konfigurationsstand-Export als JSON + Audit. */
    public function test_settings_export_returns_json_and_writes_audit(): void {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.settings.export', ['scope' => 'system']))
            ->assertOk()
            ->assertHeader('Content-Disposition');

        $payload = $response->json();
        $this->assertSame('system', $payload['scope']);
        $this->assertArrayHasKey('settings', $payload);
        $this->assertNotEmpty($payload['settings']);

        $this->assertDatabaseHas('audit_logs', ['event' => 'settings.exported']);

        // Ohne Recht kein Export.
        $user = \App\Models\User::factory()->user()->create(['organization_id' => $this->admin->organization_id]);
        $this->actingAs($user)->get(route('admin.settings.export'))->assertForbidden();
    }

    public function test_validation_error_is_reported_without_saving(): void {
        $this->actingAs($this->admin)->put(route('admin.settings.update', ['key' => 'pagination.customers']), [
            'scope' => 'system',
            'value' => '99999',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseCount('system_settings', 0);
    }

    public function test_org_scope_writes_organization_override(): void {
        $this->actingAs($this->admin)->put(route('admin.settings.update', ['key' => 'pagination.customers']), [
            'scope' => 'organization',
            'value' => '90',
        ])->assertRedirect();

        $effective = app(SettingsRegistry::class)->effective('pagination.customers', $this->admin->organization()->firstOrFail());
        $this->assertSame(90, $effective->value);
        $this->assertSame(\App\Settings\SettingSource::Organization, $effective->source);
    }

    public function test_disallowed_scope_shows_error(): void {
        // archive.schedule_at ist system-only.
        $this->actingAs($this->admin)->put(route('admin.settings.update', ['key' => 'archive.schedule_at']), [
            'scope' => 'organization',
            'value' => '04:00',
        ])->assertRedirect()->assertSessionHas('error');
    }

    public function test_history_dialog_shows_audit_entries(): void {
        // Über die UI setzen: voller HTTP-Kontext (currentOrganization/User)
        // für den Audit-Eintrag des SystemSettings.
        $this->actingAs($this->admin)->put(route('admin.settings.update', ['key' => 'pagination.customers']), [
            'scope' => 'system',
            'value' => '60',
        ])->assertRedirect();

        $this->actingAs($this->admin)
            ->get(route('admin.settings.history', ['key' => 'pagination.customers']))
            ->assertOk()
            ->assertSee('created');
    }
}
