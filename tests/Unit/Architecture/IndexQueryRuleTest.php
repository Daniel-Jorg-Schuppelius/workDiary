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

    /** @var array<string, string> Datei => Grund */
    private const ALLOW_LIST = [
        'app/Legacy/Http/Controllers/LegacyArchiveController.php' => 'Altsystem: je Tab eine eigene Spaltenzuordnung, Vorgabe und Richtung hängen am aktiven Tab.',
        'app/Legacy/Services/LegacyDashboardService.php' => 'Altsystem: je Tab eine eigene Spaltenzuordnung, Vorgabe und Richtung hängen am aktiven Tab.',
    ];

    public function test_controllers_read_sort_and_dir_through_the_index_parser(): void {
        $violations = [];
        $seen = [];
        // Plugins und Altsystem lesen dieselben Parameter (Konsolidierungs-Audit 2026-10, k3-18).
        foreach ([...$this->phpFiles('app/Http/Controllers'), ...$this->phpFiles('app/Plugins'), ...$this->phpFiles('app/Legacy')] as $file) {
            $source = $this->stripComments((string) file_get_contents($file));
            if (preg_match_all('/->(?:query|string|get)\(\s*[\'"](?:sort|dir)[\'"]/', $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }
            $relative = $this->relativePath($file);
            if (isset(self::ALLOW_LIST[$relative])) {
                $seen[$relative] = true;

                continue;
            }
            foreach ($matches[0] as [$call, $offset]) {
                $violations[] = $relative . ':' . $this->lineOf($source, $offset) . " {$call}";
            }
        }

        $this->assertSame([], $violations, "Sortierung über SortableQuery::resolve()/apply() oder ParsesIndexQuery lesen:\n" . implode("\n", $violations));
        $this->assertSame([], array_values(array_diff(array_keys(self::ALLOW_LIST), array_keys($seen))), 'Veraltete Ausnahmen in ALLOW_LIST.');
    }
}
