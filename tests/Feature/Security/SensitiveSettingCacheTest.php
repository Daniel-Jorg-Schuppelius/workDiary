<?php
/*
 * Created on   : Sun Sep 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SensitiveSettingCacheTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Models\Platform\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Sicherheitsaudit 2026-09-13: Die Werte-Karte der Systemeinstellungen lag
 * dauerhaft im Cache — mitsamt dem KLARTEXT der als sensibel gekennzeichneten
 * Einträge, deren Spalte in der Datenbank eigens verschlüsselt ist. Der Cache
 * hat den Schutz damit ausgehebelt.
 */
class SensitiveSettingCacheTest extends TestCase {
    use RefreshDatabase;

    private function setting(string $key, string $value, bool $sensitive): SystemSetting {
        $row = new SystemSetting();
        $row->key = $key;
        $row->setResolvedValue($value, $sensitive);
        $row->save();

        return $row;
    }

    public function test_sensitive_values_never_reach_the_cache(): void {
        $this->setting('integrations.secret_token', 'GEHEIM-KANARIENVOGEL', true);
        $this->setting('branding.company', 'Muster GmbH', false);

        $map = SystemSetting::valueMap();

        // Der Wert bleibt über die normale Schnittstelle lesbar …
        $this->assertSame('GEHEIM-KANARIENVOGEL', $map['integrations.secret_token'] ?? null);
        $this->assertSame('Muster GmbH', $map['branding.company'] ?? null);

        // … steht aber nicht im Cache.
        $cached = Cache::get(SystemSetting::CACHE_KEY);
        $this->assertIsArray($cached);
        $this->assertStringNotContainsString(
            'GEHEIM-KANARIENVOGEL',
            (string) json_encode($cached),
            'Der Klartext einer sensiblen Einstellung darf nicht im Cache liegen.',
        );
        $this->assertContains('integrations.secret_token', $cached['sensitive'] ?? []);
        $this->assertArrayNotHasKey('integrations.secret_token', $cached['values'] ?? []);
    }

    public function test_changing_a_sensitive_value_is_seen_immediately(): void {
        $row = $this->setting('integrations.secret_token', 'ALT', true);
        $this->assertSame('ALT', SystemSetting::valueMap()['integrations.secret_token'] ?? null);

        $row->setResolvedValue('NEU', true);
        $row->save();

        $this->assertSame('NEU', SystemSetting::valueMap()['integrations.secret_token'] ?? null);
    }
}
