<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalHrefRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Gate zum Sicherheitsaudit 2026-10-04 (xi-4): eine Adresse aus einem
 * Datensatz (`*_url`, `permalink`, `website`, `homepage`) wird nur über
 * `x-external-link` zum Link. Blade maskiert Zeichen, prüft aber kein Schema —
 * ein `javascript:`-Wert aus einer Fremdquelle bliebe anklickbar.
 *
 * Nicht gemeint sind Ziele, die die Anwendung selbst erzeugt (`route()`,
 * `$item['url']` aus einem Dienst).
 */
class ExternalHrefRuleTest extends TestCase {
    use ScansSourceTree;

    private const PATTERN = '/\bhref\s*=\s*"\{\{\s*\$[^}]*?(?:->|\[\')(?:[a-z]+_url|permalink|website|homepage)(?:\'\])?\s*(?:\?\?[^}]*)?\}\}"/';

    public function test_addresses_from_records_are_linked_through_the_external_link_component(): void {
        $violations = [];
        foreach ($this->bladeFiles() as $file) {
            $source = $this->stripBladeComments((string) file_get_contents($file));
            if (preg_match_all(self::PATTERN, $source, $matches, PREG_OFFSET_CAPTURE) > 0) {
                foreach ($matches[0] as [$_, $offset]) {
                    $violations[] = $this->relativePath($file) . ':' . $this->lineOf($source, $offset);
                }
            }
        }

        $this->assertSame([], $violations, "Adresse aus einem Datensatz roh im href — <x-external-link :url=\"…\" /> prüft das Schema:\n" . implode("\n", $violations));
    }
}
