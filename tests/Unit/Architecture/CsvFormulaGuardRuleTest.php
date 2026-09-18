<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CsvFormulaGuardRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Gate zu S-46 (Sicherheitsscan 2026-08-23).
 *
 * Reines CSV-Quoting entschärft keine Formel: Excel und LibreOffice werten
 * eine Zelle, die mit `=`, `+`, `-` oder `@` beginnt, beim Öffnen aus. Die
 * Anwendung hat dafür einen zentralen Guard ({@see \App\Support\CsvExport}) —
 * vier Ausgabepfade schrieben aber direkt über `encodeLine()` daran vorbei,
 * darunter der DSGVO-/Stammdatenexport mit frei editierbaren Feldern (der
 * eigene Anzeigename, Kunden-Kommentar, Rechnungstext).
 *
 * Die Regel ist bewusst grob: **wer CSV schreibt, muss den Guard mindestens
 * einmal benutzen** — oder mit Begründung in der Ausnahmeliste stehen. Sie
 * fängt damit den eigentlichen Rückfall (eine neue Datei, die CSV ganz ohne
 * Guard schreibt), ohne bei Kopf- und Summenzeilen zu lärmen.
 */
class CsvFormulaGuardRuleTest extends TestCase {
    use ScansSourceTree;

    /**
     * Dateien, die bewusst ohne Guard schreiben — Datei => Begründung.
     *
     * @var array<string, string>
     */
    private const EXCEPTIONS = [
        'app/Support/CsvExport.php' => 'Hier wohnt der Guard selbst; die Kopfzeile besteht aus anwendungseigenen Spaltennamen.',
        'app/Support/Toolkit/CsvFacade.php' => 'Naht zum Toolkit: buildCsv() guardet die Datenzeilen selbst (Parameter guardFormulas), die Kopfzeile besteht aus anwendungseigenen Spaltennamen.',
        'app/Console/Commands/ExportAuditLog.php' => 'GoBD-Ausleitung: die Bytes sind über den head_hash kryptografisch gebunden. Ein vorangestellter Apostroph zerstörte genau den Nachweis, den die Datei erbringen soll.',
        'app/Services/TimeExport/Profiles/GenericCsvProfile.php' => 'Lohnexport per SFTP an ein Lohnsystem; payload_hash weist den ausgelieferten Stand nach. Geänderte Bytes hieße geänderte Lohndaten.',
        'app/Services/TimeExport/Profiles/DatevLodasProfile.php' => 'Lohnimportdatei für DATEV LODAS, kein Tabellendokument; payload_hash weist den Stand nach. Ein Apostroph vor negativen Stunden („-2,00") verfälschte die Lohndaten.',
        'app/Services/TimeExport/Profiles/LexwareProfile.php' => 'Lohnimportdatei für Lexware Lohn, kein Tabellendokument; payload_hash weist den Stand nach. Ein Apostroph vor negativen Werten verfälschte die Lohndaten.',
        'app/Services/Import/CsvPreflightAnalyzer.php' => 'Zwischenform beim IMPORT (XLSX-Blatt als CSV-Zeilen), keine ausgelieferte Datei.',
        'app/Services/Finance/GdpduExportService.php' => 'GoBD-Datenträgerüberlassung Z3: die Dateien sind über index.xml und den Paket-Hash gebunden und werden von der Prüfsoftware eingelesen, nicht in Excel geöffnet. Ein vorangestellter Apostroph zerstörte den Nachweis.',
    ];

    public function test_csv_ausgabe_nutzt_den_formel_guard(): void {
        $violations = [];

        foreach ($this->filesUnder('app', '/\.php$/') as $path) {
            $source = (string) file_get_contents($path);
            $relative = str_replace($this->repoRoot() . '/', '', $path);

            // CSV entsteht auf drei Wegen: direkt über encodeLine(), über die
            // Toolkit-Naht buildCsv() und über den CSVGenerator des Toolkits
            // (Sicherheitsaudit 2026-09-17, csvformula-1).
            $writesCsv = str_contains($source, 'encodeLine(')
                || str_contains($source, 'CsvFacade::buildCsv(')
                || str_contains($source, 'new CSVGenerator');
            if (! $writesCsv) {
                continue;
            }
            if (isset(self::EXCEPTIONS[$relative])) {
                continue;
            }
            if (
                str_contains($source, 'guardRow(')
                || str_contains($source, 'CsvExport::guard(')
                // buildCsv() guardet standardmäßig; ein ausdrückliches
                // guardFormulas: false steht mit Begründung im Aufrufer.
                || str_contains($source, 'CsvFacade::buildCsv(')
            ) {
                continue;
            }

            $violations[] = $relative;
        }

        $this->assertSame([], $violations, sprintf(
            "CSV-Ausgabe ohne Formel-Guard:\n  %s\n"
            . 'Zellen mit CsvExport::guardRow() umschließen — oder mit Begründung in die Ausnahmeliste.',
            implode("\n  ", $violations),
        ));
    }

    /** Eine Ausnahme ohne Begründung ist ein Vergessen mit Alibi. */
    public function test_ausnahmen_sind_begruendet_und_existieren(): void {
        foreach (self::EXCEPTIONS as $file => $reason) {
            $this->assertFileExists($this->repoRoot() . '/' . $file, "Ausnahme {$file} existiert nicht mehr.");
            $this->assertGreaterThan(40, mb_strlen(trim($reason)), "Ausnahme {$file} ist nicht begründet.");
        }
    }
}
