<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginTextsRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Plugins sind geschlossene Pakete (MVP-1050): Texte, die nur ein Plugin
 * verwendet, liegen in dessen `Resources/lang` — JSON-Texte in
 * `<sprache>.json` (Aufruf unverändert), Gruppenschlüssel als
 * `<plugin-id>::<gruppe>.…`. Im Kernkatalog bleiben Texte, die der Kern, die
 * Plugin-Infrastruktur (`app/Plugins/Support`, `Contracts`) oder mehrere
 * Plugins verwenden.
 */
final class PluginTextsRuleTest extends TestCase {
    use ScansSourceTree;

    /** Ordner unter app/Plugins, die zur Plattform gehören, nicht zu einem Plugin. */
    private const PLATFORM = ['Support', 'Contracts'];

    /**
     * Kernschlüssel, die der Kern nur dynamisch liest und die deshalb trotz
     * reiner Plugin-Aufrufe im Kern bleiben: Schlüssel → Begründung.
     *
     * @var array<string, string>
     */
    private const ALLOWED = [];

    public function test_texts_used_by_a_single_plugin_live_in_the_plugin(): void {
        $core = $this->repoRoot() . '/lang';
        $json = array_fill_keys(array_keys((array) json_decode((string) file_get_contents($core . '/en.json'), true)), true);
        $groups = array_fill_keys(array_map(static fn(string $f): string => basename($f, '.php'), glob($core . '/de/*.php') ?: []), true);

        $users = [];
        foreach ([...$this->phpFiles('app'), ...$this->phpFiles('resources/views')] as $file) {
            $relative = $this->relativePath($file);
            if (str_contains($relative, '/Resources/lang/')) {
                continue;
            }
            $area = $this->area($relative);
            if (preg_match_all('~(?<![A-Za-z0-9_$>])(?:__|trans|trans_choice|@lang)\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1\s*[,)]~s', (string) file_get_contents($file), $m) === 0) {
                continue;
            }
            foreach ($m[2] as $raw) {
                $users[stripcslashes($raw)][$area] = true;
            }
        }

        $offenders = [];
        foreach ($users as $key => $areas) {
            if (count($areas) !== 1 || isset($areas['']) || isset(self::ALLOWED[$key])) {
                continue;
            }
            $plugin = (string) array_key_first($areas);
            if (isset($json[$key])) {
                $offenders[] = "{$plugin}: „{$key}“ → app/Plugins/{$plugin}/Resources/lang/<sprache>.json";
            } elseif (! str_contains($key, '::') && isset($groups[explode('.', $key, 2)[0]])) {
                $offenders[] = "{$plugin}: {$key} → app/Plugins/{$plugin}/Resources/lang/<sprache>/" . explode('.', $key, 2)[0] . '.php (Aufruf mit <plugin-id>::)';
            }
        }
        sort($offenders);

        $this->assertSame([], $offenders, "Texte eines einzelnen Plugins im Kernkatalog:\n" . implode("\n", $offenders));
    }

    /** Plugin-Ordner oder '' für Kern und Plugin-Plattform. */
    private function area(string $relative): string {
        if (preg_match('~^app/Plugins/([^/]+)/~', $relative, $m) === 1 && ! in_array($m[1], self::PLATFORM, true)) {
            return $m[1];
        }

        return '';
    }
}
