<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FritzboxHealthCheckTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{EmailConnection, PluginSetting, User};
use App\Plugins\Fritzbox\FritzboxPlugin;
use App\Plugins\PluginHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Audit 2026-08 (Welle 0.4): Fritzbox war das einzige Plugin ohne eigenen
 * healthCheck() und stand dauerhaft „degraded/not_implemented" in der
 * Admin-Übersicht — der Check ist DB-basiert (kein API-Pull vorhanden).
 */
class FritzboxHealthCheckTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_ok_in_csv_only_mode_without_mail_intake(): void {
        $health = (new FritzboxPlugin)->healthCheck();

        $this->assertSame(PluginHealth::STATUS_OK, $health->status);
        $this->assertNotSame('', $health->message);
    }

    public function test_degraded_when_default_user_is_missing(): void {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => FritzboxPlugin::ID,
            'enabled' => true,
            'settings' => ['default_user_id' => '999999'],
        ]);

        $health = (new FritzboxPlugin)->healthCheck();

        $this->assertSame(PluginHealth::STATUS_DEGRADED, $health->status);
        $this->assertSame('invalid_config', $health->code);
    }

    public function test_ok_when_default_user_exists_and_mail_intake_connected(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => FritzboxPlugin::ID,
            'enabled' => true,
            'settings' => ['default_user_id' => (string) $user->id],
        ]);
        EmailConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Telefonberichte',
            'host' => 'imap.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'callreports@example.com',
            'password' => 'secret',
            'folder' => 'INBOX',
            'active' => true,
            'callreport_intake' => true,
        ]);

        $health = (new FritzboxPlugin)->healthCheck();

        $this->assertSame(PluginHealth::STATUS_OK, $health->status);
    }
}
