<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeVoucherSyncButtonTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Lexoffice;

use App\Models\Platform\{PluginSetting, User};
use App\Plugins\Lexoffice\LexofficePlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * „Belege jetzt abgleichen“ im Einstellungsdialog des Plugins (Vollscan
 * 2026-11-02, gap-12): die Route `lexoffice.vouchers.sync` hat damit einen
 * Einstieg in der Oberfläche — nur mit Zugangsdaten und aktivem Plugin.
 */
final class LexofficeVoucherSyncButtonTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $settings */
    private function plugin(bool $enabled, array $settings): void {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'enabled' => $enabled,
            'settings' => $settings,
        ]);
    }

    public function test_settings_dialog_offers_the_manual_voucher_sync(): void {
        $this->plugin(true, ['api_key' => 'test-key']);

        $this->actingAs($this->admin)
            ->get(route('admin.plugins.edit', LexofficePlugin::ID))
            ->assertOk()
            ->assertSee(route('lexoffice.vouchers.sync'))
            ->assertSee(__('lexoffice::finance.vouchers.sync_now'));
    }

    public function test_without_credentials_there_is_no_sync_button(): void {
        $this->plugin(true, []);

        $this->actingAs($this->admin)
            ->get(route('admin.plugins.edit', LexofficePlugin::ID))
            ->assertOk()
            ->assertDontSee(route('lexoffice.vouchers.sync'));
    }

    public function test_disabled_plugin_shows_no_sync_button(): void {
        $this->plugin(false, ['api_key' => 'test-key']);

        $this->actingAs($this->admin)
            ->get(route('admin.plugins.edit', LexofficePlugin::ID))
            ->assertOk()
            ->assertDontSee(route('lexoffice.vouchers.sync'));
    }
}
