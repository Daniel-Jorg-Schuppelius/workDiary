<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginDefaultsHealthTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Plugins;

use App\Plugins\{PluginDefaults, PluginHealth};
use Tests\TestCase;

/**
 * A15 (Review 2026-08): der Healthcheck-Default meldet „degraded" mit Code
 * `not_implemented` — ein pauschales „ok" ohne jede Prüfung wäre eine falsche
 * Gesundmeldung in der Admin-Übersicht.
 */
class PluginDefaultsHealthTest extends TestCase {
    public function test_default_health_check_is_degraded_not_implemented(): void {
        $health = (new DefaultsOnlyHealthProbe)->healthCheck();

        $this->assertSame(PluginHealth::STATUS_DEGRADED, $health->status);
        $this->assertSame('not_implemented', $health->code);
        $this->assertNotSame('', $health->message);
    }
}

final class DefaultsOnlyHealthProbe {
    use PluginDefaults;
}
