<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeRequestIntervalTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Plugins\Lexoffice\{LexofficeConfig, LexofficePlugin};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\InteractsWithPlugins;
use Tests\TestCase;

/**
 * Ratenlimit-Schutz (Produktionslog 2026-09-03: 429 bei jedem Sync-Lauf, weil
 * drei Lexoffice-Kommandos gleichzeitig starteten): ein zentraler Anfrage-
 * abstand je Client statt verstreuter usleep-Aufrufe, per Plugin-Einstellung
 * änderbar und nach oben wie unten begrenzt.
 */
class LexofficeRequestIntervalTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_default_interval_keeps_a_single_run_below_two_requests_per_second(): void {
        $this->enablePluginFor($this->organization, LexofficePlugin::ID, ['api_key' => 'lex-key']);

        $this->assertSame(0.5, LexofficeConfig::resolve($this->organization->id)['request_interval']);
        $this->assertSame(0.5, LexofficeConfig::requestInterval($this->organization->id));
    }

    public function test_setting_overrides_and_is_clamped(): void {
        $setting = $this->enablePluginFor($this->organization, LexofficePlugin::ID, ['api_key' => 'lex-key', 'request_interval' => '1.25']);
        $this->assertSame(1.25, LexofficeConfig::requestInterval($this->organization->id));

        // settings ist verschlüsselt gespeichert — immer über das Modell schreiben.
        $setting->settings = ['api_key' => 'lex-key', 'request_interval' => '42'];
        $setting->save();
        $this->assertSame(5.0, LexofficeConfig::requestInterval($this->organization->id), 'nach oben auf 5 s begrenzt');

        $setting->settings = ['api_key' => 'lex-key', 'request_interval' => '-3'];
        $setting->save();
        $this->assertSame(0.0, LexofficeConfig::requestInterval($this->organization->id), 'nie negativ');
    }

    public function test_lock_key_is_per_organization(): void {
        $this->assertSame('lexoffice:api:7', LexofficeConfig::apiLockKey(7));
        $this->assertNotSame(LexofficeConfig::apiLockKey(1), LexofficeConfig::apiLockKey(2));
    }
}
