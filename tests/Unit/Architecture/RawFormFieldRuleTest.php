<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RawFormFieldRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „rohe Formularfelder" (Vollscan 2026-08-23, I5 / WCAG
 * 1.3.1+3.3.2): `<label class="fieldset-label">` ohne `for` und ohne
 * umschlossenes Control ist für Screenreader nicht mit dem Feld verknüpft.
 *
 * Erlaubte Bauweisen (MVP-724, Baseline abgetragen):
 *  1. Feld-Komponenten `x-input-field` / `x-select-field` / `x-textarea-field`
 *     / `x-checkbox-field` — sie rendern label/for, aria-describedby und die
 *     Fehleranzeige selbst; bei mehrfach vorkommendem `name` (Loops,
 *     Detailformulare) zusätzlich `id="…"` setzen (I13).
 *  2. Rohes Control, wenn die Komponente nicht passt (Alpine-Repeater,
 *     Zeilenformulare in Tabellen, Zweig-Logik): `label for="…"` + `id="…"`
 *     am Control; in `<template x-for>`-Zeilen als `:for`/`:id` mit demselben
 *     Ausdruck wie `:name` (CSP-Build erlaubt Methodenaufrufe).
 *  3. Beschriftet die Zeile mehrere Controls (Von/Bis-Paare, x-date-range,
 *     x-tag-picker, x-user-checklist, Radio-/Checkbox-Gruppen) oder gar keins
 *     (unsichtbarer Platzhalter zur Ausrichtung), ist es KEIN Label:
 *     `<span class="fieldset-label">` als Gruppen-Überschrift verwenden und
 *     den Controls eigene `aria-label`/`title` geben.
 *
 * Regel: kein `fieldset-label`-Label ohne for-Verknüpfung — in
 * resources/views UND in den Plugin-Views (app/Plugins/**\/Resources/views).
 * Die BASELINE ist seit MVP-724 leer und bleibt es; neue Fundstellen sind
 * Verstöße, kein Baseline-Eintrag. Labels, die ihr Control umschließen
 * (Checkbox-/Toggle-Muster), sind implizit verknüpft und zählen nicht.
 */
class RawFormFieldRuleTest extends TestCase {
    use ScansSourceTree;

    /**
     * Datei → Fundstellen. Seit MVP-724 leer (Welle 8, Paket 3): die 337
     * Bestandsfälle sind auf Feld-Komponenten, explizite for/id-Paare oder
     * `<span class="fieldset-label">`-Gruppenüberschriften umgestellt.
     * Neue Einträge sind hier NICHT vorgesehen.
     *
     * @var array<string, int>
     */
    private const BASELINE = [];

    /** Zählt label.fieldset-label ohne for=, die kein Control umschließen. */
    private function countUnlinkedLabels(string $source): int {
        $count = 0;
        if (preg_match_all('~<label\b[^>]*\bfieldset-label\b[^>]*>~', $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return 0;
        }

        foreach ($matches[0] as [$tag, $offset]) {
            if (preg_match('~\bfor=~', $tag) === 1) {
                continue; // explizit verknüpft
            }
            $bodyStart = (int) $offset + strlen($tag);
            $end = strpos($source, '</label>', $bodyStart);
            $body = $end === false ? '' : substr($source, $bodyStart, $end - $bodyStart);
            if (preg_match('~<(?:input|select|textarea)\b|<x-(?:input|select|textarea|checkbox|tag-picker|user-select|project-select)~', $body) === 1) {
                continue; // umschließt sein Control → implizit verknüpft
            }
            $count++;
        }

        return $count;
    }

    public function test_views_do_not_add_unlinked_fieldset_labels(): void {
        $violations = [];
        $stale = [];
        $seen = [];

        $files = array_merge($this->bladeFiles(), $this->filesUnder('app/Plugins', '/\.blade\.php$/'));

        foreach ($files as $file) {
            $relative = $this->relativePath($file);
            $source = $this->stripBladeComments((string) file_get_contents($file));
            $count = $this->countUnlinkedLabels($source);
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
        $this->assertSame([], $violations, "Neues <label class=\"fieldset-label\"> ohne for-Verknüpfung (WCAG 1.3.1/3.3.2).\n"
            . "Stattdessen die Feld-Komponenten nutzen (x-input-field/x-select-field/x-textarea-field/\n"
            . "x-checkbox-field/x-date-range), label for=\"…\" + id am Control setzen — oder,\n"
            . "wenn die Beschriftung mehrere Controls überschreibt, <span class=\"fieldset-label\">.\n\n"
            . implode("\n", $violations));

        sort($stale);
        $this->assertSame([], $stale, "Baseline abtragen (Fundstellen gesunken/Datei weg) — Einträge in RawFormFieldRuleTest::BASELINE anpassen:\n"
            . implode("\n", $stale));
    }
}
