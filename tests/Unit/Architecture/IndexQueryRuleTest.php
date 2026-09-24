<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IndexQueryRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Gate MVP-871: Listen lesen `sort`/`dir` nur über
 * `SortableQuery::resolve()/apply()` bzw. `ParsesIndexQuery` — eine
 * Whitelist-Semantik (ungültiger Schlüssel setzt Schlüssel und Richtung auf
 * den Default). `direction`/`order` sind in der App Fachfilter
 * (Zahlungsrichtung, Verschieben), `input('sort')` ein Positionsfeld.
 */
class IndexQueryRuleTest extends TestCase {
    use ScansSourceTree;

    public function test_controllers_read_sort_and_dir_through_the_index_parser(): void {
        $violations = [];
        foreach ($this->phpFiles('app/Http/Controllers') as $file) {
            $source = $this->stripComments((string) file_get_contents($file));
            if (preg_match_all('/->(?:query|string|get)\(\s*[\'"](?:sort|dir)[\'"]/', $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }
            foreach ($matches[0] as [$call, $offset]) {
                $violations[] = $this->relativePath($file) . ':' . $this->lineOf($source, $offset) . " {$call}";
            }
        }

        $this->assertSame([], $violations, "Sortierung über SortableQuery::resolve()/apply() oder ParsesIndexQuery lesen:\n" . implode("\n", $violations));
    }
}
