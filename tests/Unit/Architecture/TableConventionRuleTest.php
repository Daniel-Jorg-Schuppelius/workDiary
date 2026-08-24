<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TableConventionRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Tabellen-Appstandard als Gate (Sweep 2026-08-23):
 *
 *  R1  Leerzeilen laufen über <x-table.empty>, nie handgebaut als
 *      <tr><td colspan><x-empty-state> — sonst fehlen bg-base-100!-Override
 *      (Zebra!), Ghost-Ton und data-sort-ignore.
 *  R2  <x-table.th sort …> wirkt nur mit table-sort= am <x-table> — ohne
 *      rendert th.blade ein totes, unsortierbares <th>.
 *  R3  Kein rohes ->links(): Pagination läuft über <x-pagination>
 *      (Index-Listen mit `standing`).
 *  R4  Kein rohes <table> in Screen-Views — Listen nutzen <x-table>
 *      (in Karten mit :bare="true"). PDF-/Druck-/Legacy-Views und die
 *      dokumentierten Matrix-/Formularraster sind ausgenommen; neue bewusste
 *      Ausnahmen pro Zeile mit dem Marker `raw-table-ok` freigeben.
 *  R5  Nach einer Voll-Höhe-Tabelle (<x-table scroll="flex">) folgt kein
 *      sichtbarer Inhalt mehr — er läge unter dem Fold (flex-1 nimmt die
 *      Resthöhe) und bliebe unentdeckt. Erlaubt danach: <x-pagination
 *      standing>, versteckte Zeilen-Formulare (class="hidden"), <x-modal>
 *      (Dialog, unsichtbar) und schließende Tags. Bestand steht auf der
 *      Allow-List FLEX_TAIL_EXEMPT und wird beim nächsten Anfassen bereinigt.
 */
class TableConventionRuleTest extends TestCase {
    private const MARKER = 'raw-table-ok';

    /** Pfade (Regex auf den views-relativen Pfad), die R4 nicht unterliegen. */
    private const RAW_TABLE_PATH_EXEMPT = [
        '#^components/#',                    // x-table selbst rendert <table>
        '#^vendor/#',
        '#^legacy/#',                        // bewusst konservierte Alt-Ansichten
        '#(^|/)pdf/#i',                      // pdf/, reports/pdf/, …/pdf/ — Druckwelt ohne Komponenten
        '#(^|/)print/#i',
        '#(^|/)[^/]*pdf[^/]*\.blade\.php$#i',   // pdf.blade.php, *-pdf.blade.php
        '#(^|/)[^/]*print[^/]*\.blade\.php$#i', // print.blade.php u. ä. Druckseiten
        '#(^|/)mails?/#',
    ];

    /** Dokumentierte Bestandsausnahmen zu R4 (Matrix/Raster/Dokument/Eigenlayout). */
    private const RAW_TABLE_FILE_EXEMPT = [
        'assets/dossier.blade.php',              // Druck-Dossier (window.print)
        'diary/case-file.blade.php',             // Druck-Akte
        'public/protocol-sign.blade.php',        // öffentliche Signaturseite
        'invoices/_preview.blade.php',           // Belegvorschau (Dokument-Replikat)
        'install/requirements.blade.php',        // Installer-Layout
        'isms/risks/index.blade.php',            // Risikomatrix
        'isms/soa.blade.php',                    // SoA-Dokument
        'coverage-requirements/_heatmap.blade.php',
        'rooms/index.blade.php',                 // Raum-Belegungsraster (Zeitachse)
        'reports/absence-card.blade.php',        // Jahres-/Monatskarte (Matrix)
        'admin/shift-rotations/index.blade.php', // editierbares Rotationsraster
        'work-schedules/_form_body.blade.php',   // Formularraster im Dialog
        'forms/submissions/show.blade.php',      // Key/Value-Snapshot
        'b2b/catalog/browse.blade.php',          // Punchout-Eigenlayout
    ];

    /**
     * R5-Bestand (Vollscan 2026-08-23, I10): scroll=flex mit sichtbarem
     * Folge-Inhalt — außerhalb Welle 4 Batch C; beim nächsten Anfassen der
     * Seite auf „Inhalt vor die Tabelle" oder normale Tabelle umstellen.
     */
    private const FLEX_TAIL_EXEMPT = [
        'admin/wage-type-mappings/index.blade.php',    // Liefer-Karte unter der Tabelle
        'admin/plugin-errors/index.blade.php',         // Bulk-Aktionsleiste unter der Tabelle
        'admin/invoice-mail-templates/index.blade.php', // Variablen-Legende unter der Tabelle
        'admin/number-formats/index.blade.php',        // Hinweistext unter der Tabelle
        'cash-registers/show.blade.php',               // Tagesabschluss-Karte unter der Tabelle
    ];

