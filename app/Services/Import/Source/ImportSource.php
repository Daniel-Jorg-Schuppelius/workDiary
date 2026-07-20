<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Source;

use App\Services\Import\{EntitySpec, ValidationIssue};

/**
 * Format-Schicht **vor** der {@see EntitySpec} (MVP-438).
 *
 * Erkennt das Dateiformat (CSV/XLSX bzw. iCal) und liefert je Zeile eine
 * kanonische Feld-Map (keyed nach {@see EntitySpec::columns()}). Damit laufen
 * `normalize()` → `validateRow()` → `upsert()` der Spezifikation
 * **format-unabhängig** — die Specs kennen kein CSV/iCal.
 *
 * Der {@see \App\Services\Import\CsvPreflightAnalyzer} und der
 * {@see \App\Jobs\ProcessCsvImportJob} teilen sich diese Schicht; die früher
 * doppelte Header-/Stream-Logik lebt jetzt an einer Stelle.
 */
interface ImportSource {
    /**
     * Kopfzeilen-Befunde (fehlende/doppelte kanonische Spalten). Nur für
     * spaltenbasierte Formate relevant; iCal hat keine Kopfzeile → `[]`.
     *
     * @return list<ValidationIssue>
     */
    public function headerIssues(EntitySpec $spec): array;

    /**
     * Liefert die Datenzeilen (kanonische Feld-Maps) sowie nicht blockierende
     * Hinweiszeilen (z. B. übersprungene Ganztags-Events bei iCal).
     *
     * @return iterable<int, SourceRow>
     */
    public function rows(EntitySpec $spec): iterable;
}
