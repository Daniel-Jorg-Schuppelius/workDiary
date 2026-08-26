<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TextContrastRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „Textkontrast" (Vollscan 2026-08-23, I2 / WCAG 1.4.3 /
 * BFSG): text-base-content mit /30–/60 unterschreitet in den daisyUI-Themes
 * (corporate hell / dim dunkel) 4,5:1 — für Text unzulässig. Nachgerechnet
 * mit CommonToolkit\Helper\Data\ColorHelper::contrastRatio (MVP-724,
 * Deckkraft über base-100 kompositiert):
 *
 *   Stufe  corporate (hell)  dim (dunkel)
 *   /40           2,51            2,53
 *   /50           3,30            3,16
 *   /60           4,53            3,90   ← dunkel unter 4,5:1
 *   /70 = .text-muted  6,34        4,70   ← beide ≥ 4,5:1
 *
 * Der semantische Token `.text-muted` (resources/css/app.css, 70 %
 * base-content) ersetzt diese Abstufungen flächendeckend — auch für
 * dekorative Icons, damit es genau EINEN Sekundärton gibt.
 *
 * Regel: Kein text-base-content/30, /40, /50 oder /60 in resources/views
 * UND in den Plugin-Views (app/Plugins/**\/Resources/views) — sonst wäre der
 * Token über die Plugin-Oberflächen umgehbar.
 * Die BASELINE ist seit MVP-724 leer und bleibt es — neue Fundstellen sind
 * Verstöße, kein Baseline-Eintrag.
 */
class TextContrastRuleTest extends TestCase {
    use ScansSourceTree;

    private const PATTERN = '~text-base-content/(?:30|40|50|60)\b~';

    /**
     * Datei → Fundstellen. Seit MVP-724 leer (Welle 8, Paket 3): alle 430
     * /30–/50-, 1401 /60- und 166 Plugin-Fundstellen sind auf `.text-muted`
     * umgestellt.
     * Neue Einträge sind hier NICHT vorgesehen.
     *
     * @var array<string, int>
     */
    private const BASELINE = [];

    public function test_views_do_not_add_low_contrast_text_classes(): void {
        $violations = [];
        $stale = [];
        $seen = [];

        $files = array_merge($this->bladeFiles(), $this->filesUnder('app/Plugins', '/\.blade\.php$/'));

        foreach ($files as $file) {
            $relative = $this->relativePath($file);
            $source = $this->stripBladeComments((string) file_get_contents($file));
            $count = (int) preg_match_all(self::PATTERN, $source);
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
        $this->assertSame([], $violations, "Neuer Text mit text-base-content/30–/60 (WCAG 1.4.3 unter 4,5:1).\n"
            . "Stattdessen `text-muted` (semantischer Token, resources/css/app.css) verwenden;\n"
            . "reine Deko-Elemente ohne Textinformation bekommen aria-hidden statt einer Ausnahme.\n\n"
            . implode("\n", $violations));

        sort($stale);
        $this->assertSame([], $stale, "Baseline abtragen (Fundstellen gesunken/Datei weg) — Einträge in TextContrastRuleTest::BASELINE anpassen:\n"
            . implode("\n", $stale));
    }
}
