<?php
/*
 * Created on   : Sat Sep 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ListReturnRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „Aktionen kehren zur gefilterten Liste zurück" (Anlass
 * 2026-09-19: Testlauf im Scheduler setzte Filter und Sortierung zurück; 165
 * gleichartige Rücksprünge in 66 Controllern).
 *
 * Listen sind Routen, deren Ansicht eine Filterleiste oder Server-Sortierung
 * hat. Ein Rücksprung dorthin ohne Parameter — redirect()->route('x.index'),
 * to_route('x.index'), redirect(route('x.index')) — verwirft Filter, Sortierung
 * und Seite. Richtig ist redirect()->toList('x.index') (RememberListUrl).
 */
class ListReturnRuleTest extends TestCase {
    use ScansSourceTree;

    public function test_redirects_to_filterable_lists_keep_the_list_state(): void {
        $lists = $this->listRoutes();
        $this->assertNotEmpty($lists, 'Keine Listenrouten gefunden — Erkennung der Filterleiste prüfen.');

        $names = implode('|', array_map(static fn(string $n): string => preg_quote($n, '/'), $lists));
        $pattern = '/(?:redirect\(\)->route|to_route)\(\s*\'(?:' . $names . ')\'\s*\)|redirect\(\s*route\(\s*\'(?:' . $names . ')\'\s*\)\s*\)/';

        $violations = [];
        $files = array_merge($this->phpFiles('app/Http'), $this->phpFiles('app/Plugins'));
        foreach ($files as $file) {
            if (str_ends_with($file, '.blade.php')) {
                continue;
            }
            $source = $this->stripComments((string) file_get_contents($file));
            if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }
            foreach ($matches[0] as [$text, $offset]) {
                $violations[] = sprintf('%s:%d  %s', $this->relativePath($file), $this->lineOf($source, (int) $offset), $text);
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "Rücksprung auf eine filterbare Liste ohne ihren Zustand.\n"
            . "redirect()->toList('…') statt redirect()->route('…') verwenden.\n\n" . implode("\n", $violations));
    }

    /** @return list<string> */
    private function listRoutes(): array {
        $routes = [];
        $views = array_merge($this->bladeFiles(), $this->filesUnder('app/Plugins', '/\.blade\.php$/'));
        foreach ($views as $view) {
            if (str_contains($this->relativePath($view), 'resources/views/legacy/')) {
                continue;
            }
            $source = (string) file_get_contents($view);
            if (preg_match_all('/<x-filter-bar[^>]*?(?::action="route\(\'([^\']+)\'|action="\{\{\s*route\(\'([^\']+)\')/s', $source, $m, PREG_SET_ORDER) > 0) {
                foreach ($m as $hit) {
                    $routes[$hit[1] !== '' ? $hit[1] : $hit[2]] = true;
                }
            }
            if (preg_match_all('/table-sort="server"[^>]*?:route="route\(\'([^\']+)\'/s', $source, $m) > 0) {
                foreach ($m[1] as $name) {
                    $routes[$name] = true;
                }
            }
        }

        return array_keys($routes);
    }
}