    public function test_tables_follow_app_standard(): void {
        $root = dirname(__DIR__, 3);
        $viewsDir = $root . '/resources/views';
        $violations = [];

        foreach ($this->bladeFiles($viewsDir) as $file) {
            $rel = str_replace($viewsDir . '/', '', $file->getPathname());
            $src = (string) file_get_contents($file->getPathname());
            $isComponent = str_starts_with($rel, 'components/');

            // R1: handgebaute Leerzeile statt <x-table.empty>
            if (! $isComponent && preg_match('/<tr>\s*<td[^>]*colspan[^>]*>\s*<x-empty-state/s', $src, $m, PREG_OFFSET_CAPTURE)) {
                $violations[] = $rel . ':' . $this->lineOf($src, $m[0][1]) . '  R1 Leerzeile handgebaut — <x-table.empty :colspan=…> nutzen';
            }

            // R2: sort-Marker ohne table-sort= am <x-table>
            if (! $isComponent
                && preg_match('/<x-table\.th[^>]*\bsort\b/', $src, $m, PREG_OFFSET_CAPTURE)
                && ! str_contains($src, 'table-sort=')) {
                $violations[] = $rel . ':' . $this->lineOf($src, $m[0][1]) . '  R2 x-table.th sort ohne table-sort= (toter Sort-Marker)';
            }

            // R3: rohes ->links() statt <x-pagination>
            if ($rel !== 'components/pagination.blade.php'
                && ! str_starts_with($rel, 'vendor/')
                && preg_match('/->links\(/', $src, $m, PREG_OFFSET_CAPTURE)) {
                $violations[] = $rel . ':' . $this->lineOf($src, $m[0][1]) . '  R3 ->links() — <x-pagination :paginator=… standing> nutzen';
            }

            // R5: sichtbarer Inhalt nach <x-table scroll="flex">
            if (! $isComponent && ! in_array($rel, self::FLEX_TAIL_EXEMPT, true)) {
                $r5 = $this->flexTailViolation($src);
                if ($r5 !== null) {
                    $violations[] = $rel . ':' . $r5 . '  R5 sichtbarer Inhalt nach scroll="flex"-Tabelle — vor die Tabelle ziehen oder normale Tabelle nutzen';
                }
            }

            // R4: rohes <table> in Screen-Views
            if (! $this->rawTableExempt($rel)) {
                $lines = preg_split('/\r?\n/', $src) ?: [];
                foreach ($lines as $i => $line) {
                    if (! preg_match('/<table\b/', $line)) {
                        continue;
                    }
                    if (str_contains($line, self::MARKER) || str_contains($lines[$i - 1] ?? '', self::MARKER)) {
                        continue;
                    }
                    $violations[] = $rel . ':' . ($i + 1) . '  R4 rohes <table> — <x-table> nutzen (in Karten :bare="true")';
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Tabellen-Appstandard verletzt (siehe Regel-Doku im Test-Kopf).\n"
                . "Bewusste R4-Ausnahmen pro Zeile mit '" . self::MARKER . "' markieren oder (Matrix/Druck) in die Ausnahmeliste aufnehmen:\n  "
                . implode("\n  ", $violations)
        );
    }

    /**
     * R5: Zeilennummer des ersten sichtbaren Inhalts nach der
     * scroll="flex"-Tabelle — oder null, wenn der Nachlauf sauber ist.
     */
    private function flexTailViolation(string $src): ?int {
        if (! preg_match('/<x-table\b[^>]*scroll="flex"/s', $src, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $close = strpos($src, '</x-table>', $m[0][1] + strlen($m[0][0]));
        if ($close === false) {
            return null;
        }
        $tailStart = $close + strlen('</x-table>');
        $tail = substr($src, $tailStart);
        if (($end = strpos($tail, '@endsection')) !== false) {
            $tail = substr($tail, 0, $end);
        }

        // Erlaubtes ausblenden, Offsets erhalten (Ersatz durch Leerraum):
        $blank = static fn (array $mm): string => preg_replace('/\S/', ' ', $mm[0]) ?? '';
        // Blade-Kommentare
        $tail = (string) preg_replace_callback('/\{\{--.*?--\}\}/s', $blank, $tail);
        // Dialoge (unsichtbar, egal ob embedded)
        $tail = (string) preg_replace_callback('/<x-modal\b.*?<\/x-modal>/s', $blank, $tail);
        // versteckte Zeilen-Formulare (Tag darf mehrzeilig sein, -> beachten)
        $tail = (string) preg_replace_callback('/<form\b(?:[^>]|->)*?class="hidden"(?:[^>]|->)*?>.*?<\/form>/s', $blank, $tail);
        // stehende Pagination
        $tail = (string) preg_replace_callback('/<x-pagination\b[^>]*standing[^>]*\/?>/s', $blank, $tail);

        if (preg_match('/<(?:div|details|section|form|ul|ol|p|h[1-6]|table|x-card|x-table\b|x-form-group|x-kpi-tile|x-empty-state|x-filter-bar)\b/', $tail, $hit, PREG_OFFSET_CAPTURE)) {
            return $this->lineOf($src, $tailStart + (int) $hit[0][1]);
        }

        return null;
    }

    private function rawTableExempt(string $rel): bool {
        if (in_array($rel, self::RAW_TABLE_FILE_EXEMPT, true)) {
            return true;
        }
        foreach (self::RAW_TABLE_PATH_EXEMPT as $pattern) {
            if (preg_match($pattern, $rel)) {
                return true;
            }
        }

        return false;
    }

    private function lineOf(string $src, int $offset): int {
        return substr_count(substr($src, 0, $offset), "\n") + 1;
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function bladeFiles(string $dir): iterable {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                yield $file;
            }
        }
    }
}
