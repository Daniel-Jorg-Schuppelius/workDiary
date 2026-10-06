<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FontBootstrapRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „Schriften vor dem ersten Paint": Jedes eigenständige
 * Dokument mit App-Bundle bindet partials/font-bootstrap vor `@vite` ein.
 * Sonst stehen bei schwacher Leitung Ligatur-Namen wie „login" statt der
 * Icons im Layout (Startseite 2026-09-29: layouts/public ohne den Block).
 *
 *  F1  <!DOCTYPE> + @vite ohne @include('partials.font-bootstrap') davor
 *  F2  `html.fonts-loaded`-Regel außerhalb des Partials (eine Quelle)
 *  F3  ['icons' => false], obwohl die Seite Icons rendert — auch `<x-public-page>` ohne `icons`
 *  F4  Seite unter `public/` mit eigenem Dokumentkopf statt `<x-public-page>`
 *      (Konsolidierungs-Audit 2026-10, k4-06: 20 Kopien, vier ohne Sprache und `noindex`)
 */
class FontBootstrapRuleTest extends TestCase {
    use ScansSourceTree;

    private const PARTIAL = 'resources/views/partials/font-bootstrap.blade.php';

    private const INCLUDE = '~@include\(\s*[\'"]partials\.font-bootstrap[\'"]~';

    private const ICONS_OFF = '~@include\(\s*[\'"]partials\.font-bootstrap[\'"]\s*,\s*\[\s*[\'"]icons[\'"]\s*=>\s*false~';

    private const ICON_USE = '~<x-icon\b|<x-icon-btn\b|\s:?icon(?:-trailing)?\s*=|material-symbols-~';

    public function test_standalone_documents_bootstrap_fonts(): void {
        $violations = [];

        $files = $this->bladeFiles();

        foreach ($files as $file) {
            $relative = $this->relativePath($file);
            if ($relative === self::PARTIAL) {
                continue;
            }

            $source = $this->stripBladeComments((string) file_get_contents($file));

            if (str_contains($source, 'html.fonts-loaded')) {
                $violations[] = "F2 {$relative} — fonts-loaded-Regel gehört nur ins Partial";
            }

            if (str_starts_with($relative, 'resources/views/public/') && stripos($source, '<!DOCTYPE') !== false) {
                $violations[] = "F4 {$relative} — öffentliche Token-Seite mit eigenem Dokumentkopf, <x-public-page> nutzen";
            }
            if (preg_match('~^<x-public-page\b([^\n]*)>$~m', $source, $page) === 1 && preg_match('~\sicons(\s|$)~', $page[1]) !== 1 && preg_match(self::ICON_USE, $source) === 1) {
                $violations[] = "F3 {$relative} — rendert Icons, <x-public-page> lädt die Icon-Schrift ohne `icons` nicht vor";
            }

            $vite = strpos($source, '@vite(');
            if ($vite === false || stripos($source, '<!DOCTYPE') === false) {
                continue;
            }

            if (preg_match(self::INCLUDE, $source, $match, PREG_OFFSET_CAPTURE) !== 1 || $match[0][1] > $vite) {
                $violations[] = "F1 {$relative} — @include('partials.font-bootstrap') fehlt vor @vite";
                continue;
            }

            if (preg_match(self::ICONS_OFF, $source) === 1 && preg_match(self::ICON_USE, $source) === 1) {
                $violations[] = "F3 {$relative} — rendert Icons, lädt die Icon-Schrift aber nicht vor";
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "Schrift-Bootstrap in Standalone-Dokumenten:\n" . implode("\n", $violations));
    }
}
