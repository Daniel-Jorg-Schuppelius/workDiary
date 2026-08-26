<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RawIconRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „rohe Icons" (Vollscan 2026-08-23, MVP-724): Icons laufen
 * über `<x-icon name="…">` bzw. `<x-icon-btn icon="…">` — nur dort stehen
 * `aria-hidden`, `data-icon`, die FILL/wght-Variationen und die
 * `shrink-0/align-middle`-Basisklassen zentral. Handgeschriebene
 * `<span class="material-symbols-outlined">…</span>` und Inline-`<svg>`
 * driften davon ab (fehlendes aria-hidden = Screenreader liest den Icon-Namen
 * vor) und lassen sich nicht global umstellen.
 *
 * Ebenfalls verboten: Icon-Markup als Prop-String
 * (`icon='<span class="material-symbols-outlined">…</span>'`) — `x-empty-state`
 * / `x-table.empty` nehmen den bloßen Namen (`icon="inbox"`).
 *
 * Geprüft werden resources/views (ohne components/, die das Markup selbst
 * rendern) und die Plugin-Views. Die Ausnahmen unten sind einzeln begründet;
 * neue Fundstellen sind Verstöße.
 */
class RawIconRuleTest extends TestCase {
    use ScansSourceTree;

    /**
     * Begründete Ausnahmen: Datei → Anzahl erlaubter Fundstellen.
     *
     * @var array<string, int>
     */
    private const ALLOWED = [
        // Standalone-Fehlerseite: rendert bewusst ohne Layout/Komponenten-Kette
        // (greift auch, wenn der Container halb kaputt ist) und bringt ihr CSS inline mit.
        'resources/views/errors/_page.blade.php' => 1,
        // Icon-Name kommt zur Laufzeit aus Alpine (x-text) — <x-icon> kann nur
        // einen serverseitigen Namen rendern.
        'resources/views/ideas/show.blade.php' => 2,
    ];

    /** Rohe Material-Symbol-Spans (Element, nicht Prop-String). */
    private const RAW_SPAN = '~<span\b[^>]*\bmaterial-symbols-outlined\b~';

    /** Icon-Markup als Prop-String statt bloßem Namen. */
    private const ICON_PROP_MARKUP = '~\bicon\s*=\s*([\'"])\s*<~';

    /** Inline-SVG (Icons gehören in x-icon, Diagramme in components/charts). */
    private const INLINE_SVG = '~<svg\b~';

    public function test_views_use_the_icon_components(): void {
        $violations = [];
        $stale = [];
        $seen = [];

        $files = array_merge($this->bladeFiles(), $this->filesUnder('app/Plugins', '/\.blade\.php$/'));

        foreach ($files as $file) {
            $relative = $this->relativePath($file);
            if (str_starts_with($relative, 'resources/views/components/')) {
                continue; // x-icon/x-charts rendern das Markup selbst
            }

            $source = $this->stripBladeComments((string) file_get_contents($file));
            $count = (int) preg_match_all(self::RAW_SPAN, $source)
                + (int) preg_match_all(self::ICON_PROP_MARKUP, $source)
                + (int) preg_match_all(self::INLINE_SVG, $source);
            $seen[$relative] = true;

            $allowed = self::ALLOWED[$relative] ?? 0;
            if ($count > $allowed) {
                $violations[] = sprintf('%s — %d Fundstelle(n), erlaubt %d', $relative, $count, $allowed);
            } elseif ($count < $allowed) {
                $stale[] = sprintf("'%s' => %d, // aktuell %d", $relative, $allowed, $count);
            }
        }

        foreach (array_keys(self::ALLOWED) as $relative) {
            if (! isset($seen[$relative])) {
                $stale[] = sprintf("'%s' — Datei existiert nicht mehr, Eintrag streichen", $relative);
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "Rohes Icon-Markup in Views.\n"
            . "Stattdessen <x-icon name=\"…\" /> bzw. <x-icon-btn icon=\"…\"> verwenden;\n"
            . "x-empty-state/x-table.empty nehmen den bloßen Namen (icon=\"inbox\").\n\n"
            . implode("\n", $violations));

        sort($stale);
        $this->assertSame([], $stale, "Ausnahmeliste abtragen — Einträge in RawIconRuleTest::ALLOWED anpassen:\n"
            . implode("\n", $stale));
    }
}
