<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginAdminTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{PluginSetting, User};
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficePlugin};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Deckt die Admin-UI für das Plugin-System ab:
 *  - nur Admins dürfen einsteigen
 *  - Plugin-Toggle und Settings werden persistiert
 *  - API-Key landet verschlüsselt in der DB
 *  - LexofficeConfig::resolve() bevorzugt DB-Werte gegenüber config()
 */
class PluginAdminTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_non_admin_cannot_access_plugin_admin(): void {
        $this->actingAs($this->user)
            ->get(route('admin.plugins.index'))
            ->assertForbidden();
    }

    public function test_admin_can_list_plugins(): void {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.plugins.index'));

        $response->assertOk();
        $response->assertViewHas('plugins');
    }

    public function test_admin_can_save_lexoffice_settings_and_api_key_is_encrypted_at_rest(): void {
        $payload = [
            'enabled' => '1',
            'settings' => [
                'api_key' => 'sk_live_secret_123456',
                'base_url' => 'https://api.lexoffice.io/v1',
                'default_currency' => 'EUR',
                'default_tax_type' => 'net',
                'default_vat_rate' => '19',
                'match_policy' => 'manual_review',
                'create_missing_local' => '1',
            ],
        ];

        $this->actingAs($this->admin)
            ->put(route('admin.plugins.update', LexofficePlugin::ID), $payload)
            ->assertRedirect();

        $row = PluginSetting::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->where('plugin_id', LexofficePlugin::ID)
            ->firstOrFail();

        $this->assertTrue($row->enabled);
        $this->assertSame('sk_live_secret_123456', $row->settings['api_key']);
        $this->assertSame('manual_review', $row->settings['match_policy']);
        $this->assertTrue((bool) $row->settings['create_missing_local']);

        // Raw-DB-Wert darf den Klartext-Key NICHT enthalten.
        $raw = (string) DB::table('plugin_settings')
            ->where('id', $row->id)
            ->value('settings');

        $this->assertNotEmpty($raw);
        $this->assertStringNotContainsString('sk_live_secret_123456', $raw);
    }

    public function test_empty_password_input_does_not_overwrite_existing_api_key(): void {
        // Erst speichern …
        $this->actingAs($this->admin)
            ->put(route('admin.plugins.update', LexofficePlugin::ID), [
                'enabled' => '1',
                'settings' => ['api_key' => 'sk_initial'],
            ])
            ->assertRedirect();

        // … dann ohne api_key updaten (z. B. nur match_policy ändern).
        $this->actingAs($this->admin)
            ->put(route('admin.plugins.update', LexofficePlugin::ID), [
                'enabled' => '1',
                'settings' => ['api_key' => '', 'match_policy' => 'lexoffice_wins'],
            ])
            ->assertRedirect();

        $row = PluginSetting::query()
            ->withoutGlobalScopes()
            ->where('plugin_id', LexofficePlugin::ID)
            ->firstOrFail();

        $this->assertSame('sk_initial', $row->settings['api_key'], 'API-Key darf bei leerer Eingabe nicht überschrieben werden');
        $this->assertSame('lexoffice_wins', $row->settings['match_policy']);
    }

    public function test_lexoffice_config_resolve_prefers_database_over_config(): void {
        config()->set('plugins.lexoffice.api_key', 'env-key-loser');
        config()->set('plugins.lexoffice.match_policy', 'manual_review');

        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'enabled' => true,
            'settings' => [
                'api_key' => 'db-key-winner',
                'match_policy' => 'lexoffice_wins',
            ],
        ]);

        $resolved = LexofficeConfig::resolve($this->organization->id);

        $this->assertSame('db-key-winner', $resolved['api_key']);
        $this->assertSame('lexoffice_wins', $resolved['match_policy']);
        $this->assertTrue($resolved['enabled']);
    }

    public function test_lexoffice_config_resolve_falls_back_to_env_when_no_db_row(): void {
        config()->set('plugins.lexoffice.api_key', 'env-only-key');

        $resolved = LexofficeConfig::resolve($this->organization->id);

        $this->assertSame('env-only-key', $resolved['api_key']);
    }

    public function test_update_rejects_unknown_plugin(): void {
        $this->actingAs($this->admin)
            ->put(route('admin.plugins.update', 'nonexistent-plugin'), ['enabled' => '1'])
            ->assertNotFound();
    }
}
