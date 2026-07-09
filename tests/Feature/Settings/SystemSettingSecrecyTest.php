<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SystemSettingSecrecyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Settings;

use App\Models\{AuditLog, SystemSetting};
use App\Settings\{SettingScope, SettingsRegistry};
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class SystemSettingSecrecyTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        // Sensibles Test-Setting registrieren (Registry liest config).
        // Achtung: config() mit gepunktetem Key würde verschachteln —
        // deshalb die Map als Ganzes ersetzen.
        $definitions = (array) config('settings-registry');
        $definitions['testsecret.api_key'] = [
            'type' => 'string',
            'scopes' => ['system'],
            'sensitive' => true,
        ];
        config(['settings-registry' => $definitions]);
        // Registry-Singleton neu aufbauen, damit der Test-Key sichtbar ist.
        $this->app->forgetInstance(SettingsRegistry::class);
    }

    public function test_sensitive_value_is_encrypted_at_rest(): void {
        Setting::set('testsecret.api_key', 'super-geheim-123', SettingScope::System);

        $raw = DB::table('system_settings')->where('key', 'testsecret.api_key')->value('value');
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('super-geheim-123', (string) $raw);

        // Entschlüsselt lesbar über den Resolver
        $this->assertSame('super-geheim-123', Setting::get('testsecret.api_key'));
    }

    public function test_sensitive_value_is_redacted_in_audit(): void {
        Setting::set('testsecret.api_key', 'super-geheim-123', SettingScope::System);

        $log = AuditLog::query()
            ->where('auditable_type', SystemSetting::class)
            ->where('event', 'created')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $changes = json_encode($log->getAttribute('changes'));
        $this->assertStringNotContainsString('super-geheim-123', (string) $changes);
        $this->assertStringContainsString('<redacted>', (string) $changes);
    }

    public function test_effective_export_value_is_redacted(): void {
        Setting::set('testsecret.api_key', 'super-geheim-123', SettingScope::System);

        $effective = app(SettingsRegistry::class)->effective('testsecret.api_key');
        $this->assertSame('super-geheim-123', $effective->value);
        $this->assertSame('<redacted>', $effective->exportValue());
    }
}
