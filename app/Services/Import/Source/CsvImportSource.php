<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CsvImportSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Source;

use App\Services\Import\{EntitySpec, HeaderMapper};
use App\Support\Toolkit\CsvFacade;
use CommonToolkit\Parsers\CSVDocumentParser;

/**
 * CSV-/XLSX-Quelle (MVP-438).
 *
 * Kapselt Delimiter-Erkennung, Header-Aliasauflösung und das Streamen der
 * Datenzeilen — die Logik, die zuvor im {@see \App\Services\Import\CsvPreflightAnalyzer}
 * UND im {@see \App\Jobs\ProcessCsvImportJob} dupliziert war. XLSX wird bereits
 * im Preflight in die interne CSV-Struktur überführt (A13); diese Quelle sieht
 * daher stets CSV.
 */
final class CsvImportSource implements ImportSource {
    private ?string $delimiter;

    public function __construct(
        private readonly string $absolutePath,
        ?string $delimiter = null,
    ) {
        $this->delimiter = $delimiter;
    }

    /**
     * Aufgelöster Delimiter (wird auf dem {@see \App\Models\ImportRun}
     * persistiert, damit der Job dieselbe Trennung nutzt).
     */
    public function delimiter(): string {
        return $this->delimiter ??= CSVDocumentParser::detectDelimiter($this->absolutePath);
    }

    public function headerIssues(EntitySpec $spec): array {
        return HeaderMapper::issues($spec, $this->rawHeader());
    }

    public function rows(EntitySpec $spec): iterable {
        // MVP-707: Kopfzeilen-Zuordnung zentral im HeaderMapper (auch Dokument-Manifest).
        $headerMap = HeaderMapper::map($spec, $this->rawHeader());
        $number = 0;

        foreach (CsvFacade::streamAssoc($this->absolutePath, $this->delimiter()) as $rawRow) {
            $number++;

            yield SourceRow::data($number, HeaderMapper::apply($rawRow, $headerMap));
        }
    }

    /**
     * Roh-Kopfzeile der Datei — öffentlich seit MVP-732 (Feature 148): die
     * KI-Spaltenzuordnung braucht die unaufgelösten Kopfzellen, um sie gegen
     * die Spec-Spalten vorzuschlagen. Der {@see HeaderMapper} bleibt die
     * deterministische Zuordnung; die KI ergänzt nur, was er nicht kennt.
     *
     * @return list<string>
     */
    public function rawHeader(): array {
        return array_values(CSVDocumentParser::readHeader($this->absolutePath, $this->delimiter())->getColumnNames());
    }
}
