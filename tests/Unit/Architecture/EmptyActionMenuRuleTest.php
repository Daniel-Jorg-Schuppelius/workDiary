<?php
/*
 * Created on   : Thu Oct 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EmptyActionMenuRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Ein Aktionsmenü ohne Einträge ist ein toter Knopf: Beim Seitenkopf-Umbau
 * (Phase 119) verloren der Prüfbericht und der KI-Verbrauchsbericht so ihre
 * CSV-/Excel-Exporte, obwohl die Controller sie weiter liefern.
 */
class EmptyActionMenuRuleTest extends TestCase {
    use ScansSourceTree;

    public function test_action_menus_have_entries(): void {
        $hits = [];
        foreach ($this->bladeFiles() as $file) {
            $source = $this->stripBladeComments((string) file_get_contents($file));
            if (preg_match_all('/<x-action-menu\b[^>]*>\s*<\/x-action-menu>/', $source, $matches, PREG_OFFSET_CAPTURE) > 0) {
                foreach ($matches[0] as [, $offset]) {
                    $hits[] = $this->relativePath($file) . ':' . $this->lineOf($source, (int) $offset);
                }
            }
        }

        $this->assertSame([], $hits, "Leeres <x-action-menu>:\n" . implode("\n", $hits));
    }
}
