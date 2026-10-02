<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RawSchemaListingRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate gegen Tabellenlisten ohne Schema: Seit Laravel 12 liefern
 * `getTables()`, `getTableListing()` und `getViews()` ohne Argument auf MySQL/
 * MariaDB die Objekte aller sichtbaren Datenbanken. Merge-Dienste sahen so jede
 * Tabelle mehrfach, der Support-Bericht nannte fremde Datenbanken.
 *
 * Regel: immer mit `getCurrentSchemaListing()` aufrufen.
 */
class RawSchemaListingRuleTest extends TestCase {
    use ScansSourceTree;

    public function test_table_listings_are_limited_to_the_current_schema(): void {
        $violations = [];

        foreach ($this->phpFiles('app') as $file) {
            $source = $this->stripComments((string) file_get_contents($file));
            if (preg_match_all('/(?:->|::)(getTables|getTableListing|getViews)\(\s*\)/', $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }
            foreach ($matches[1] as [$method, $offset]) {
                $violations[] = sprintf('%s:%d — %s()', $this->relativePath($file), $this->lineOf($source, (int) $offset), $method);
            }
        }

        sort($violations);

        $this->assertSame([], $violations, "Tabellenliste ohne Schema — getCurrentSchemaListing() übergeben:\n" . implode("\n", $violations));
    }
}
