<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RawConfirmAttributeRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „nacktes data-confirm" (Reselling-Review 2026-09-10, C2):
 * `data-confirm="…"` hat keinen JS-Handler — `layout.js` reagiert nur auf
 * `data-confirm-dialog`. Ein Formular mit dem nackten Attribut sendet OHNE
 * Rückfrage (Abo löschen kaskadierte Perioden, Bezüge und Einkaufsbelege).
 *
 * Erlaubte Bauweise: `data-confirm-dialog` am Formular, Button oder Link plus
 * `data-confirm-message="…"` (optional `data-confirm-title`, `-label`,
 * `-icon`, `-tone="error"`). Die BASELINE ist leer und bleibt es. Geprüft
 * werden resources/views und die Plugin-Views (app/Plugins/**\/Resources/views).
 */
class RawConfirmAttributeRuleTest extends TestCase {
    use ScansSourceTree;

    /**
     * Datei → erlaubte Fundstellen. Leer seit dem Review 2026-09-10 (11 Views
     * umgestellt); neue Einträge sind hier NICHT vorgesehen.
     *
     * @var array<string, int>
     */
    private const BASELINE = [];

    /** Zählt `data-confirm="…"` bzw. `data-confirm='…'` — nicht die `data-confirm-*`-Familie. */
    private function countRawConfirmAttributes(string $source): int {
        return (int) preg_match_all('~\bdata-confirm\s*=\s*["\']~', $source);
    }

    public function test_views_do_not_use_the_raw_confirm_attribute(): void {
        $violations = [];
        $stale = [];
        $seen = [];

        foreach (array_merge($this->bladeFiles(), $this->bladeFiles('app/Plugins')) as $file) {
            $relative = $this->relativePath($file);
            $source = $this->stripBladeComments((string) file_get_contents($file));
            $count = $this->countRawConfirmAttributes($source);
            $seen[$relative] = true;

            $allowed = self::BASELINE[$relative] ?? 0;
            if ($count > $allowed) {
                $violations[] = sprintf('%s — %d Fundstelle(n), Baseline erlaubt %d', $relative, $count, $allowed);
            } elseif ($count < $allowed) {
                $stale[] = sprintf("'%s' => %d, // aktuell %d", $relative, $allowed, $count);
            }
        }

        foreach (array_keys(self::BASELINE) as $relative) {
            if (! isset($seen[$relative])) {
                $stale[] = sprintf("'%s' — Datei existiert nicht mehr, Eintrag streichen", $relative);
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "Nacktes data-confirm=\"…\" ohne JS-Handler (layout.js kennt nur data-confirm-dialog).\n"
            . "Stattdessen: data-confirm-dialog data-confirm-message=\"…\" data-confirm-tone=\"error\" am Formular/Button.\n\n"
            . implode("\n", $violations));

        sort($stale);
        $this->assertSame([], $stale, "Baseline abtragen — Einträge in RawConfirmAttributeRuleTest::BASELINE anpassen:\n"
            . implode("\n", $stale));
    }
}
