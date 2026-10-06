<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginCommandSwitchRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Plugins\Support\Console\ChecksPluginSwitch;
use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Gate zum Konsolidierungs-Audit 2026-10 (k2-06): jedes geplante
 * Plugin-Kommando fragt den Schalter der Organisation über
 * {@see ChecksPluginSwitch::pluginEnabledFor()}. Elf Kommandos prüften gar
 * nicht und liefen bei abgeschaltetem Plugin weiter, solange die Verbindung
 * bestand; die übrigen taten es in fünf Schreibweisen.
 */
class PluginCommandSwitchRuleTest extends TestCase {
    use ScansSourceTree;

    public function test_scheduled_plugin_commands_ask_the_organization_switch(): void {
        $scheduled = [];
        foreach ((array) config('scheduler.jobs', []) as $job) {
            if (is_array($job) && is_string($job['command'] ?? null)) {
                $scheduled[strtok($job['command'], ' ')] = true;
            }
        }
        $this->assertNotSame([], $scheduled, 'Scheduler-Register nicht lesbar.');

        $violations = [];
        $checked = 0;
        foreach ($this->phpFiles('app/Plugins') as $file) {
            $source = (string) file_get_contents($file);
            if (preg_match('/\$signature = \'([a-z0-9:-]+)/', $source, $m) !== 1 || ! isset($scheduled[$m[1]])) {
                continue;
            }
            $checked++;
            if (! str_contains($source, 'pluginEnabledFor(') && ! $this->parentChecks($source)) {
                $violations[] = $this->relativePath($file) . ' (' . $m[1] . ')';
            }
        }

        $this->assertGreaterThan(20, $checked, 'Keine geplanten Plugin-Kommandos gefunden — Gate umgebaut?');
        $this->assertSame([], $violations, "Geplantes Plugin-Kommando ohne Schalterprüfung — `ChecksPluginSwitch::pluginEnabledFor()` je Organisation aufrufen:\n" . implode("\n", $violations));
    }

    /** Ableitungen einer Basis unter `app/Plugins/Support`, die selbst prüft. */
    private function parentChecks(string $source): bool {
        if (preg_match('/^use (App\\\\Plugins\\\\Support\\\\[\w\\\\]+);/m', $source, $use) < 1 || preg_match('/class \w+ extends (\w+)/', $source, $class) !== 1) {
            return false;
        }
        preg_match_all('/^use (App\\\\Plugins\\\\Support\\\\[\w\\\\]+);/m', $source, $uses);
        foreach ($uses[1] as $fqcn) {
            if (str_ends_with($fqcn, '\\' . $class[1])) {
                $parent = base_path(str_replace(['App\\', '\\'], ['app/', '/'], $fqcn) . '.php');

                return is_file($parent) && str_contains((string) file_get_contents($parent), 'pluginEnabledFor(');
            }
        }

        return false;
    }
}
