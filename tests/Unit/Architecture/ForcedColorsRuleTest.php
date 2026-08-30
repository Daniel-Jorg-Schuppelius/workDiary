<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ForcedColorsRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate für den erzwungenen Farbmodus (BFSG, EN 301 549).
 *
 * Der Windows-Kontrastmodus ersetzt sämtliche Farben durch Systemfarben und
 * unterdrückt `box-shadow`. Wer den Tastaturfokus nur über Füllfarbe oder
 * Schatten anzeigt, zeigt ihn dort **gar nicht** an — und ein unsichtbarer
 * Fokus macht die Anwendung für Tastaturnutzer unbedienbar (WCAG 2.4.7).
 *
 * Beides ist mit bloßem Auge nicht zu sehen, solange man nicht selbst im
 * Kontrastmodus arbeitet. Deshalb hier zwei maschinelle Regeln statt einer
 * Bitte im Handbuch.
 */
class ForcedColorsRuleTest extends TestCase {
    use ScansSourceTree;

    public function test_es_gibt_einen_kontrastmodus_block_mit_fokusumriss(): void {
        $css = (string) file_get_contents($this->repoRoot() . '/resources/css/app.css');

        $this->assertStringContainsString(
            '@media (forced-colors: active)',
            $css,
            'Ohne forced-colors-Block bleibt im Windows-Kontrastmodus u. a. der Fokus unsichtbar.'
        );

        $start = strpos($css, '@media (forced-colors: active)');
        $block = substr($css, (int) $start, 2000);

        $this->assertMatchesRegularExpression(
            '/:focus-visible\s*\{[^}]*outline\s*:/s',
            $block,
            'Der forced-colors-Block muss einen Fokus-Umriss setzen — outline ist das Einzige, was dort überlebt.'
        );
    }

    public function test_kein_fokus_ohne_umriss(): void {
        $offenders = [];

        foreach ($this->filesUnder('resources/css', '/\.css$/') as $file) {
            $source = (string) file_get_contents($file);

            foreach (explode('}', $source) as $chunk) {
                $brace = strrpos($chunk, '{');

                if ($brace === false) {
                    continue;
                }

                $selector = substr($chunk, 0, $brace);
                $body = substr($chunk, $brace + 1);

                if (! str_contains($selector, ':focus-visible')) {
                    continue;
                }

                if (preg_match('/outline\s*:\s*(none|0)\b/i', $body) === 1) {
                    $offenders[] = $this->relativePath($file) . ': ' . trim(preg_replace('/\s+/', ' ', $selector) ?? '');
                }
            }
        }

        $this->assertSame([], $offenders, "Fokus-Regeln, die den Umriss entfernen:\n"
            . implode("\n", $offenders)
            . "\n\nWer outline abschaltet, muss einen eigenen Umriss setzen — Farbe und Schatten überleben den Kontrastmodus nicht.");
    }
}
