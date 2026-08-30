<?php
/*
 * Created on   : Sat Aug 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlaceholderLabelRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „Platzhalter ist keine Beschriftung" (WCAG 3.3.2 / BFSG,
 * 2026-08-29).
 *
 * Ein `placeholder` verschwindet, sobald jemand tippt, hat in vielen Themes
 * zu wenig Kontrast, und Screenreader lesen ihn je nach Browser gar nicht
 * oder als Wert statt als Namen vor. Wer das Feld nicht sieht, weiß nach dem
 * ersten Zeichen nicht mehr, was er ausfüllt.
 *
 * **Neue Rechtslage:** Seit der Betreiber-Entscheidung vom 2026-08-29 werden
 * Kurse der Lernplattform auch an Verbraucher verkauft. Damit ist das BFSG
 * (EN 301 549 / WCAG 2.1 AA) keine Empfehlung mehr, sondern
 * Marktzugangsvoraussetzung.
 *
 * Erlaubte Bauweisen — eine davon muss zutreffen:
 *  1. Feld-Komponenten (`x-input-field` & Co.) — sie setzen `label`/`for`
 *     selbst.
 *  2. Rohes Control mit `id`, dazu ein `<label for="…">` (auch `sr-only`,
 *     wenn eine sichtbare Beschriftung die Zeile sprengt).
 *  3. Control von einem `<label>` umschlossen (implizit verknüpft).
 *  4. `aria-label` bzw. `:aria-label` am Control — das Mittel für kompakte
 *     Zeilenformulare in Karten und Tabellen.
 *
 * **Der Platzhaltertext taugt nicht immer als Beschriftung.** `000000`,
 * `SP`, `L`/`B`/`H` oder ein Cron-Ausdruck sind Formathinweise; dort gehört
 * ein ausgeschriebener Name hin, nicht der Platzhalter. Deshalb prüft dieses
 * Gate nur das Vorhandensein eines Namens — den Inhalt muss ein Mensch
 * beurteilen.
 *
 * Geprüft werden `resources/views` UND die Plugin-Views
 * (`app/Plugins/**\/Resources/views`), sonst wäre die Regel über die
 * Plugin-Oberflächen umgehbar.
 *
 * Die BASELINE ist leer und bleibt es: die 196 Bestandsfälle wurden am
 * 2026-08-29 abgetragen. Neue Fundstellen sind Verstöße, kein
 * Baseline-Eintrag.
 */
class PlaceholderLabelRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, int> */
    private const BASELINE = [];

    /**
     * Vollständigen Tag ab `<` lesen — Anführungszeichen respektierend.
     *
     * Ein naives `[^>]*>` bricht an `=>` oder `->` in Blade-Ausdrücken ab
     * und liefert einen abgeschnittenen Tag; ein bereits gesetztes
     * `aria-label` würde dann übersehen (Fehlalarm) und ein echter Fund
     * hinter dem Abbruch verpasst.
     */
    private function readTag(string $source, int $start): ?string {
        $quote = null;
        $length = strlen($source);

        for ($i = $start; $i < $length; $i++) {
            $char = $source[$i];

            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;

                continue;
            }

            if ($char === '>') {
                return substr($source, $start, $i - $start + 1);
            }
        }

        return null;
    }

    /** Umschließt ein `<label>` dieses Control? Dann ist es implizit verknüpft. */
    private function wrappedInLabel(string $source, int $position): bool {
        $before = substr($source, 0, $position);
        $open = strrpos($before, '<label');

        if ($open === false) {
            return false;
        }

        return strpos($before, '</label>', $open) === false;
    }

    private function countUnnamedPlaceholders(string $source): int {
        $count = 0;

        if (preg_match_all('~<(?:input|textarea|select)\b~', $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return 0;
        }

        foreach ($matches[0] as [, $offset]) {
            $tag = $this->readTag($source, (int) $offset);

            if ($tag === null || ! str_contains($tag, 'placeholder')) {
                continue;
            }

            // Versteckte Felder haben keine Bedienoberfläche.
            if (str_contains($tag, 'type="hidden"')) {
                continue;
            }

            // Zugänglicher Name direkt am Control …
            if (preg_match('~\s:?aria-label(?:ledby)?=~', $tag) === 1) {
                continue;
            }

            // … oder über ein label[for] verknüpfbar.
            if (preg_match('~\s:?id=~', $tag) === 1) {
                continue;
            }

            if ($this->wrappedInLabel($source, (int) $offset)) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    public function test_views_do_not_use_placeholder_as_only_label(): void {
        $violations = [];
        $stale = [];
        $seen = [];

        $files = array_merge($this->bladeFiles(), $this->filesUnder('app/Plugins', '/\.blade\.php$/'));

        foreach ($files as $file) {
            $relative = $this->relativePath($file);
            $source = $this->stripBladeComments((string) file_get_contents($file));
            $count = $this->countUnnamedPlaceholders($source);
            $seen[$relative] = true;

            $allowed = self::BASELINE[$relative] ?? 0;

            if ($count > $allowed) {
                $violations[] = sprintf('%s — %d Feld(er) nur mit Platzhalter, Baseline erlaubt %d', $relative, $count, $allowed);
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
        $this->assertSame([], $violations, "Eingabefeld nur mit Platzhalter statt Beschriftung (WCAG 3.3.2 / BFSG).\n"
            . "Ein Platzhalter verschwindet beim Tippen — wer das Feld nicht sieht, weiß dann nicht mehr, was er ausfüllt.\n"
            . "Abhilfe: Feld-Komponente nutzen, label for=\"…\" + id setzen (sr-only ist erlaubt),\n"
            . "das Control in ein <label> fassen — oder aria-label am Control.\n"
            . "ACHTUNG: den Platzhaltertext NICHT blind übernehmen. `000000`, `SP` oder `L` sind\n"
            . "Formathinweise, keine Namen; dort gehört ein ausgeschriebener Name hin.\n\n"
            . implode("\n", $violations));

        sort($stale);
        $this->assertSame([], $stale, "Baseline abtragen — Einträge in PlaceholderLabelRuleTest::BASELINE anpassen:\n"
            . implode("\n", $stale));
    }
}
