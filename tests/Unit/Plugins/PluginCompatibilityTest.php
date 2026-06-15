<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginCompatibilityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Plugins;

use App\Plugins\PluginCompatibility;
use PHPUnit\Framework\TestCase;

/**
 * Kompatibilitätsprüfung Plugin ↔ Kernversion (Feature 022): blockiert
 * zu alte/zu neue Kernversionen, lässt offene Grenzen und Dev-Suffixe zu.
 */
class PluginCompatibilityTest extends TestCase {
    public function test_no_bounds_is_always_compatible(): void {
        $result = PluginCompatibility::evaluate(null, null, '1.5.0');
        $this->assertTrue($result->compatible);
        $this->assertSame('ok', $result->code);
    }

    public function test_app_below_min_is_incompatible(): void {
        $result = PluginCompatibility::evaluate('1.2.0', null, '1.1.0');
        $this->assertFalse($result->compatible);
        $this->assertSame('too_old', $result->code);
    }

    public function test_app_above_max_is_incompatible(): void {
        $result = PluginCompatibility::evaluate(null, '1.2.0', '1.3.0');
        $this->assertFalse($result->compatible);
        $this->assertSame('too_new', $result->code);
    }

    public function test_app_within_range_is_compatible(): void {
        $result = PluginCompatibility::evaluate('1.0.0', '2.0.0', '1.5.0');
        $this->assertTrue($result->compatible);
    }

    public function test_boundaries_are_inclusive(): void {
        $this->assertTrue(PluginCompatibility::evaluate('1.2.0', null, '1.2.0')->compatible);
        $this->assertTrue(PluginCompatibility::evaluate(null, '1.2.0', '1.2.0')->compatible);
    }

    public function test_dev_suffix_is_treated_as_release_for_bounds(): void {
        // 0.1.0-dev darf nicht unter die Mindestgrenze 0.1.0 rutschen.
        $result = PluginCompatibility::evaluate('0.1.0', null, '0.1.0-dev');
        $this->assertTrue($result->compatible);
    }
}
