<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginCommandRegistrationRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate (UI-Fuzz 2026-09-21): Plugin-Provider registrierten ihre
 * Commands nur unter runningInConsole(). Die Admin-Oberfläche stößt sie aber per
 * Artisan::call/queue im Web-Prozess an — „The command … does not exist" (HTTP 500)
 * bei Spiegeln, Publish und Sync. PHPUnit läuft in der Konsole und sah das nie.
 */
class PluginCommandRegistrationRuleTest extends TestCase {
    use ScansSourceTree;

    public function test_plugin_commands_are_registered_outside_the_console_too(): void {
        $violations = [];

        foreach ($this->phpFiles('app/Plugins') as $file) {
            if (! str_ends_with($file, 'ServiceProvider.php')) {
                continue;
            }
            $source = (string) file_get_contents($file);
            if (preg_match('~runningInConsole\(\)~', $source, $m, PREG_OFFSET_CAPTURE) === 1) {
                $violations[] = sprintf('%s:%d', $this->relativePath($file), $this->lineOf($source, (int) $m[0][1]));
            }
        }

        $this->assertSame([], $violations, "Plugin-Commands ohne runningInConsole()-Wächter registrieren — die Oberfläche ruft sie per Artisan im Web-Prozess auf:\n"
            . implode("\n", $violations));
    }
}
