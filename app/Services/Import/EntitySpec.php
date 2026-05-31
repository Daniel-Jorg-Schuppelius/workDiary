<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EntitySpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\Import\ImportEntity;
use App\Models\Organization;

/**
 * Vertrag für entitätsspezifische CSV-Import-Spezifikationen (MVP-049).
 *
 * Jede Implementierung kapselt:
 * - die Liste erlaubter / pflichtiger Header,
 * - Header-Aliase (DE/EN),
 * - die Zeilenvalidierung,
 * - die Upsert-Logik (Idempotenz nach external_ref bzw. fachlichem Schlüssel).
 */
interface EntitySpec {
    public function entity(): ImportEntity;

    /**
     * Kanonische Spalten-Codes der Entität.
     *
     * @return list<string>
     */
    public function columns(): array;

    /**
     * Pflicht-Spalten (Teilmenge von {@see columns()}).
     *
     * @return list<string>
     */
    public function requiredColumns(): array;

    /**
     * Liefert {alias => kanonischer Code}. Vergleich erfolgt case-insensitiv
     * und nach trim().
     *
     * @return array<string, string>
     */
    public function headerAliases(): array;

    /**
     * Vorverarbeitung des kompletten Roh-Dateiinhalts vor dem Parsen, z. B. um
     * eine Excel-`sep=`-Hinweiszeile oder ein BOM zu entfernen. Standard:
     * unverändert. Wird vom {@see CsvPreflightAnalyzer} einmal beim Upload
     * angewandt; die bereinigte Fassung wird gespeichert und vom Job gelesen.
     */
    public function preprocessRaw(string $raw): string;

    /**
     * Normalisiert eine Zeile (Trim, Type-Coercion, Defaults) ohne Persistenz.
     *
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    public function normalize(array $row): array;

    /**
     * Validiert eine bereits normalisierte Zeile.
     *
     * @param  array<string, mixed>  $row
     * @return list<ValidationIssue>
     */
    public function validateRow(array $row, Organization $organization): array;

    /**
     * Führt den Upsert durch und gibt das Outcome zurück.
     *
     * Bei {@see ImportOutcome::Failed} muss zusätzlich ein
     * {@see ValidationIssue} als zweiter Rückgabewert via Tuple geliefert
     * werden.
     *
     * @param  array<string, mixed>  $row
     * @return array{0: ImportOutcome, 1: ?ValidationIssue}
     */
    public function upsert(array $row, Organization $organization): array;
}
